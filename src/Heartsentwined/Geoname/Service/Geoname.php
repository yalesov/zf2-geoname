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
        if ($meta->getIsLocked()) return $this;

        $meta->setIsLocked(true);
        switch ($meta->getStatus()) {
            case Repository\Meta::STATUS_INSTALL_DOWNLOAD:
                $this->installDownload();
                $meta->setStatus(
                    Repository\Meta::STATUS_INSTALL_PREPARE);
                break;
            case Repository\Meta::STATUS_INSTALL_PREPARE:
                $this->installPrepare();
                $meta->setStatus(
                    Repository\Meta::STATUS_INSTALL_LANGUAGE);
                break;
            case Repository\Meta::STATUS_INSTALL_LANGUAGE:
                $this->installLanguage();
                $meta->setStatus(
                    Repository\Meta::STATUS_INSTALL_FEATURE);
                break;
            case Repository\Meta::STATUS_INSTALL_FEATURE:
                $this->installFeature();
                $meta->setStatus(
                    Repository\Meta::STATUS_INSTALL_PLACE);
                break;
            case Repository\Meta::STATUS_INSTALL_PLACE:
                $this->installPlace();
                break;
            case Repository\Meta::STATUS_INSTALL_COUNTRY_CURRENCY_LOCALE:
                $this->installCountryCurrencyLocale();
                $meta->setStatus(
                    Repository\Meta::STATUS_INSTALL_TIMEZONE);
                break;
            case Repository\Meta::STATUS_INSTALL_TIMEZONE:
                $this->installTimezone();
                $meta->setStatus(
                    Repository\Meta::STATUS_INSTALL_NEIGHBOUR);
                break;
            case Repository\Meta::STATUS_INSTALL_NEIGHBOUR:
                $this->installNeighbour();
                $meta->setStatus(
                    Repository\Meta::STATUS_INSTALL_PLACE_TIMEZONE);
                break;
            case Repository\Meta::STATUS_INSTALL_PLACE_TIMEZONE:
                $this->installPlaceTimezone();
                break;
            case Repository\Meta::STATUS_INSTALL_HIERARCHY:
                $this->installHierarchy();
                break;
            case Repository\Meta::STATUS_INSTALL_ALT_NAME:
                $this->installAltName();
                break;
            case Repository\Meta::STATUS_INSTALL_CLEANUP:
                $this->installCleanup();
                $meta->setStatus(
                    Repository\Meta::STATUS_UPDATE);
                break;
            case Repository\Meta::STATUS_UPDATE:
                $this
                    ->updatePlaceModify()
                    ->updatePlaceDelete()
                    ->updateAltNameModify()
                    ->updateAltNameDelete()
                    ->updateCleanup();
                break;
        }
        $meta->setIsLocked(false);

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
        $repo =
            $em->getRepository('Heartsentwined\Geoname\Entity\Meta');
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
     * @param  string $src  source URL
     * @param  string $dest destination save path
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

        // geoname seems to update at UTC ~2am
        $latest = \DateTime::createFromFormat('U', strtotime('-1 day'));
        $cutoff = clone $latest;
        $cutoff->setTime(2, 0);
        if ($latest > $cutoff) {
            $date = $latest->format('Y-m-d');
        } else {
            $latest->setDate(
                $latest->format('Y'),
                $latest->format('n'),
                $latest->format('j')-1);
            $date = $latest->format('Y-m-d');
        }

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
     * crude implementation of a across-script file lock
     *
     * will append .lock to a target file, if it is not already .done / .lock
     *
     * @param  string $file target file
     * @return bool
     */
    public function getLock($file)
    {
        ArgValidator::assert($file, 'string');
        if (!file_exists($file)) return false;
        if (!strpos($file, '.done') && !strpos($file, '.lock')) {
            rename($file, "$file.lock");

            return true;
        }

        return false;
    }

    /**
     * crude implementation of a across-script "done" file marker
     *
     * will append .done to a target file, but only if it is currently .lock
     *
     * @param  string $file target file
     * @return bool
     */
    public function markDone($file)
    {
        ArgValidator::assert($file, 'string');
        $lockFile = $file . '.lock';
        if (!file_exists($lockFile)) return false;
        if (strpos($lockFile, '.lock')) {
            rename($lockFile, $file . '.done');

            return true;
        }

        return false;
    }

    /**
     * remove .lock and .done markers
     *
     * @param  string $dir target dir, will scan it and its children for files
     * @return self
     */
    public function resetFiles($dir)
    {
        ArgValidator::assert($dir, 'string');
        foreach (FileSystemManager::fileIterator($dir) as $file) {
            if (strpos($file, '.done') || strpos($file, '.lock')) {
                rename($file, substr($file, 0, strlen($file)-5));
            }
        }

        return $this;
    }

    /**
     * install: download source files
     *
     * @return self
     */
    public function installDownload()
    {
        $tmpDir = $this->getTmpDir();
        $cli = $this->getCli();

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
            $file = $tmpDir . '/' . basename($url);
            $this->downloadFile($url, $file);
        }

        return $this;
    }

    /**
     * install: prepare files
     *
     * - unzip
     * - split to smaller chunks
     *
     * @return self
     */
    public function installPrepare()
    {
        $tmpDir = $this->getTmpDir();
        $cli = $this->getCli();

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

    /**
     * install: language
     *
     * @return self
     */
    public function installLanguage()
    {
        $em = $this->getEm();

        $source = $this->getTmpDir() . '/iso-languagecodes.txt';
        if ($fh = fopen($source, 'r')) {
            fgets($fh); // skip first line
            while ($data = fgetcsv($fh, 0, "\t", "\0")) {
                list($iso3, $iso2, $iso1, $name) = $data;
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
        $em->flush();

        return $this;
    }

    /**
     * install: feature
     *
     * @return self
     */
    public function installFeature()
    {
        $em = $this->getEm();

        $source = $this->getTmpDir() . '/featureCodes_en.txt';
        if ($fh = fopen($source, 'r')) {
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
            while ($data = fgetcsv($fh, 0, "\t", "\0")) {
                list($rawCode, $description, $comment) = $data;

                if ($rawCode == 'null') continue;

                list($parentCode, $code) = explode('.', $rawCode);

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

        return $this;
    }

    /**
     * install: place (except timezone and hierarchy) [multi]
     *
     * @return self
     */
    public function installPlace()
    {
        $em = $this->getEm();
        $featureRepo =
            $em->getRepository('Heartsentwined\Geoname\Entity\Feature');

        $sourceDir = $this->getTmpDir() . '/allCountries';
        foreach (FileSystemManager::fileIterator($sourceDir) as $source) {
            if ($this->getLock($source) && $fh = fopen("$source.lock", 'r')) {
                $this->getCli()->write($source, 'module');
                while ($data = fgetcsv($fh, 0, "\t", "\0")) {
                    list($id, $name, /*ascii name*/, /*alt name*/,
                        $latitude, $longitude, $featureClass, $featureCode,
                        $countryCode, /*alt country code*/,
                        $admin1Code, $admin2Code, $admin3Code, $admin4Code,
                        $population, $elevation, $digiEleModel,
                        $timezoneCode, /*modification date*/) =
                        $data;

                    $place = new Entity\Place;
                    $place
                        ->setId($id)
                        ->setName($name)
                        ->setLatitude($latitude)
                        ->setLongitude($longitude)
                        ->setElevation($elevation)
                        ->setDigiEleModel($digiEleModel)
                        ->setCountryCode($countryCode)
                        ->setAdmin1Code($admin1Code)
                        ->setAdmin2Code($admin2Code)
                        ->setAdmin3Code($admin3Code)
                        ->setAdmin4Code($admin4Code)
                        ->setPopulation($population);
                    $em->persist($place);

                    $featureCode = "$featureClass.$featureCode";
                    if ($feature = $featureRepo
                            ->findByGeonameCode($featureCode)) {
                        $place->setFeature($feature);
                    }
                }
                fclose($fh);
                $em->flush();
                $this->markDone($source);

                return $this;
            }
        }
        $this->resetFiles($sourceDir);
        $this->getMeta()->setStatus(
            Repository\Meta::STATUS_INSTALL_COUNTRY_CURRENCY_LOCALE);

        return $this;
    }

    /**
     * install: country (except neighbour), currency, locale
     *
     * @return self
     */
    public function installCountryCurrencyLocale()
    {
        $em = $this->getEm();
        $placeRepo =
            $em->getRepository('Heartsentwined\Geoname\Entity\Place');
        $languageRepo =
            $em->getRepository('Heartsentwined\Geoname\Entity\Language');
        $currencyMap = array();
        $localeMap = array();
        $languageMap = array();
        $continentMap = array(
            'AF' => 6255146,
            'AS' => 6255147,
            'EU' => 6255148,
            'NA' => 6255149,
            'OC' => 6255151,
            'SA' => 6255150,
            'AN' => 6255152,
        );
        $source = $this->getTmpDir() . '/countryInfo.txt';
        if ($fh = fopen($source, 'r')) {
            while ($data = fgetcsv($fh, 0, "\t", "\0")) {
                if (substr(trim($data[0]), 0, 1) === '#') {
                    continue;
                }
                list($iso2, $iso3, $isoNum, /*fips*/, /*name*/,
                    $capital, $area, $population, $continentCode, $tld,
                    $currencyCode, $currencyName, $phone,
                    $postalCode, $postalCodeRegex, $localeCodes, $placeId,
                    $neighbours, /*equiv fips code*/) =
                    $data;
                if ($area == 'NA') $area = null;
                if ($population == 'NA') $population = null;
                $country = new Entity\Country;
                $em->persist($country);
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

                if ($place = $placeRepo->find((int) $placeId)) {
                    $country->setPlace($place);
                }
                if (isset($continentMap[$continentCode])) {
                    if ($continent = $placeRepo
                            ->find((int) $continentMap[$continentCode])) {
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
                foreach (array_unique(explode(',', $localeCodes))
                    as $localeCode) {
                    $localeCode = strtr($localeCode, '-', '_');
                    if (isset($localeMap[$localeCode])) {
                        $locale = $localeMap[$localeCode];
                    } else {
                        $locale = new Entity\Locale;
                        $em->persist($locale);
                        $localeMap[$localeCode] = $locale;
                        $locale->setCode($localeCode);
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
                    if ($count === 1) {
                        $country->setMainLocale($locale);
                    }
                    $country->addLocale($locale);
                    $count++;
                }
            }
            fclose($fh);
        }
        $em->flush();

        return $this;
    }

    /**
     * install: timezone
     *
     * @return self
     */
    public function installTimezone()
    {
        $em = $this->getEm();
        $countryRepo =
            $em->getRepository('Heartsentwined\Geoname\Entity\Country');

        $countryMap = array();
        $source = $this->getTmpDir() . '/timeZones.txt';
        if ($fh = fopen($source, 'r')) {
            fgets($fh); //skip header
            while ($data = fgetcsv($fh, 0, "\t", "\0")) {
                list($countryCode, $code,
                    $offsetJan, $offsetJul, $offset) =
                    $data;
                $timezone = new Entity\Timezone;
                $em->persist($timezone);
                $timezone
                    ->setCode($code)
                    ->setOffset($offset)
                    ->setOffsetJan($offsetJan)
                    ->setOffsetJul($offsetJul);
                if (isset($countryMap[$countryCode])) {
                    $timezone->setCountry($countryMap[$countryCode]);
                } elseif ($country = $countryRepo
                    ->findOneBy(array('iso2' => $countryCode))) {
                    $countryMap[$countryCode] = $country;
                    $timezone->setCountry($country);
                }
            }
            fclose($fh);
        }
        $em->flush();

        return $this;
    }

    /**
     * install: country neighbour
     *
     * @return self
     */
    public function installNeighbour()
    {
        $em = $this->getEm();
        $countryRepo =
            $em->getRepository('Heartsentwined\Geoname\Entity\Country');

        $countryMap = array();
        $source = $this->getTmpDir() . '/countryInfo.txt';
        if ($fh = fopen($source, 'r')) {
            while ($data = fgetcsv($fh, 0, "\t", "\0")) {
                if (substr(trim($data[0]), 0, 1) === '#') {
                    continue;
                }
                list($iso2, $iso3, $isoNum, /*fips*/, /*name*/,
                    $capital, $area, $population, $continentCode, $tld,
                    $currencyCode, $currencyName, $phone,
                    $postalCode, $postalCodeRegex, $localeCodes, $placeId,
                    $neighbours, /*equiv fips code*/) =
                    $data;
                if (isset($countryMap[$iso2])) {
                    $country = $countryMap[$iso2];
                } elseif ($country = $countryRepo
                    ->findOneBy(array('iso2' => $iso2))) {
                    $countryMap[$iso2] = $country;
                } else {
                    continue;
                }
                $curNeighbours = array();
                foreach ($country->getNeighbours() as $neighbour) {
                    $curNeighbours[] = $neighbour->getIso2();
                }
                foreach (explode(',', $neighbours) as $neighbourIso2) {
                    if (in_array($neighbourIso2, $curNeighbours)) {
                        continue;
                    }
                    if (isset($countryMap[$neighbourIso2])) {
                        $neighbour = $countryMap[$neighbourIso2];
                    } elseif ($neighbour = $countryRepo
                        ->findOneBy(array('iso2' => $neighbourIso2))) {
                        $countryMap[$neighbourIso2] = $neighbour;
                    } else {
                        continue;
                    }
                    $revCurNeighbours = array();
                    foreach ($neighbour->getNeighbours() as $neighbour2) {
                        $revCurNeighbours[] = $neighbour2->getIso2();
                    }
                    if (!in_array($neighbourIso2, $revCurNeighbours)) {
                        $country->addNeighbour($neighbour);
                    }
                }
            }
            fclose($fh);
        }
        $em->flush();

        return $this;
    }

    /**
     * install: place timezone [multi]
     *
     * @return self
     */
    public function installPlaceTimezone()
    {
        $em = $this->getEm();
        $placeRepo =
            $em->getRepository('Heartsentwined\Geoname\Entity\Place');
        $timezoneRepo =
            $em->getRepository('Heartsentwined\Geoname\Entity\Timezone');
        $countryRepo =
            $em->getRepository('Heartsentwined\Geoname\Entity\Country');

        $countryMap = array();
        $sourceDir = $this->getTmpDir() . '/allCountries';
        foreach (FileSystemManager::fileIterator($sourceDir) as $source) {
            if ($this->getLock($source) && $fh = fopen("$source.lock", 'r')) {
                $this->getCli()->write($source, 'module');
                while ($data = fgetcsv($fh, 0, "\t", "\0")) {
                    list($id, /*$name*/, /*ascii name*/, /*alt name*/,
                        /*$latitude*/, /*$longitude*/,
                        /*$featureClass*/, /*$featureCode*/,
                        $countryCode, /*alt country code*/, /*$admin1Code*/,
                        /*$admin2Code*/, /*$admin3Code*/, /*$admin4Code*/,
                        /*$population*/, /*$elevation*/, /*$digiEleModel*/,
                        $timezoneCode, /*modification date*/) =
                        $data;
                    if (!$place = $placeRepo->find((int) $id)) continue;
                    if (empty($timezoneCode)) continue;

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
                $this->markDone($source);

                return $this;
            }
        }
        $this->resetFiles($sourceDir);
        $this->getMeta()->setStatus(
            Repository\Meta::STATUS_INSTALL_HIERARCHY);

        return $this;
    }

    /**
     * install: place hierarchy [multi]
     *
     * @return self
     */
    public function installHierarchy()
    {
        $em = $this->getEm();
        $placeRepo =
            $em->getRepository('Heartsentwined\Geoname\Entity\Place');

        $sourceDir = $this->getTmpDir() . '/hierarchy';
        foreach (FileSystemManager::fileIterator($sourceDir) as $source) {
            if ($this->getLock($source) && $fh = fopen("$source.lock", 'r')) {
                $this->getCli()->write($source, 'module');
                while ($data = fgetcsv($fh, 0, "\t", "\0")) {
                    list($parentId, $childId, /*$type*/) = $data;

                    if (($parent = $placeRepo->find((int) $parentId))
                        && ($child = $placeRepo->find((int) $childId))) {
                        $child->setParent($parent);
                    }
                }
                fclose($fh);
                $em->flush();
                $this->markDone($source);

                return $this;
            }
        }
        $this->resetFiles($sourceDir);
        $this->getMeta()->setStatus(
            Repository\Meta::STATUS_INSTALL_ALT_NAME);

        return $this;
    }

    /**
     * install: alt name [multi]
     *
     * @return self
     */
    public function installAltName()
    {
        $em = $this->getEm();
        $placeRepo =
            $em->getRepository('Heartsentwined\Geoname\Entity\Place');
        $languageRepo =
            $em->getRepository('Heartsentwined\Geoname\Entity\Language');

        $languageMap = array();
        $sourceDir = $this->getTmpDir() . '/alternateNames';
        foreach (FileSystemManager::fileIterator($sourceDir) as $source) {
            if ($this->getLock($source) && $fh = fopen("$source.lock", 'r')) {
                $this->getCli()->write($source, 'module');
                while ($data = fgetcsv($fh, 0, "\t", "\0")) {
                    list($id, $placeId, $languageCode, $name,
                        $isPreferred, $isShort, $isColloquial, $isHistoric) =
                        $data;

                    $altName = new Entity\AltName;
                    $altName
                        ->setId($id)
                        ->setName($name)
                        ->setIsPreferred((bool) trim($isPreferred))
                        ->setIsShort((bool) trim($isShort))
                        ->setIsColloquial((bool) trim($isColloquial))
                        ->setIsHistoric((bool) trim($isHistoric));
                    $em->persist($altName);

                    if ($place = $placeRepo->find((int) $placeId)) {
                        $altName->setPlace($place);
                    }

                    if (isset($languageMap[$languageCode])) {
                        $altName->setLanguage($languageMap[$languageCode]);
                    } elseif ($language = $languageRepo
                        ->findLanguage($languageCode)) {
                        $altName->setLanguage($language);
                        $languageMap[$languageCode] = $language;
                    } else {
                        $altName->setLanguageOther($languageCode);
                    }
                }
                fclose($fh);
                $em->flush();
                $this->markDone($source);

                return $this;
            }
        }
        $this->resetFiles($sourceDir);
        $this->getMeta()->setStatus(
            Repository\Meta::STATUS_INSTALL_CLEANUP);

        return $this;
    }

    /**
     * install: cleanup
     *
     * - remove source files and dir, except updates
     *
     * @return self
     */
    public function installCleanup()
    {
        foreach (new \FilesystemIterator($this->getTmpDir()) as $dir) {
            if (!is_dir($dir)) {
                unlink($dir);
            } else {
                if (basename($dir) != 'update') {
                    FileSystemManager::rrmdir($dir);
                }
            }
        }

        return $this;
    }

    /**
     * update: place modification
     *
     * @return self
     */
    public function updatePlaceModify()
    {
        $em = $this->getEm();
        $placeRepo =
            $em->getRepository('Heartsentwined\Geoname\Entity\Place');
        $featureRepo =
            $em->getRepository('Heartsentwined\Geoname\Entity\Feature');
        $timezoneRepo =
            $em->getRepository('Heartsentwined\Geoname\Entity\Timezone');
        $countryRepo =
            $em->getRepository('Heartsentwined\Geoname\Entity\Country');

        $countryMap = array();
        $featureMap = array();
        $placeMap = array();
        foreach (FileSystemManager::fileIterator(
            $this->getTmpDir() . '/update/place/modification') as $source) {
            if ($this->getLock($source) && $fh = fopen("$source.lock", 'r')) {
                $this->getCli()->write($source, 'module');
                while ($data = fgetcsv($fh, 0, "\t", "\0")) {
                    list($id, $name, /*ascii name*/, /*alt name*/,
                        $latitude, $longitude, $featureClass, $featureCode,
                        $countryCode, /*alt country code*/,
                        $admin1Code, $admin2Code, $admin3Code, $admin4Code,
                        $population, $elevation, $digiEleModel,
                        $timezoneCode, /*modification date*/) =
                        $data;

                    if (!$place = $placeRepo->find((int) $id)) {
                        $place = new Entity\Place;
                        $place->setId($id);
                        $placeMap[$id] = $place;
                        $em->persist($place);
                    }
                    $place
                        ->setName($name)
                        ->setLatitude($latitude)
                        ->setLongitude($longitude)
                        ->setElevation($elevation)
                        ->setDigiEleModel($digiEleModel)
                        ->setCountryCode($countryCode)
                        ->setAdmin1Code($admin1Code)
                        ->setAdmin2Code($admin2Code)
                        ->setAdmin3Code($admin3Code)
                        ->setAdmin4Code($admin4Code)
                        ->setPopulation($population);

                    $fullFeatureCode = "$featureClass.$featureCode";
                    if (isset($featureMap[$fullFeatureCode])) {
                        $feature = $featureMap[$fullFeatureCode];
                    } elseif (!$feature = $featureRepo
                        ->findByGeonameCode($fullFeatureCode)) {
                        $feature = new Entity\Feature;
                        $em->persist($feature);
                        $feature->setCode($featureCode);
                        if (isset($featureMap[$featureClass])) {
                            $feature->setParent($featureMap[$featureClass]);
                        } elseif ($parent = $featureRepo->findOneBy(
                            array('code' => $featureClass))) {
                            $featureMap[$featureClass] = $parent;
                            $feature->setParent($parent);
                        }
                        $featureMap[$fullFeatureCode] = $feature;
                    }
                    $place->setFeature($feature);

                    if (!empty($timezoneCode)) {
                        if (!$timezone = $timezoneRepo->findOneBy(
                            array('code' => $timezoneCode))) {
                            $timezone = new Entity\Timezone;
                            $em->persist($timezone);
                            $timezone->setCode($timezoneCode);
                            if (isset($countryMap[$countryCode])) {
                                $timezone
                                    ->setCountry($countryMap[$countryCode]);
                            } elseif ($country = $countryRepo->findOneBy(
                                array('iso2' => $countryCode))) {
                                $timezone->setCountry($country);
                            }
                        }
                        $place->setTimezone($timezone);
                    }

                    // hierarchy

                    $em->flush();   // in case this entity will become
                                    // dependency for the next entity

                    if ($featureClass !== 'A'
                        || !in_array($featureCode,
                            array('ADM1', 'ADM2', 'ADM3', 'ADM4'))) {
                        continue;
                    }

                    // determine parent feature
                    $parentFeatureCode = '';
                    $adminLevel = (int) substr($featureCode, -1);
                    while ($adminLevel > 1) {
                        $parentLevel = $adminLevel - 1;
                        $parentVar = "admin{$parentLevel}Code";
                        $parentCode = $$parentVar;
                        if ($parentCode != '00') {
                            $parentFeatureCode = "ADM{$parentLevel}";
                            break;
                        }
                        $adminLevel--;
                    }

                    // assemble criteria
                    $criteria = array(
                        'admin3Code' => '',
                        'admin2Code' => '',
                        'admin1Code' => '',
                        'countryCode' => '',
                    );
                    switch ($parentFeatureCode) {
                        // intentional fall throughs
                        case 'ADM3':
                            $criteria['admin3Code'] = $admin3Code;
                        case 'ADM2':
                            $criteria['admin2Code'] = $admin2Code;
                        case 'ADM1':
                            $criteria['admin1Code'] = $admin1Code;
                        default:
                            $criteria['countryCode'] = $countryCode;
                    }
                    if ($parentFeatureCode) {
                        $criteria['featureCode'] = $parentFeatureCode;
                        $criteria['featureClass'] = 'A';
                    }
                    if ($places = $placeRepo->findPlace($criteria, 1)) {
                        $place->setParent(current($places));
                    }
                }
                fclose($fh);
                $this->markDone($source);
            }
        }
        $em->flush();

        return $this;
    }

    /**
     * update: place delete
     *
     * BC: will not actually delete the place, only mark as deprecated
     *
     * @return self
     */
    public function updatePlaceDelete()
    {
        $em = $this->getEm();
        $placeRepo =
            $em->getRepository('Heartsentwined\Geoname\Entity\Place');
        foreach (FileSystemManager::fileIterator(
            $this->getTmpDir() . '/update/place/delete') as $source) {
            if ($this->getLock($source) && $fh = fopen("$source.lock", 'r')) {
                $this->getCli()->write($source, 'module');
                while ($data = fgetcsv($fh, 0, "\t", "\0")) {
                    list($id, /*name*/, /*comment*/) = $data;
                    if ($place = $placeRepo->find((int) $id)) {
                        $place->setIsDeprecated(true);
                    }
                }
                fclose($fh);
                $this->markDone($source);
            }
        }
        $em->flush();

        return $this;
    }

    /**
     * update: alt name modification
     *
     * @return self
     */
    public function updateAltNameModify()
    {
        $em = $this->getEm();
        $altNameRepo =
            $em->getRepository('Heartsentwined\Geoname\Entity\AltName');
        $placeRepo =
            $em->getRepository('Heartsentwined\Geoname\Entity\Place');
        $languageRepo =
            $em->getRepository('Heartsentwined\Geoname\Entity\Language');

        $placeMap = array();
        $languageMap = array();
        foreach (FileSystemManager::fileIterator(
            $this->getTmpDir() . '/update/altName/modification') as $source) {
            if ($this->getLock($source) && $fh = fopen("$source.lock", 'r')) {
                $this->getCli()->write($source, 'module');
                while ($data = fgetcsv($fh, 0, "\t", "\0")) {
                    list($id, $placeId, $languageCode, $name,
                        $isPreferred, $isShort, $isColloquial, $isHistoric) =
                        $data;

                    if (!$altName = $altNameRepo->find((int) $id)) {
                        $altName = new Entity\AltName;
                        $altName->setId($id);
                        $em->persist($altName);
                    }
                    $altName
                        ->setName($name)
                        ->setIsPreferred((bool) trim($isPreferred))
                        ->setIsShort((bool) trim($isShort))
                        ->setIsColloquial((bool) trim($isColloquial))
                        ->setIsHistoric((bool) trim($isHistoric));

                    if (isset($placeMap[$placeId])) {
                        $altName->setPlace($placeMap[$placeId]);
                    } elseif ($place = $placeRepo->find((int) $placeId)) {
                        $altName->setPlace($place);
                        $placeMap[$placeId] = $place;
                    }

                    if (isset($languageMap[$languageCode])) {
                        $altName->setLanguage($languageMap[$languageCode]);
                    } elseif ($language = $languageRepo
                        ->findLanguage($languageCode)) {
                        $altName->setLanguage($language);
                        $languageMap[$languageCode] = $language;
                    } else {
                        $altName->setLanguageOther($languageCode);
                    }
                }
                fclose($fh);
                $this->markDone($source);
            }
        }
        $em->flush();

        return $this;
    }

    /**
     * update: alt name delete
     *
     * BC: will not actually delete the alt name, only mark as deprecated
     *
     * @return self
     */
    public function updateAltNameDelete()
    {
        $em = $this->getEm();
        $altNameRepo =
            $em->getRepository('Heartsentwined\Geoname\Entity\AltName');
        foreach (FileSystemManager::fileIterator(
            $this->getTmpDir() . '/update/altName/delete') as $source) {
            if ($this->getLock($source) && $fh = fopen("$source.lock", 'r')) {
                $this->getCli()->write($source, 'module');
                while ($data = fgetcsv($fh, 0, "\t", "\0")) {
                    list($id, /*name*/, /*comment*/) = $data;
                    if ($altName = $altNameRepo->find((int) $id)) {
                        $altName->setIsDeprecated(true);
                    }
                }
                fclose($fh);
                $this->markDone($source);
            }
        }
        $em->flush();

        return $this;
    }

    /**
     * update: cleanup
     *
     * - delete .done update files,
     *   but preserve recent two days as downloaded mark
     *
     * @return self
     */
    public function updateCleanup()
    {
        // geoname seems to update at UTC ~2am
        $latest = \DateTime::createFromFormat('U', strtotime('-1 day'));
        $cutoff = clone $latest;
        $cutoff->setTime(2, 0);
        if ($latest > $cutoff) {
            $latestDate = $latest->format('Y-m-d');
            $before = clone $latest;
            $before->setDate(
                $before->format('Y'),
                $before->format('n'),
                $before->format('j')-1);
            $beforeDate = $before->format('Y-m-d');
        } else {
            $latest->setDate(
                $latest->format('Y'),
                $latest->format('n'),
                $latest->format('j')-1);
            $latestDate = $latest->format('Y-m-d');
            $before = clone $latest;
            $before->setDate(
                $before->format('Y'),
                $before->format('n'),
                $before->format('j')-1);
            $beforeDate = $before->format('Y-m-d');
        }

        foreach (FileSystemManager::fileIterator(
            $this->getTmpDir() . '/update') as $source) {
            if (strpos($source, '.done')
                && !strpos($source, $latestDate)
                && !strpos($source, $beforeDate)) {
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
