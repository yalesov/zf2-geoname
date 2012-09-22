<?php
namespace Heartsentwined\Geoname\Service;

use Heartsentwined\ArgValidator\ArgValidator;
use Heartsentwined\Cli\Cli;
use Heartsentwined\FileSystemManager\FileSystemManager;
use Heartsentwined\Cron\Service\Cron;
use Doctrine\ORM\EntityManager;
use Heartsentwined\Geoname\Entity;
use Heartsentwined\Geoname\Repository;

/**
 * Geoname module that syncs local database with geonames source
 *
 * will perform the following tasks automatically:
 * - fetch source files, including daily updates
 * - install the geonames database
 * - update the geonames database
 *
 * @author heartsentwined <heartsentwined@cogito-lab.com>
 * @license GPL http://opensource.org/licenses/gpl-license.php
 */
class Geoname
{
    /**
     * cli app helper
     *
     * @var Cli
     */
    protected $cli;
    public function setCli(Cli $cli)
    {
        $this->cli = $cli;
        return $this;
    }
    public function getCli()
    {
        return $this->cli;
    }

    /**
     * ORM Entity Manager
     *
     * @var EntityManager
     */
    protected $em;
    public function setEm(EntityManager $em)
    {
        $this->em = $em;
        return $this;
    }
    public function getEm()
    {
        return $this->em;
    }

    /**
     * tmp dir for storing geoname data source files
     *
     * @var string
     */
    protected $tmpDir;
    public function setTmpDir($tmpDir)
    {
        ArgValidator::assert($tmpDir, array('string', 'min' => 1));
        $this->tmpDir = $tmpDir;
        return $this;
    }
    public function getTmpDir()
    {
        return $this->tmpDir;
    }

    /**
     * cron expression: how frequently Geoname module should be run
     * for both auto-install and auto-update
     *
     * @var mixed
     */
    protected $cron;
    public function setCron($cron)
    {
        $this->cron = $cron;
        return $this;
    }
    public function getCron()
    {
        return $this->cron;
    }

    /**
     * main entry point
     *
     * @return self
     */
    public function run()
    {
        $this->downloadUpdate();

        $meta = $this->getMeta();
        switch ($meta->getStatus()) {
            case Repository\Meta::STATUS_INSTALL:
            case Repository\Meta::STATUS_INSTALL_DOWNLOAD:
                $this->install();
                break;
            case Repository\Meta::STATUS_UPDATE:
                $this->update();
                break;
        }

        return $this;
    }

    /**
     * get meta information
     *
     * @return Entity\Meta
     */
    public function getMeta()
    {
        $em = $this->getEm();
        $repo = $em->getRepository('Heartsentwined\Geoname\Entity\Meta');
        $meta = $repo->findOneBy(array());
        if (!$meta) {
            $meta = new Entity\Meta;
            $em->persist($meta);
            $meta->setStatus(Repository\Meta::STATUS_INSTALL_DOWNLOAD);
            $em->flush();
        }
        return $meta;
    }

    /**
     * download a file from source
     *
     * will implement a simple lock mechanism to prevent parallel downloading
     * will also ignore a file if it is marked as *.done
     *
     * @param string $src   source URL
     * @param string $dest  destination save path
     * @return bool
     */
    public function downloadFile($src, $dest)
    {
        if (file_exists($dest)
            || file_exists("$dest.lock")
            || file_exists("$dest.done")) {
            return false;
        }
        try {
            $headers = get_headers($src);
            if (preg_match('/40\d/', $headers[0])) {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
        $dest = "$dest.lock";
        $fh = fopen($dest, 'w');
        fwrite($fh, file_get_contents($src));
        fclose($fh);
        rename($dest, substr($dest, 0, -5));
        return true;
    }

    /**
     * fetch the latest modification files from geoname, if needed
     *
     * @return self
     */
    public function downloadUpdate()
    {
        $cli = $this->getCli();
        $em = $this->getEm();
        $tmpDir = $this->getTmpDir();

        $now = \DateTime::createFromFormat('U', strtotime('-1 day'));
        $date = $now->format('Y-m-d');

        foreach (array(
            "$tmpDir/update/place/modification",
            "$tmpDir/update/place/delete",
            "$tmpDir/update/altName/modification",
            "$tmpDir/update/altName/delete",
        ) as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        $cli->write('Download updates', 'section');
        $files = array(
            'place/modification' => "http://download.geonames.org/export/dump/modifications-$date.txt",
            'place/delete' => "http://download.geonames.org/export/dump/deletes-$date.txt",
            'altName/modification' => "http://download.geonames.org/export/dump/alternateNamesModifications-$date.txt",
            'altName/delete' => "http://download.geonames.org/export/dump/alternateNamesDeletes-$date.txt",
        );
        foreach ($files as $dir => $url) {
            $cli->write($url, 'module');
            $file = "$tmpDir/update/$dir/" . basename($url);
            $this->downloadFile($url, $file);
        }
        return $this;
    }

    /**
     * auto-install geoname database
     *
     * install routine is broken into small parts
     * to avoid clogging up system resources for too long
     * and to be more friendly to script max execution time settings
     *
     * the parts should run in under 15 mins even on shared host
     *
     * @return self
     */
    public function install()
    {
        $cli = $this->getCli();
        $tmpDir = $this->getTmpDir();
        $em = $this->getEm();
        $countryRepo = $em->getRepository('Heartsentwined\Geoname\Entity\Country');
        $featureRepo = $em->getRepository('Heartsentwined\Geoname\Entity\Feature');
        $languageRepo = $em->getRepository('Heartsentwined\Geoname\Entity\Language');
        $placeRepo = $em->getRepository('Heartsentwined\Geoname\Entity\Place');
        $timezoneRepo = $em->getRepository('Heartsentwined\Geoname\Entity\Timezone');

        # block 1

        /* download files */

        if (!file_exists("$tmpDir/countryInfo.txt")
            && !file_exists("$tmpDir/allCountries.zip")) {
            $cli->write('Download files', 'section');
            $files = array(
                'http://download.geonames.org/export/dump/countryInfo.txt',
                'http://download.geonames.org/export/dump/featureCodes_en.txt',
                //'http://download.geonames.org/export/dump/iso-languagecodes.txt',
                // contained in alternateNames.zip
                'http://download.geonames.org/export/dump/timeZones.txt',
                'http://download.geonames.org/export/dump/hierarchy.zip',
                'http://download.geonames.org/export/dump/alternateNames.zip',
                'http://download.geonames.org/export/dump/allCountries.zip',
            );
            foreach ($files as $url) {
                $cli->write($url, 'module');
                $this->downloadFile($url, $file);
            }
            return $this;
        }

        # block 2

        /* prepare files */

        if (!file_exists("$tmpDir/allCountries.txt")
            && !file_exists("$tmpDir/hierarchy.txt")) {
            $cli->write('Prepare files', 'section');
            foreach (array(
                'allCountries' => 25000,
                'alternateNames' => 50000,
                'hierarchy' => 250000,
            ) as $dir => $lineCount) {
                $cli->write($dir, 'module');

                if (!is_dir("$tmpDir/$dir")) {
                    mkdir("$tmpDir/$dir");
                }

                $cli->write('unzip', 'task');
                $zip = new \ZipArchive;
                $zip->open("$tmpDir/$dir.zip");
                $zip->extractTo($tmpDir);
                $zip->close();

                $cli->write('split files', 'task');
                $srcFh = fopen("$tmpDir/$dir.txt", 'r');
                $curLine = 1;
                while ($line = fgets($srcFh)) {
                    if ($curLine % $lineCount == 1) {
                        $newFh = fopen("$tmpDir/$dir/$curLine", 'w');
                    }
                    fwrite($newFh, $line);
                    if ($curLine % $lineCount == 0) {
                        fclose($newFh);
                    }
                    $curLine++;
                }
            }

            return $this;
        }

        # block 3

        if (file_exists("$tmpDir/featureCodes_en.txt")) {

            /* language */

            $cli->write('Language', 'section');
            $source = "$tmpDir/iso-languagecodes.txt";
            if ($fh = fopen($source, 'r')) {
                rename($source, "$source.lock");
                fgets($fh); // skip first line
                while ($line = fgets($fh)) {
                    list($iso3, $iso2, $iso1, $name) =
                        explode("\t", $line);
                    $language = new Entity\Language;
                    $em->persist($language);
                    $language
                        ->setName($name)
                        ->setIso3($iso3)
                        ->setIso2($iso2)
                        ->setIso1($iso1);
                }
                fclose($fh);
            }

            /* feature */

            $cli->write('Feature', 'section');
            $source = "$tmpDir/featureCodes_en.txt";
            if ($fh = fopen($source, 'r')) {
                rename($source, "$source.lock");
                $parentMap = array();
                $parentDesc = array(
                    'A' => 'country, state, region',
                    'H' => 'stream, lake',
                    'L' => 'parks, area',
                    'P' => 'city, village',
                    'R' => 'road, railroad',
                    'S' => 'spot, building, farm',
                    'T' => 'mountain, hill, rock',
                    'U' => 'undersea',
                    'V' => 'forest, heath',
                );
                while ($line = fgets($fh)) {
                    list($rawCode, $description, $comment) =
                        explode("\t", $line);

                    if ($rawCode == 'null') {
                        continue;
                    }

                    list($parentCode, $code) =
                        explode('.', $rawCode);

                    if (isset($parentMap[$parentCode])) {
                        $parent = $parentMap[$parentCode];
                    } else {
                        $parent = new Entity\Feature;
                        $em->persist($parent);
                        $parent->setCode($parentCode);
                        if (isset($parentDesc[$parentCode])) {
                            $parent->setDescription($parentDesc[$parentCode]);
                        }
                        $parentMap[$parentCode] = $parent;
                    }

                    $feature = new Entity\Feature;
                    $em->persist($feature);
                    $feature
                        ->setCode($code)
                        ->setDescription($description)
                        ->setComment($comment)
                        ->setParent($parent);
                }
                fclose($fh);
            }

            $em->flush();
            rename("$tmpDir/iso-languagecodes.txt.lock",
                "$tmpDir/iso-languagecodes.txt.done");
            rename("$tmpDir/featureCodes_en.txt.lock",
                "$tmpDir/featureCodes_en.txt.done");
            return $this;
        }

        # block 4 * n

        /* place #1: omit hierarchy + timezone */

        $sourceDir = "$tmpDir/allCountries";
        foreach (FileSystemManager::fileIterator($sourceDir) as $source) {
            if (!strpos($source, '.done')
                && !strpos($source, '.lock')
                && $fh = fopen($source, 'r')) {
                $cli->write('Place #1: omit hierarchy + timezone', 'section');
                rename($source, "$source.lock");
                while ($line = fgets($fh)) {
                    list($id, $name, /*ascii name*/, /*alt name*/,
                        $latitude, $longitude, $featureClass, $featureCode,
                        $countryCode, /*alt country code*/,
                        $admin1Code, $admin2Code, $admin3Code, $admin4Code,
                        $population, /*elevation*/, /*digital elevation model*/,
                        $timezoneCode, /*modification date*/) =
                        explode("\t", $line);

                    $place = new Entity\Place;
                    $place
                        ->setId($id)
                        ->setName($name)
                        ->setLatitude($latitude)
                        ->setLongitude($longitude)
                        ->setCountryCode($countryCode)
                        ->setAdmin1Code($admin1Code)
                        ->setAdmin2Code($admin2Code)
                        ->setAdmin3Code($admin3Code)
                        ->setAdmin4Code($admin4Code)
                        ->setPopulation($population);
                    $em->persist($place);

                    $featureCode = "$featureClass.$featureCode";
                    if ($feature = $featureRepo->findByGeonameCode($featureCode)) {
                        $place->setFeature($feature);
                    }
                }
                fclose($fh);
                $em->flush();
                rename("$source.lock", "$source.done");
                return $this;
            }
        }

        # block 5

        if (file_exists("$tmpDir/countryInfo.txt")) {

            /* country #1: omit neighbour */

            $cli->write('Country #1: omit neighbour', 'section');
            $currencyMap = array();
            $localeMap = array();
            $languageMap = array();
            $countryMap = array();
            $continentMap = array(
                'AF' => 6255146,
                'AS' => 6255147,
                'EU' => 6255148,
                'NA' => 6255149,
                'OC' => 6255151,
                'SA' => 6255150,
                'AN' => 6255152,
            );
            $source = "$tmpDir/countryInfo.txt";
            if ($fh = fopen($source, 'r')) {
                rename($source, "$source.lock");
                while ($line = fgets($fh)) {
                    if (substr(trim($line), 0, 1) == '#') {
                        continue;
                    }
                    list($iso2, $iso3, $isoNum, /*fips*/, /*name*/,
                        $capital, $area, $population, $continentCode, $tld,
                        $currencyCode, $currencyName, $phone,
                        $postalCode, $postalCodeRegex, $localeCodes, $placeId,
                        $neighbours, /*equiv fips code*/) =
                        explode("\t", $line);
                    if ($area == 'NA') $area = null;
                    if ($population == 'NA') $population = null;
                    $country = new Entity\Country;
                    $em->persist($country);
                    $countryMap[$iso2] = $country;
                    $country
                        ->setIso3($iso3)
                        ->setIso2($iso2)
                        ->setIsoNum($isoNum)
                        ->setCapital($capital)
                        ->setArea($area)
                        ->setPopulation($population)
                        ->setTld($tld)
                        ->setPhone($phone)
                        ->setPostalCode($postalCode)
                        ->setPostalCodeRegex($postalCodeRegex);

                    if ($place = $placeRepo->find((int)$placeId)) {
                        $country->setPlace($place);
                    }
                    if (isset($continentMap[$continentCode])) {
                        if ($continent = $placeRepo->find((int)$continentMap[$continentCode])) {
                            $country->setContinent($continent);
                        }
                    }
                    if (isset($currencyMap[$currencyCode])) {
                        $currency = $currencyMap[$currencyCode];
                    } else {
                        $currency = new Entity\Currency;
                        $em->persist($currency);
                        $currency
                            ->setCode($currencyCode)
                            ->setName($currencyName);
                        $currencyMap[$currencyCode] = $currency;
                    }
                    $country->setCurrency($currency);
                    $count = 1;
                    foreach (array_unique(explode(',', $localeCodes)) as $localeCode) {
                        $localeCode = strtr($localeCode, '-', '_');
                        if (isset($localeMap[$localeCode])) {
                            $locale = $localeMap[$localeCode];
                        } else {
                            $locale = new Entity\Locale;
                            $em->persist($locale);
                            $localeMap[$localeCode] = $locale;
                            $locale->setCode($localeCode);
                            if ($count == 1) {
                                $locale->setIsMain(true);
                            } else {
                                $locale->setIsMain(false);
                            }
                            list($iso2) = explode('_', $localeCode);
                            if (isset($languageMap[$iso2])) {
                                $language = $languageMap[$iso2];
                            } else {
                                if (!$language = $languageRepo->findOneBy(
                                    array('iso2' => $iso2))) {
                                    $language = new Entity\Language;
                                    $em->persist($language);
                                    $language->setIso2($iso2);
                                }
                                $languageMap[$iso2] = $language;
                            }
                            $locale->setLanguage($language);
                        }
                        $country->addLocale($locale);
                        $count++;
                    }
                }
                fclose($fh);
            }

            /* timezone */

            $cli->write('Timezone', 'section');
            $source = "$tmpDir/timeZones.txt";
            if ($fh = fopen($source, 'r')) {
                rename($source, "$source.lock");
                fgets($fh); //skip header
                while ($line = fgets($fh)) {
                    list($countryCode, $code, /*offset 1 Jan*/,
                        /*offset 1 Jul*/, /*raw offset*/) =
                        explode("\t", $line);
                    $timezone = new Entity\Timezone;
                    $em->persist($timezone);
                    $timezone->setCode($code);
                    if (isset($countryMap[$countryCode])) {
                        $timezone->setCountry($countryMap[$countryCode]);
                    }
                }
                fclose($fh);
            }

            /* country #2: neighbour */

            $cli->write('Country #2: neighbour', 'section');
            $source = "$tmpDir/countryInfo.txt.lock";
            if ($fh = fopen($source, 'r')) {
                while ($line = fgets($fh)) {
                    if (substr(trim($line), 0, 1) == '#') {
                        continue;
                    }
                    list($iso2, $iso3, $isoNum, /*fips*/, /*name*/,
                        $capital, $area, $population, $continentCode, $tld,
                        $currencyCode, $currencyName, $phone,
                        $postalCode, $postalCodeRegex, $localeCodes, $placeId,
                        $neighbours, /*equiv fips code*/) =
                        explode("\t", $line);
                    if (!$country = $countryMap[$iso2]) {
                        continue;
                    }
                    foreach (explode(',', $neighbours) as $neighbourCode) {
                        if (isset($countryMap[$neighbourCode])) {
                            $neighbour = $countryMap[$neighbourCode];
                            $country->addCountry($neighbour);
                        }
                    }
                }
                fclose($fh);
            }

            $em->flush();
            rename("$tmpDir/countryInfo.txt.lock",
                "$tmpDir/countryInfo.txt.done");
            rename("$tmpDir/timeZones.txt.lock",
                "$tmpDir/timeZones.txt.done");
            return $this;
        }

        # block 6 * n

        /* place #2: timezone */

        $sourceDir = "$tmpDir/allCountries";
        $countryMap = array();
        foreach (FileSystemManager::fileIterator($sourceDir) as $source) {
            if (!strpos($source, '.done2')
                && !strpos($source, '.lock2')
                && $fh = fopen($source, 'r')) {
                $cli->write('Place #2: timezone', 'section');
                rename($source, "$source.lock2");
                while ($line = fgets($fh)) {
                    list($id, /*$name*/, /*ascii name*/, /*alt name*/,
                        /*$latitude*/, /*$longitude*/, /*$featureClass*/, /*$featureCode*/,
                        $countryCode, /*alt country code*/,
                        /*$admin1Code*/, /*$admin2Code*/, /*$admin3Code*/, /*$admin4Code*/,
                        /*$population*/, /*elevation*/, /*digital elevation model*/,
                        $timezoneCode, /*modification date*/) =
                        explode("\t", $line);
                    if (!$place = $placeRepo->find((int)$id)) {
                        continue;
                    }

                    if (!$timezone = $timezoneRepo->findOneBy(
                        array('code' => $timezoneCode))) {
                        $timezone = new Entity\Timezone;
                        $em->persist($timezone);
                        $timezone->setCode($timezoneCode);
                        if (isset($countryMap[$countryCode])) {
                            $timezone->setCountry($countryMap[$countryCode]);
                        } elseif ($country = $countryRepo->findOneBy(
                            array('iso2' => $countryCode))) {
                            $timezone->setCountry($country);
                        }
                    }
                    $place->setTimezone($timezone);
                }
                fclose($fh);
                $em->flush();
                rename("$source.lock2", "$source.done2");
                return $this;
            }
        }

        # block 7 * n

        /* hierarchy */

        $sourceDir = "$tmpDir/hierarchy";
        foreach (FileSystemManager::fileIterator($sourceDir) as $source) {
            if (!strpos($source, '.done')
                && !strpos($source, '.lock')
                && $fh = fopen($source, 'r')) {
                $cli->write('Hierarchy', 'section');
                rename($source, "$source.lock");
                while ($line = fgets($fh)) {
                    list($parentId, $childId, $type) =
                        explode("\t", $line);

                    if ($parent = $placeRepo->find((int)$parentId)
                        && $child = $placeRepo->find((int)$childId)) {
                        $child->setParent($parent);
                    }
                }
                fclose($fh);
                $em->flush();
                rename("$source.lock", "$source.done");
                return $this;
            }
        }

        # block 8 * n

        /* alt names */

        $sourceDir = "$tmpDir/alternateNames";
        $languageMap = array();
        foreach (FileSystemManager::fileIterator($sourceDir) as $source) {
            if (!strpos($source, '.done')
                && !strpos($source, '.lock')
                && $fh = fopen($source, 'r')) {
                rename($source, "$source.lock");
                $cli->write('Alt names', 'section');
                while ($line = fgets($fh)) {
                    list($id, $placeId, $languageCode, $name,
                        $isPreferred, $isShort, $isColloquial, $isHistoric) =
                        explode("\t", $line);

                    $altName = new Entity\AltName;
                    $altName
                        ->setId($id)
                        ->setName($name)
                        ->setIsPreferred((bool)trim($isPreferred))
                        ->setIsShort((bool)trim($isShort))
                        ->setIsColloquial((bool)trim($isColloquial))
                        ->setIsHistoric((bool)trim($isHistoric));
                    $em->persist($altName);

                    if ($place = $placeRepo->find((int)$placeId)) {
                        $altName->setPlace($place);
                    }

                    if (isset($languageMap[$languageCode])) {
                        $altName->setLanguage($languageMap[$languageCode]);
                    } elseif ($language = $languageRepo->findLanguage($languageCode)) {
                        $altName->setLanguage($language);
                        $languageMap[$languageCode] = $language;
                    } else {
                        $altName->setLanguageOther($languageCode);
                    }
                }
                fclose($fh);
                $em->flush();
                rename("$source.lock", "$source.done");
                return $this;
            }
        }

        # block 9

        /* install dir cleanup */

        $cli->write('Install dir cleanup', 'task');
        foreach (new \FilesystemIterator($tmpDir) as $dir) {
            if (!is_dir($dir)) {
                unlink($dir);
            } else {
                if (basename($dir) != 'update') {
                    FileSystemManager::rrmdir($dir);
                }
            }
        }

        /* update meta status */

        $cli->write('Update meta status: install => update', 'task');
        $this->getMeta()->setStatus(Repository\Meta::STATUS_UPDATE);
        $em->flush();

        return $this;
    }

    /**
     * auto-update geoname database
     *
     * @return self
     */
    public function update()
    {
        $cli = $this->getCli();
        $tmpDir = $this->getTmpDir();
        $em = $this->getEm();
        $placeRepo = $em->getRepository('Heartsentwined\Geoname\Entity\Place');
        $featureRepo = $em->getRepository('Heartsentwined\Geoname\Entity\Feature');
        $altNameRepo = $em->getRepository('Heartsentwined\Geoname\Entity\AltName');
        $languageRepo = $em->getRepository('Heartsentwined\Geoname\Entity\Language');
        $timezoneRepo = $em->getRepository('Heartsentwined\Geoname\Entity\Timezone');
        $countryRepo = $em->getRepository('Heartsentwined\Geoname\Entity\Country');

        $files = array();

        /* place */

        $cli->write('Place', 'section');
        $sourceDir = "$tmpDir/update/place/modification";
        $countryMap = array();
        $featureMap = array();
        $placeMap = array();
        foreach (FileSystemManager::fileIterator($sourceDir) as $source) {
            if (!strpos($source, '.lock')
                && !strpos($source, '.done')
                && $fh = fopen($source, 'r')) {
                rename($source, "$source.lock");
                while ($line = fgets($fh)) {
                    list($id, $name, /*ascii name*/, /*alt name*/,
                        $latitude, $longitude, $featureClass, $featureCode,
                        $countryCode, /*alt country code*/,
                        $admin1Code, $admin2Code, $admin3Code, $admin4Code,
                        $population, /*elevation*/, /*digital elevation model*/,
                        $timezoneCode, /*modification date*/) =
                        explode("\t", $line);

                    if (!$place = $placeRepo->find((int)$id)) {
                        $place = new Entity\Place;
                        $place->setId($id);
                        $placeMap[$id] = $place;
                        $em->persist($place);
                    }
                    $place
                        ->setName($name)
                        ->setLatitude($latitude)
                        ->setLongitude($longitude)
                        ->setCountryCode($countryCode)
                        ->setAdmin1Code($admin1Code)
                        ->setAdmin2Code($admin2Code)
                        ->setAdmin3Code($admin3Code)
                        ->setAdmin4Code($admin4Code)
                        ->setPopulation($population);

                    $fullFeatureCode = "$featureClass.$featureCode";
                    if (isset($featureMap[$fullFeatureCode])) {
                        $feature = $featureMap[$fullFeatureCode];
                    } elseif (!$feature = $featureRepo->findByGeonameCode($fullFeatureCode)) {
                        $feature = new Entity\Feature;
                        $em->persist($feature);
                        $feature->setCode($featureCode);
                        if ($parent = $featureRepo->findOneBy(
                            array('code' => $featureClass))) {
                            $feature->setParent($parent);
                        }
                    }
                    $place->setFeature($feature);

                    if (!$timezone = $timezoneRepo->findOneBy(
                        array('code' => $timezoneCode))) {
                        $timezone = new Entity\Timezone;
                        $em->persist($timezone);
                        $timezone->setCode($timezoneCode);
                        if (isset($countryMap[$countryCode])) {
                            $timezone->setCountry($countryMap[$countryCode]);
                        } elseif ($country = $countryRepo->findOneBy(
                            array('iso2' => $countryCode))) {
                            $timezone->setCountry($country);
                        }
                    }
                    $place->setTimezone($timezone);

                    if ($featureClass != 'A'
                        || !in_array($featureCode, array('ADM1', 'ADM2', 'ADM3', 'ADM4'))) {
                        continue;
                    }

                    // determine parent feature
                    $parentFeatureCode = '';
                    $adminLevel = (int)substr($featureCode, -1);
                    while ($adminLevel >= 1) {
                        $parentLevel = $adminLevel - 1;
                        $parentVar = "admin{$parentLevel}Code";
                        $parentCode = $$parentVar;
                        if ($parentCode != '00') {
                            $parentFeatureCode = "ADM{$parentLevel}";
                            break;
                        }
                    }

                    // assemble criteria
                    $criteria = array(
                        'admin3Code' => '',
                        'admin2Code' => '',
                        'admin1Code' => '',
                        'countryCode' => '',
                    );
                    switch ($featureCode) {
                        // intentional fall throughs
                        case 'ADM4':
                            $criteria['admin3Code'] = $admin3Code;
                        case 'ADM3':
                            $criteria['admin2Code'] = $admin2Code;
                        case 'ADM2':
                            $criteria['admin1Code'] = $admin1Code;
                        case 'ADM1':
                            $criteria['countryCode'] = $countryCode;
                    }
                    if ($parentFeatureCode) {
                        $criteria['featureCode'] = $parentFeatureCode;
                        $criteria['featureClass'] = 'A';
                    }

                    if ($parent = $placeRepo->findPlace($criteria)) {
                        $place->setParent($parent);
                    }
                }
                fclose($fh);
                $files[] = "$source.lock";
            }
        }

        $sourceDir = "$tmpDir/update/place/delete";
        foreach (FileSystemManager::fileIterator($sourceDir) as $source) {
            if (!strpos($source, '.lock')
                && !strpos($source, '.done')
                && $fh = fopen($source, 'r')) {
                rename($source, "$source.lock");
                while ($line = fgets($fh)) {
                    list($id, /*name*/, /*comment*/) =
                        explode("\t", $line);
                    if ($place = $placeRepo->find((int)$id)) {
                        // handle associations manually
                        if ($country = $place->getCountry()) {
                            $country->setPlace(null);
                            foreach ($country->getTimezones() as $timezone) {
                                $timezone->setCountry(null);
                            }
                            $locales = $country->getLocales();
                            foreach ($locales as $locale) {
                                $locales->removeElement($locale);
                            }
                            $neighbours = $country->getNeighbours();
                            foreach ($neighbours as $neighbour) {
                                $neighbours->removeElement($neighbour);
                            }
                        }
                        foreach ($place->getChildren() as $child) {
                            $child->setParent(null);
                        }
                        foreach ($place->getAltNames() as $altName) {
                            $em->remove($altName);
                        }
                        foreach ($place->getCountries() as $country) {
                            $country->setContinent(null);
                        }
                        $em->remove($place);
                    }
                }
                fclose($fh);
                $files[] = "$source.lock";
            }
        }

        /* alt names */

        $cli->write('Alt names', 'section');
        $sourceDir = "$tmpDir/update/altName/modification";
        $languageMap = array();
        foreach (FileSystemManager::fileIterator($sourceDir) as $source) {
            if (!strpos($source, '.lock')
                && !strpos($source, '.done')
                && $fh = fopen($source, 'r')) {
                rename($source, "$source.lock");
                while ($line = fgets($fh)) {
                    list($id, $placeId, $languageCode, $name,
                        $isPreferred, $isShort, $isColloquial, $isHistoric) =
                        explode("\t", $line);

                    if (!$altName = $altNameRepo->find((int)$id)) {
                        $altName = new Entity\AltName;
                        $altName->setId($id);
                        $em->persist($altName);
                    }
                    $altName
                        ->setName($name)
                        ->setIsPreferred((bool)trim($isPreferred))
                        ->setIsShort((bool)trim($isShort))
                        ->setIsColloquial((bool)trim($isColloquial))
                        ->setIsHistoric((bool)trim($isHistoric));

                    if (isset($placeMap[$placeId])) {
                        $altName->setPlace($placeMap[$placeId]);
                        $em->persist($placeMap[$placeId]); // ?!
                    } elseif ($place = $placeRepo->find((int)$placeId)) {
                        $altName->setPlace($place);
                    }

                    if (isset($languageMap[$languageCode])) {
                        $altName->setLanguage($languageMap[$languageCode]);
                    } elseif ($language = $languageRepo->findLanguage($languageCode)) {
                        $altName->setLanguage($language);
                        $languageMap[$languageCode] = $language;
                    } else {
                        $altName->setLanguageOther($languageCode);
                    }
                }
                fclose($fh);
                $files[] = "$source.lock";
            }
        }

        $sourceDir = "$tmpDir/update/altName/delete";
        foreach (FileSystemManager::fileIterator($sourceDir) as $source) {
            if (!strpos($source, '.lock')
                && !strpos($source, '.done')
                && $fh = fopen($source, 'r')) {
                rename($source, "$source.lock");
                while ($line = fgets($fh)) {
                    list($id, /*name*/, /*comment*/) =
                        explode("\t", $line);
                    if ($altName = $altNameRepo->find((int)$id)) {
                        $em->remove($altName);
                    }
                }
                fclose($fh);
                $files[] = "$source.lock";
            }
        }

        $em->flush();
        foreach ($files as $file) {
            rename($file, substr($file, 0, -5) . '.done');
        }

        /* cleanup */

        $dt = new \DateTime;
        $today = $dt->format('Y-m-d');
        $dt->setTimestamp(strtotime('-1 day'));
        $yesterday = $dt->format('Y-m-d');

        $sourceDir = "$tmpDir/update";
        foreach (FileSystemManager::fileIterator($sourceDir) as $source) {
            if (strpos($source, '.done')
                && !strpos($source, $today)
                && !strpos($source, $yesterday)) {
                unlink($source);
            }
        }

        return $this;
    }

    /**
     * register cron job for auto-install and auto-update
     *
     * @return self
     */
    public function registerCron()
    {
        Cron::register(
            'geoname',
            $this->getCron(),
            array($this, 'run'),
            array()
        );

        return $this;
    }
}
