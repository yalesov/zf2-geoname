<?php
namespace Heartsentwined\Geoname\Test;

use Heartsentwined\FileSystemManager\FileSystemManager;
use Heartsentwined\Geoname\Entity;
use Heartsentwined\Geoname\Repository;
use Heartsentwined\Geoname\Service\Geoname;
use Heartsentwined\Phpunit\Testcase\Doctrine as DoctrineTestcase;

class GeonameTest extends DoctrineTestcase
{
    public function setUp()
    {
        $this
            ->setBootstrap(__DIR__ . '/../../../../bootstrap.php')
            ->setEmAlias('doctrine.entitymanager.orm_default')
            ->setTmpDir('tmp');
        parent::setUp();

        if (!is_dir('tmp/geoname')) mkdir('tmp/geoname', 0755, true);
        $this->geoname = $this->sm->get('geoname')
            ->setEm($this->em)
            ->setTmpDir('tmp/geoname');
    }

    public function tearDown()
    {
        unset($this->geoname);
        FileSystemManager::rrmdir('tmp/goename');
        parent::tearDown();
    }

    public function getCliDummy()
    {
        $cli = $this->sm->get('Heartsentwined\Cli\Cli')
            ->setTemplates(array(
                'section' => array(
                    'template'  => '## %s ##',
                    'color'     => 'YELLOW',
                ),
                'task' => array(
                    'template'  => '- %s -',
                    'color'     => 'BLUE',
                ),
                'module' => array(
                    'template'  => '[ %s ]',
                    'color'     => 'GREEN',
                ),
            ));
        return $cli;
    }

    public function testGetMeta()
    {
        $metaRepo = $this->em
            ->getRepository('Heartsentwined\Geoname\Entity\Meta');

        // no meta in the beginning
        $this->assertCount(0, $metaRepo->findAll());

        // no meta -> create one, with 'install_download' status
        $meta = $this->geoname->getMeta();
        $this->assertSame(Repository\Meta::STATUS_INSTALL_DOWNLOAD,
            $meta->getStatus());
        $this->assertCount(1, $metaRepo->findAll());

        // get again -> retrieve same meta
        $meta = $this->geoname->getMeta();
        $this->assertSame(Repository\Meta::STATUS_INSTALL_DOWNLOAD,
            $meta->getStatus());
        $this->assertCount(1, $metaRepo->findAll());

        // clean the meta -> get = create another one
        $this->em->remove($meta);
        $this->em->flush();
        $meta = $this->geoname->getMeta();
        $this->assertSame(Repository\Meta::STATUS_INSTALL_DOWNLOAD,
            $meta->getStatus());
        $this->assertCount(1, $metaRepo->findAll());

        // change meta status -> get meta -> don't update it
        $meta->setStatus(Repository\Meta::STATUS_UPDATE);
        $this->em->flush();
        $meta = $this->geoname->getMeta();
        $this->assertSame(Repository\Meta::STATUS_UPDATE,
            $meta->getStatus());
        $this->assertCount(1, $metaRepo->findAll());
    }
    public function testdownloadFile()
    {
        // download file and save to tmp/foo
        $this->geoname->downloadFile('http://www.google.com', 'tmp/foo');
        $this->assertFileExists('tmp/foo');
        $this->assertFalse(file_exists('tmp/foo.lock'));
        $this->assertFalse(file_exists('tmp/foo.done'));

        // don't download again
        $mtime = filemtime('tmp/foo');
        $this->geoname->downloadFile('http://www.google.com', 'tmp/foo');
        $this->assertFileExists('tmp/foo');
        $this->assertFalse(file_exists('tmp/foo.lock'));
        $this->assertFalse(file_exists('tmp/foo.done'));
        $this->assertSame($mtime, filemtime('tmp/foo'));

        // don't download if .lock
        rename('tmp/foo', 'tmp/foo.lock');
        $this->geoname->downloadFile('http://www.google.com', 'tmp/foo');
        $this->assertFalse(file_exists('tmp/foo'));
        $this->assertFileExists('tmp/foo.lock');
        $this->assertFalse(file_exists('tmp/foo.done'));

        // don't download if .done
        rename('tmp/foo.lock', 'tmp/foo.done');
        $this->geoname->downloadFile('http://www.google.com', 'tmp/foo');
        $this->assertFalse(file_exists('tmp/foo'));
        $this->assertFalse(file_exists('tmp/foo.lock'));
        $this->assertFileExists('tmp/foo.done');

        // don't download if 404
        $this->geoname->downloadFile('http://example.test', 'tmp/bar');
        $this->assertFalse(file_exists('tmp/bar'));
    }

    public function testDownloadUpdate()
    {
        $now = \DateTime::createFromFormat('U', strtotime('-1 day'));
        $date = $now->format('Y-m-d');
        foreach(array(
            "http://download.geonames.org/export/dump/modifications-$date.txt",
            "http://download.geonames.org/export/dump/deletes-$date.txt",
            "http://download.geonames.org/export/dump/alternateNamesModifications-$date.txt",
            "http://download.geonames.org/export/dump/alternateNamesDeletes-$date.txt",
        ) as $dir => $url) {
            $headers = get_headers($url);
            $this->assertSame(0, preg_match('/40\d/', $headers[0]));
        }

        $geoname = $this->getMock(
            'Heartsentwined\Geoname\Service\Geoname',
            array('downloadFile'));
        $geoname
            ->expects($this->exactly(4))
            ->method('downloadFile');
        $geoname
            ->setCli($this->getCliDummy())
            ->setEm($this->em)
            ->setTmpDir('tmp/geoname')
            ->downloadUpdate();
    }

    public function testInstallDownload()
    {
        foreach(array(
            'http://download.geonames.org/export/dump/countryInfo.txt',
            'http://download.geonames.org/export/dump/featureCodes_en.txt',
            'http://download.geonames.org/export/dump/timeZones.txt',
            'http://download.geonames.org/export/dump/hierarchy.zip',
            'http://download.geonames.org/export/dump/alternateNames.zip',
            'http://download.geonames.org/export/dump/allCountries.zip',
        ) as $dir => $url) {
            $headers = get_headers($url);
            $this->assertSame(0, preg_match('/40\d/', $headers[0]));
        }

        $geoname = $this->getMock(
            'Heartsentwined\Geoname\Service\Geoname',
            array('downloadFile'));
        $geoname
            ->expects($this->exactly(6))
            ->method('downloadFile');
        $geoname
            ->setCli($this->getCliDummy())
            ->setEm($this->em)
            ->setTmpDir('tmp/geoname')
            ->installDownload();
    }

    public function testInstallPrepare()
    {
        $fh = fopen('tmp/geoname/allCountries.txt', 'a+');
        for ($i=1; $i<=50001; $i++) {
            fwrite($fh, "a\n");
        }
        fclose($fh);
        touch('tmp/geoname/alternateNames.txt');
        touch('tmp/geoname/hierarchy.txt');

        // dummy zip files
        touch('tmp/geoname/foo');
        foreach (array(
            'tmp/geoname/allCountries.zip',
            'tmp/geoname/alternateNames.zip',
            'tmp/geoname/hierarchy.zip',
        ) as $file) {
            $zip = new \ZipArchive();
            $zip->open($file, \ZipArchive::CREATE);
            $zip->addFile('tmp/geoname/foo');
            $zip->close();
            $this->assertFileExists($file);
        }

        $this->geoname->installPrepare();
        $this->assertCount(3,
            FileSystemManager::fileIterator('tmp/geoname/allCountries'));
        $this->assertFileExists('tmp/geoname/allCountries/1');
        $this->assertFileExists('tmp/geoname/allCountries/25001');
        $this->assertFileExists('tmp/geoname/allCountries/50001');

        foreach (array(
            'tmp/geoname/allCountries/1',
            'tmp/geoname/allCountries/25001',
        ) as $file) {
            $lineCount = 0;
            $fh = fopen($file, 'r');
            while ($line = fgets($fh)) {
                if (in_array($lineCount % 25000, array(0, 1, 24999))) {
                    $this->assertSame("a\n", $line);
                }
                $lineCount++;
            }
            fclose($fh);
            $this->assertSame(25000, $lineCount);
        }

        $lineCount = 0;
        $fh = fopen('tmp/geoname/allCountries/50001', 'r');
        while ($line = fgets($fh)) {
            if (in_array($lineCount % 25000, array(0, 1, 24999))) {
                $this->assertSame("a\n", $line);
            }
            $lineCount++;
        }
        fclose($fh);
        $this->assertSame(1, $lineCount);
    }

    public function testInstallLanguage()
    {
        $languageRepo =
            $this->em->getRepository('Heartsentwined\Geoname\Entity\Language');
        $fh = fopen('tmp/geoname/iso-languagecodes.txt', 'a+');
        fwrite($fh, "\n");
        fwrite($fh, "foo\tfo\tf\tFoo language\n");
        fwrite($fh, "bar\tba\tb\tBar language\n");
        fclose($fh);

        $this->geoname->installLanguage();

        $this->assertCount(2, $languageRepo->findAll());
        $foo = $languageRepo->find(1);
        $this->assertNotEmpty($foo);
        $this->assertSame('foo', $foo->getIso3());
        $this->assertSame('fo', $foo->getIso2());
        $this->assertSame('f', $foo->getIso1());
        $this->assertSame('Foo language', $foo->getName());
    }

    public function testInstallFeature()
    {
        $featureRepo =
            $this->em->getRepository('Heartsentwined\Geoname\Entity\Feature');
        $fh = fopen('tmp/geoname/featureCodes_en.txt', 'a+');
        fwrite($fh, "A.FOO\tadmin foo\tadmin foo comment\n");
        fwrite($fh, "V.FOO\tforest foo\t\n");
        fwrite($fh, "null\t\t\n");
        fclose($fh);

        $this->geoname->installFeature();

        $this->assertCount(4, $featureRepo->findAll());
        $admin = $featureRepo->findOneBy(array('code' => 'A'));
        $this->assertNotEmpty($admin);
        $this->assertSame('A', $admin->getCode());
        $this->assertSame('country, state, region', $admin->getDescription());
        $this->assertEmpty($admin->getComment());
        $this->assertEmpty($admin->getParent());

        $fooAdmin = $featureRepo->findOneBy(array('description' => 'admin foo'));
        $this->assertNotEmpty($fooAdmin);
        $this->assertSame('FOO', $fooAdmin->getCode());
        $this->assertSame('admin foo', $fooAdmin->getDescription());
        $this->assertSame('admin foo comment', $fooAdmin->getComment());
        $this->assertSame($admin, $fooAdmin->getParent());

        $fooForest = $featureRepo->findOneBy(array('description' => 'forest foo'));
        $this->assertNotEmpty($fooForest);
        $this->assertSame('FOO', $fooForest->getCode());
        $this->assertSame('forest foo', $fooForest->getDescription());
        $this->assertEmpty($fooForest->getComment());
        $this->assertSame(
            $featureRepo->findOneBy(array('code' => 'V')),
            $fooForest->getParent());
    }

    public function testInstallPlace()
    {
        $placeRepo =
            $this->em->getRepository('Heartsentwined\Geoname\Entity\Place');

        $admin = new Entity\Feature;
        $this->em->persist($admin);
        $admin
            ->setCode('A')
            ->setDescription('')
            ->setComment('');
        $bar = new Entity\Feature;
        $this->em->persist($bar);
        $bar
            ->setCode('BAR')
            ->setDescription('')
            ->setComment('')
            ->setParent($admin);
        $this->em->flush();

        mkdir('tmp/geoname/allCountries');
        $fh = fopen('tmp/geoname/allCountries/1', 'a+');
        fwrite($fh,
            '1'                 // id
            . "\tfoo placé 早"  // name - with funny chars
            . "\tfoo ascii"     // ascii name
            . "\tfoo alt"       // alt name
            . "\t50.1"          // latitude
            . "\t100.2"         // longitude
            . "\tA"             // feature class
            . "\tBAR"           // feature code
            . "\tAB"            // country code
            . "\tAC"            // alt country code
            . "\tadmin1"        // admin 1 code
            . "\tadmin2"        // admin 2 code
            . "\tadmin3"        // admin 3 code
            . "\tadmin4"        // admin 4 code
            . "\t100000"        // population
            . "\t1000"          // elevation
            . "\t2000"          // digital elevation model
            . "\tAsia/Kabul"    // timezone code
            . "\t2012-01-01"    // modification date
            . "\n"
        );
        fwrite($fh,
            '3'                 // non-sequential ID
            . "\tbar placé 早"
            . "\t"
            . "\tfoo,bar"       // comma-separated alt names (no use anyway)
            . "\t"
            . "\t"
            . "\tA"
            . "\tBAZ"           // non-existent feature
            . "\t"
            . "\t"
            . "\t"
            . "\t"
            . "\t"
            . "\t"
            . "\t"
            . "\t"
            . "\t"
            . "\t"
            . "\t"
            . "\n"
        );
        fclose($fh);

        $this->geoname->installPlace();

        $this->assertCount(2, $placeRepo->findAll());

        $foo = $placeRepo->find(1);
        $this->assertNotEmpty($foo);
        $this->assertSame('foo placé 早', $foo->getName());
        $this->assertSame('50.1', $foo->getLatitude());
        $this->assertSame('100.2', $foo->getLongitude());
        $this->assertSame('1000', $foo->getElevation());
        $this->assertSame('2000', $foo->getDigiEleModel());
        $this->assertSame('AB', $foo->getCountryCode());
        $this->assertSame('admin1', $foo->getAdmin1Code());
        $this->assertSame('admin2', $foo->getAdmin2Code());
        $this->assertSame('admin3', $foo->getAdmin3Code());
        $this->assertSame('admin4', $foo->getAdmin4Code());
        $this->assertSame('100000', $foo->getPopulation());
        $this->assertSame($bar, $foo->getFeature());

        $this->assertEmpty($placeRepo->find(2));

        $bar = $placeRepo->find(3);
        $this->assertNotEmpty($bar);
        $this->assertSame('bar placé 早', $bar->getName());
        $this->assertEmpty($bar->getLatitude());
        $this->assertEmpty($bar->getLongitude());
        $this->assertEmpty($bar->getElevation());
        $this->assertEmpty($bar->getDigiEleModel());
        $this->assertEmpty($bar->getCountryCode());
        $this->assertEmpty($bar->getAdmin1Code());
        $this->assertEmpty($bar->getAdmin2Code());
        $this->assertEmpty($bar->getAdmin3Code());
        $this->assertEmpty($bar->getAdmin4Code());
        $this->assertEmpty($bar->getPopulation());
        $this->assertEmpty($bar->getFeature());

        $this->assertFalse(file_exists('tmp/geoname/allCountries/1'));
        $this->assertFalse(file_exists('tmp/geoname/allCountries/1.lock'));
        $this->assertFileExists('tmp/geoname/allCountries/1.done');

        // make two more files for install to process - test lock and done
        touch('tmp/geoname/allCountries/2');
        touch('tmp/geoname/allCountries/3');

        $this->assertFalse(file_exists('tmp/geoname/allCountries/1'));
        $this->assertFalse(file_exists('tmp/geoname/allCountries/1.lock'));
        $this->assertFileExists('tmp/geoname/allCountries/1.done');
        $this->assertFileExists('tmp/geoname/allCountries/2');
        $this->assertFalse(file_exists('tmp/geoname/allCountries/2.lock'));
        $this->assertFalse(file_exists('tmp/geoname/allCountries/2.done'));
        $this->assertFileExists('tmp/geoname/allCountries/3');
        $this->assertFalse(file_exists('tmp/geoname/allCountries/3.lock'));
        $this->assertFalse(file_exists('tmp/geoname/allCountries/3.done'));

        $this->geoname->installPlace();
        $this->assertFalse(file_exists('tmp/geoname/allCountries/1'));
        $this->assertFalse(file_exists('tmp/geoname/allCountries/1.lock'));
        $this->assertFileExists('tmp/geoname/allCountries/1.done');
        $this->assertFalse(file_exists('tmp/geoname/allCountries/2'));
        $this->assertFalse(file_exists('tmp/geoname/allCountries/2.lock'));
        $this->assertFileExists('tmp/geoname/allCountries/2.done');
        $this->assertFileExists('tmp/geoname/allCountries/3');
        $this->assertFalse(file_exists('tmp/geoname/allCountries/3.lock'));
        $this->assertFalse(file_exists('tmp/geoname/allCountries/3.done'));

        $this->geoname->installPlace();
        $this->assertFalse(file_exists('tmp/geoname/allCountries/1'));
        $this->assertFalse(file_exists('tmp/geoname/allCountries/1.lock'));
        $this->assertFileExists('tmp/geoname/allCountries/1.done');
        $this->assertFalse(file_exists('tmp/geoname/allCountries/2'));
        $this->assertFalse(file_exists('tmp/geoname/allCountries/2.lock'));
        $this->assertFileExists('tmp/geoname/allCountries/2.done');
        $this->assertFalse(file_exists('tmp/geoname/allCountries/3'));
        $this->assertFalse(file_exists('tmp/geoname/allCountries/3.lock'));
        $this->assertFileExists('tmp/geoname/allCountries/3.done');

        // TODO test meta change
    }

    public function testInstallCountryCurrencyLocale()
    {
        $countryRepo =
            $this->em->getRepository('Heartsentwined\Geoname\Entity\Country');
        $currencyRepo =
            $this->em->getRepository('Heartsentwined\Geoname\Entity\Currency');
        $localeRepo =
            $this->em->getRepository('Heartsentwined\Geoname\Entity\Locale');

        // dummy place
        $place1 = new Entity\Place;
        $place1->setId(1);
        $this->em->persist($place1);
        $place2 = new Entity\Place;
        $place2->setId(2);
        $this->em->persist($place2);
        $place3 = new Entity\Place;
        $place3->setId(3);
        $this->em->persist($place3);
        // AF continent
        $africa = new Entity\Place;
        $africa->setId(6255146);
        $this->em->persist($africa);
        // dummy language
        $enLang = new Entity\Language;
        $this->em->persist($enLang);
        $enLang->setIso2('en');
        $esLang = new Entity\Language;
        $this->em->persist($esLang);
        $esLang->setIso2('es');
        $bzLang = new Entity\Language;
        $this->em->persist($bzLang);
        $bzLang->setIso2('bz');

        $this->em->flush();

        $fh = fopen('tmp/geoname/countryInfo.txt', 'a+');
        fwrite($fh, "# foo\n"); // a comment line
        fwrite($fh,
            'FO'                // iso 2
            . "\tFOO"           // iso 3
            . "\tisonum"        // iso numeric
            . "\tfips"          // FIPS code
            . "\tfoo country"   // country name
            . "\tfoo capital"   // capital
            . "\t1000"          // area
            . "\t100000"        // population
            . "\tAF"            // continent code
            . "\t.fo"           // TLD
            . "\tFOD"           // currency code
            . "\tfoo dollar"    // currency name
            . "\t123"           // phone (calling code?)
            . "\tFO###"         // postal code
            . "\tFO[\d]{3}"     // postal code regex
            . "\ten-FO,es"      // locale codes
            . "\t1"             // place ID
            . "\tBA"            // neighbour
            . "\tequiv fips"    // equivalent FIPS code
            . "\n"
        );
        fwrite($fh,
            'BA'                // iso 2
            . "\t"
            . "\t"
            . "\t"
            . "\t"
            . "\t"
            . "\t"
            . "\t"
            . "\t"
            . "\t"
            . "\tFOD"           // same currency
            . "\tfoo dollar"    // same currency
            . "\t"
            . "\t"
            . "\t"
            . "\tes"            // single locale code; same locale
            . "\t2"             // place ID
            . "\t"
            . "\t"
            . "\n"
        );
        fwrite($fh,
            'BZ'                // iso 2
            . "\t"
            . "\t"
            . "\t"
            . "\t"
            . "\t"
            . "\t"
            . "\t"
            . "\t"
            . "\t"
            . "\tBZD"           // new currency
            . "\tbaz dollar"    // new currency
            . "\t"
            . "\t"
            . "\t"
            . "\tbz"            // new locale
            . "\t3"             // place ID
            . "\t"
            . "\t"
            . "\n"
        );

        $this->geoname->installCountryCurrencyLocale();

        $this->assertCount(2, $currencyRepo->findAll());
        $fod = $currencyRepo->findOneBy(array('code' => 'FOD'));
        $this->assertNotEmpty($fod);
        $this->assertSame('FOD', $fod->getCode());
        $this->assertSame('foo dollar', $fod->getName());
        $bzd = $currencyRepo->findOneBy(array('code' => 'BZD'));
        $this->assertNotEmpty($bzd);
        $this->assertSame('BZD', $bzd->getCode());
        $this->assertSame('baz dollar', $bzd->getName());

        $this->assertCount(3, $localeRepo->findAll());
        $enFo = $localeRepo->findoneBy(array('code' => 'en_FO'));
        $this->assertNotEmpty($enFo);
        $es = $localeRepo->findoneBy(array('code' => 'es'));
        $this->assertNotEmpty($es);
        $bz = $localeRepo->findoneBy(array('code' => 'bz'));
        $this->assertNotEmpty($bz);

        $foo = $countryRepo->find(1);
        $this->assertNotEmpty($foo);
        $this->assertSame('FO', $foo->getIso2());
        $this->assertSame('FOO', $foo->getIso3());
        $this->assertSame('isonum', $foo->getIsoNum());
        $this->assertSame('foo capital', $foo->getCapital());
        $this->assertSame('1000', $foo->getArea());
        $this->assertSame('100000', $foo->getPopulation());
        $this->assertSame('.fo', $foo->getTld());
        $this->assertSame('123', $foo->getPhone());
        $this->assertSame('FO###', $foo->getPostalCode());
        $this->assertSame('FO[\d]{3}', $foo->getPostalCodeRegex());
        $this->assertSame($place1, $foo->getPlace());
        $this->assertSame($africa, $foo->getContinent());
        $this->assertSame($fod, $foo->getCurrency());
        $this->assertSame($enFo, $foo->getMainLocale());
        $expectedLocales = array($enFo, $es);
        $actualLocales = array();
        foreach ($foo->getLocales() as $locale) {
            $actualLocales[] = $locale;
        }
        sort($expectedLocales);
        sort($actualLocales);
        $this->assertSame($expectedLocales, $actualLocales);

        $bar = $countryRepo->find(2);
        $this->assertNotEmpty($bar);
        $this->assertSame('BA', $bar->getIso2());
        $this->assertEmpty($bar->getIso3());
        $this->assertEmpty($bar->getIsoNum());
        $this->assertEmpty($bar->getCapital());
        $this->assertEmpty($bar->getArea());
        $this->assertEmpty($bar->getPopulation());
        $this->assertEmpty($bar->getTld());
        $this->assertEmpty($bar->getPhone());
        $this->assertEmpty($bar->getPostalCode());
        $this->assertEmpty($bar->getPostalCodeRegex());
        $this->assertSame($place2, $bar->getPlace());
        $this->assertEmpty($bar->getContinent());
        $this->assertSame($fod, $bar->getCurrency());
        $this->assertSame($es, $bar->getMainLocale());
        $expectedLocales = array($es);
        $actualLocales = array();
        foreach ($bar->getLocales() as $locale) {
            $actualLocales[] = $locale;
        }
        $this->assertSame($expectedLocales, $actualLocales);

        $baz = $countryRepo->find(3);
        $this->assertNotEmpty($baz);
        $this->assertSame('BZ', $baz->getIso2());
        $this->assertEmpty($baz->getIso3());
        $this->assertEmpty($baz->getIsoNum());
        $this->assertEmpty($baz->getCapital());
        $this->assertEmpty($baz->getArea());
        $this->assertEmpty($baz->getPopulation());
        $this->assertEmpty($baz->getTld());
        $this->assertEmpty($baz->getPhone());
        $this->assertEmpty($baz->getPostalCode());
        $this->assertEmpty($baz->getPostalCodeRegex());
        $this->assertSame($place3, $baz->getPlace());
        $this->assertEmpty($baz->getContinent());
        $this->assertSame($bzd, $baz->getCurrency());
        $this->assertSame($bz, $baz->getMainLocale());
        $expectedLocales = array($bz);
        $actualLocales = array();
        foreach ($baz->getLocales() as $locale) {
            $actualLocales[] = $locale;
        }
        $this->assertSame($expectedLocales, $actualLocales);
    }

    public function testInstallTimezone()
    {
        $timezoneRepo =
            $this->em->getRepository('Heartsentwined\Geoname\Entity\Timezone');

        $fooCountry = new Entity\Country;
        $this->em->persist($fooCountry);
        $fooCountry->setIso2('FO');
        $barCountry = new Entity\Country;
        $this->em->persist($barCountry);
        $barCountry->setIso2('BA');
        $this->em->flush();

        $fh = fopen('tmp/geoname/timeZones.txt', 'a+');
        fwrite($fh, "\n");
        fwrite($fh, "FO\tAfrica/Foo\t1.0\t-1.0\t1.0\n");
        fwrite($fh, "BA\tAfrica/Bar_Baz\t2.25\t-2.5\t0.0\n");
        fclose($fh);

        $this->geoname->installTimezone();

        $this->assertCount(2, $timezoneRepo->findAll());
        $foo = $timezoneRepo->find(1);
        $this->assertNotEmpty($foo);
        $this->assertSame('Africa/Foo', $foo->getCode());
        $this->assertSame('1.0', $foo->getOffset());
        $this->assertSame('1.0', $foo->getOffsetJan());
        $this->assertSame('-1.0', $foo->getOffsetJul());
        $this->assertSame($fooCountry, $foo->getCountry());
        $bar = $timezoneRepo->find(2);
        $this->assertNotEmpty($bar);
        $this->assertSame('Africa/Bar_Baz', $bar->getCode());
        $this->assertSame('0.0', $bar->getOffset());
        $this->assertSame('2.25', $bar->getOffsetJan());
        $this->assertSame('-2.5', $bar->getOffsetJul());
        $this->assertSame($barCountry, $bar->getCountry());
    }

    public function testInstallNeighbour()
    {
        $countryRepo =
            $this->em->getRepository('Heartsentwined\Geoname\Entity\Country');

        $foo1 = new Entity\Country;
        $this->em->persist($foo1);
        $foo1->setIso2('F1');
        $foo2 = new Entity\Country;
        $this->em->persist($foo2);
        $foo2->setIso2('F2');
        $foo3 = new Entity\Country;
        $this->em->persist($foo3);
        $foo3->setIso2('F3');
        $bar1 = new Entity\Country;
        $this->em->persist($bar1);
        $bar1->setIso2('B1');
        $bar2 = new Entity\Country;
        $this->em->persist($bar2);
        $bar2->setIso2('B2');
        $baz = new Entity\Country;
        $this->em->persist($baz);
        $baz->setIso2('BZ');
        $this->em->flush();

        $fh = fopen('tmp/geoname/countryInfo.txt', 'a+');
        fwrite($fh, "# foo\n"); // a comment line
        // multiple neighbours
        fwrite($fh, "F1\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tF2,F3\t\n");
        fwrite($fh, "F2\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tF1,F3\t\n");
        fwrite($fh, "F3\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tF1,F2\t\n");
        // single neighbour
        fwrite($fh, "B1\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tB2\t\n");
        fwrite($fh, "B2\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tB1\t\n");
        // no neighbour
        fwrite($fh, "BZ\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\n");
        fclose($fh);

        $this->geoname->installNeighbour();
return;

        $expectedNeighbours = array($foo2, $foo3);
        $actualNeighbours = array();
        foreach ($foo1->getNeighbours() as $neighbour) {
            $actualNeighbours[] = $neighbour;
        }
        sort($expectedNeighbours);
        sort($actualNeighbours);
        $this->assertSame($expectedNeighbours, $actualNeighbours);
        $expectedNeighbours = array($foo1, $foo3);
        $actualNeighbours = array();
        foreach ($foo2->getNeighbours() as $neighbour) {
            $actualNeighbours[] = $neighbour;
        }
        sort($expectedNeighbours);
        sort($actualNeighbours);
        $this->assertSame($expectedNeighbours, $actualNeighbours);
        $expectedNeighbours = array($foo1, $foo2);
        $actualNeighbours = array();
        foreach ($foo3->getNeighbours() as $neighbour) {
            $actualNeighbours[] = $neighbour;
        }
        sort($expectedNeighbours);
        sort($actualNeighbours);
        $this->assertSame($expectedNeighbours, $actualNeighbours);

        $expectedNeighbours = array($bar2);
        $actualNeighbours = array();
        foreach ($bar1->getNeighbours() as $neighbour) {
            $actualNeighbours[] = $neighbour;
        }
        sort($expectedNeighbours);
        sort($actualNeighbours);
        $this->assertSame($expectedNeighbours, $actualNeighbours);
        $expectedNeighbours = array($bar1);
        $actualNeighbours = array();
        foreach ($bar2->getNeighbours() as $neighbour) {
            $actualNeighbours[] = $neighbour;
        }
        sort($expectedNeighbours);
        sort($actualNeighbours);
        $this->assertSame($expectedNeighbours, $actualNeighbours);

        $expectedNeighbours = array();
        $actualNeighbours = array();
        foreach ($baz->getNeighbours() as $neighbour) {
            $actualNeighbours[] = $neighbour;
        }
        sort($expectedNeighbours);
        sort($actualNeighbours);
        $this->assertSame($expectedNeighbours, $actualNeighbours);
    }
}
