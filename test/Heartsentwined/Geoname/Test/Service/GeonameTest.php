<?php
namespace Heartsentwined\Geoname\Test\Service;

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
            ->setBootstrap(__DIR__ . '/../../../../../bootstrap.php')
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

    public function testRun()
    {
        $stageMap = array(
            Repository\Meta::STATUS_INSTALL_DOWNLOAD => array(
                'status' => Repository\Meta::STATUS_INSTALL_PREPARE,
                'method' => 'installDownload',
            ),
            Repository\Meta::STATUS_INSTALL_PREPARE => array(
                'status' => Repository\Meta::STATUS_INSTALL_LANGUAGE,
                'method' => 'installPrepare',
            ),
            Repository\Meta::STATUS_INSTALL_LANGUAGE => array(
                'status' => Repository\Meta::STATUS_INSTALL_FEATURE,
                'method' => 'installLanguage',
            ),
            Repository\Meta::STATUS_INSTALL_FEATURE => array(
                'status' => Repository\Meta::STATUS_INSTALL_PLACE,
                'method' => 'installFeature',
            ),
            Repository\Meta::STATUS_INSTALL_PLACE => array(
                'status' => null,
                'method' => 'installPlace',
            ),
            Repository\Meta::STATUS_INSTALL_COUNTRY_CURRENCY_LOCALE => array(
                'status' => Repository\Meta::STATUS_INSTALL_TIMEZONE,
                'method' => 'installCountryCurrencyLocale',
            ),
            Repository\Meta::STATUS_INSTALL_TIMEZONE => array(
                'status' => Repository\Meta::STATUS_INSTALL_NEIGHBOUR,
                'method' => 'installTimezone',
            ),
            Repository\Meta::STATUS_INSTALL_NEIGHBOUR => array(
                'status' => Repository\Meta::STATUS_INSTALL_PLACE_TIMEZONE,
                'method' => 'installNeighbour',
            ),
            Repository\Meta::STATUS_INSTALL_PLACE_TIMEZONE => array(
                'status' => null,
                'method' => 'installPlaceTimezone',
            ),
            Repository\Meta::STATUS_INSTALL_HIERARCHY => array(
                'status' => null,
                'method' => 'installHierarchy',
            ),
            Repository\Meta::STATUS_INSTALL_ALT_NAME => array(
                'status' => null,
                'method' => 'installAltName',
            ),
            Repository\Meta::STATUS_INSTALL_CLEANUP => array(
                'status' => Repository\Meta::STATUS_UPDATE,
                'method' => 'installCleanup',
            ),
            Repository\Meta::STATUS_UPDATE => array(
                'status' => null,
                'method' => array(
                    'updatePlaceModify',
                    'updatePlaceDelete',
                    'updateAltNameModify',
                    'updateAltNameDelete',
                    'updateCleanup',
                ),
            ),
        );

        foreach ($stageMap as $curStatus => $data) {
            $meta = new Entity\Meta;
            $this->em->persist($meta);
            $meta
                ->setStatus($curStatus)
                ->setIsLocked(false);
            $this->em->flush();

            $geoname = $this->getMock(
                'Heartsentwined\Geoname\Service\Geoname',
                array(
                    'downloadUpdate',
                    'installDownload',
                    'installPrepare',
                    'installLanguage',
                    'installFeature',
                    'installPlace',
                    'installCountryCurrencyLocale',
                    'installTimezone',
                    'installNeighbour',
                    'installPlaceTimezone',
                    'installHierarchy',
                    'installAltName',
                    'installCleanup',
                    'updatePlaceModify',
                    'updatePlaceDelete',
                    'updateAltNameModify',
                    'updateAltNameDelete',
                    'updateCleanup',
                ))
                ->setEm($this->em)
                ->setTmpDir('tmp/geoname');
            $geoname
                ->expects($this->once())
                ->method('downloadUpdate');
            foreach ((array) $data['method'] as $method) {
                $geoname
                    ->expects($this->once())
                    ->method($method)
                    ->will($this->returnSelf());
            }
            $geoname->run();

            if ($data['status'] !== null) {
                $this->assertSame($data['status'], $meta->getStatus());
            } else {
                $this->assertSame($curStatus, $meta->getStatus());
            }
            $this->assertFalse($meta->getIsLocked());

            $metaRepo = $this->em
                ->getRepository('Heartsentwined\Geoname\Entity\Meta');
            foreach ($metaRepo->findAll() as $meta) {
                $this->em->remove($meta);
            }
            $this->em->flush();
            $this->assertCount(0, $metaRepo->findAll());
        }
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

    public function testGetLock()
    {
        $this->assertFalse(file_exists('tmp/foo'));
        $this->assertFalse(file_exists('tmp/foo.lock'));

        $this->assertFalse($this->geoname->getLock('tmp/foo'));
        $this->assertFalse(file_exists('tmp/foo'));
        $this->assertFalse(file_exists('tmp/foo.lock'));

        touch('tmp/foo');
        $this->assertTrue($this->geoname->getLock('tmp/foo'));
        $this->assertFalse(file_exists('tmp/foo'));
        $this->assertTrue(file_exists('tmp/foo.lock'));

        $this->assertFalse($this->geoname->getLock('tmp/foo'));
        $this->assertFalse(file_exists('tmp/foo'));
        $this->assertTrue(file_exists('tmp/foo.lock'));

        $this->assertFalse($this->geoname->getLock('tmp/foo.lock'));
        $this->assertFalse(file_exists('tmp/foo'));
        $this->assertTrue(file_exists('tmp/foo.lock'));
    }

    public function testMarkDone()
    {
        $this->assertFalse(file_exists('tmp/foo'));
        $this->assertFalse(file_exists('tmp/foo.lock'));
        $this->assertFalse(file_exists('tmp/foo.done'));

        $this->assertFalse($this->geoname->markDone('tmp/foo'));
        $this->assertFalse(file_exists('tmp/foo'));
        $this->assertFalse(file_exists('tmp/foo.lock'));
        $this->assertFalse(file_exists('tmp/foo.done'));

        touch('tmp/foo');
        $this->assertFalse($this->geoname->markDone('tmp/foo'));
        $this->assertTrue(file_exists('tmp/foo'));
        $this->assertFalse(file_exists('tmp/foo.lock'));
        $this->assertFalse(file_exists('tmp/foo.done'));

        unlink('tmp/foo');
        touch('tmp/foo.done');
        $this->assertFalse($this->geoname->markDone('tmp/foo'));
        $this->assertFalse(file_exists('tmp/foo'));
        $this->assertFalse(file_exists('tmp/foo.lock'));
        $this->assertTrue(file_exists('tmp/foo.done'));

        unlink('tmp/foo.done');
        touch('tmp/foo.lock');
        $this->assertTrue($this->geoname->markDone('tmp/foo'));
        $this->assertFalse(file_exists('tmp/foo'));
        $this->assertFalse(file_exists('tmp/foo.lock'));
        $this->assertTrue(file_exists('tmp/foo.done'));

        $this->assertFalse($this->geoname->markDone('tmp/foo'));
        $this->assertFalse(file_exists('tmp/foo'));
        $this->assertFalse(file_exists('tmp/foo.lock'));
        $this->assertTrue(file_exists('tmp/foo.done'));
    }

    public function testResetFiles()
    {
        mkdir('tmp/foo');
        touch('tmp/foo/foo');
        touch('tmp/foo/bar.lock');
        touch('tmp/foo/baz.done');

        $this->geoname->resetFiles('tmp/foo');
        $this->assertTrue(file_exists('tmp/foo/foo'));
        $this->assertTrue(file_exists('tmp/foo/bar'));
        $this->assertTrue(file_exists('tmp/foo/baz'));
        $this->assertFalse(file_exists('tmp/foo/bar.lock'));
        $this->assertFalse(file_exists('tmp/foo/baz.done'));
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
        $this->assertEmpty($foo->getParent());
        $this->assertEmpty($foo->getTimezone());

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
        $this->assertEmpty($bar->getParent());
        $this->assertEmpty($bar->getTimezone());

        $this->geoname->installPlace();
        $count = 0;
        foreach (FileSystemManager::fileIterator('tmp/geoname/allCountries') as $file) {
            if (strpos($file, '.lock') || strpos($file, '.done')) {
                $count++;
            }
        }
        $this->assertSame(0, $count);
        $this->assertSame(
            Repository\Meta::STATUS_INSTALL_COUNTRY_CURRENCY_LOCALE,
            $this->geoname->getMeta()->getStatus());
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

    public function testInstallPlaceTimezone()
    {
        $placeRepo =
            $this->em->getRepository('Heartsentwined\Geoname\Entity\Place');
        $timezoneRepo =
            $this->em->getRepository('Heartsentwined\Geoname\Entity\Timezone');

        $foo = new Entity\Place;
        $foo->setId(1);
        $this->em->persist($foo);
        $bar = new Entity\Place;
        $bar->setId(2);
        $this->em->persist($bar);

        $fooTimezone = new Entity\Timezone;
        $this->em->persist($fooTimezone);
        $fooTimezone->setCode('Asia/Foo');
        $barCountry = new Entity\Country;
        $this->em->persist($barCountry);
        $barCountry->setIso2('BA');

        $this->em->flush();

        mkdir('tmp/geoname/allCountries');
        $fh = fopen('tmp/geoname/allCountries/1', 'a+');
        // existing timezone
        fwrite($fh, "1\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tAsia/Foo\t\n");
        // new timezone
        fwrite($fh, "2\t\t\t\t\t\t\t\tBA\t\t\t\t\t\t\t\t\tAsia/Bar\t\n");
        fclose($fh);

        $this->geoname->installPlaceTimezone();

        $this->assertCount(2, $timezoneRepo->findAll());
        $barTimezone = $timezoneRepo->findOneBy(array('code' => 'Asia/Bar'));
        $this->assertSame($barCountry, $barTimezone->getCountry());

        $foo = $placeRepo->find(1);
        $this->assertNotEmpty($foo);
        $this->assertSame($fooTimezone, $foo->getTimezone());

        $bar = $placeRepo->find(2);
        $this->assertNotEmpty($bar);
        $this->assertSame($barTimezone, $bar->getTimezone());

        $this->geoname->installPlaceTimezone();
        $count = 0;
        foreach (FileSystemManager::fileIterator('tmp/geoname/allCountries') as $file) {
            if (strpos($file, '.lock') || strpos($file, '.done')) {
                $count++;
            }
        }
        $this->assertSame(0, $count);
        $this->assertSame(
            Repository\Meta::STATUS_INSTALL_HIERARCHY,
            $this->geoname->getMeta()->getStatus());
    }

    public function testInstallHierarchy()
    {
        $placeRepo =
            $this->em->getRepository('Heartsentwined\Geoname\Entity\Place');

        $foo = new Entity\Place;
        $foo->setId(1);
        $this->em->persist($foo);
        $bar = new Entity\Place;
        $bar->setId(2);
        $this->em->persist($bar);
        $baz = new Entity\Place;
        $baz->setId(3);
        $this->em->persist($baz);
        $qux = new Entity\Place;
        $qux->setId(4);
        $this->em->persist($qux);

        $this->em->flush();

        mkdir('tmp/geoname/hierarchy');
        $fh = fopen('tmp/geoname/hierarchy/1', 'a+');
        fwrite($fh, "1\t2\t\n");
        fwrite($fh, "2\t3\t\n");
        fclose($fh);

        $this->geoname->installHierarchy();

        $this->assertEmpty($foo->getParent());
        $this->assertSame($foo, $bar->getParent());
        $this->assertSame($bar, $baz->getParent());
        $this->assertEmpty($qux->getParent());

        $this->geoname->installHierarchy();
        $count = 0;
        foreach (FileSystemManager::fileIterator('tmp/geoname/hierarchy') as $file) {
            if (strpos($file, '.lock') || strpos($file, '.done')) {
                $count++;
            }
        }
        $this->assertSame(0, $count);
        $this->assertSame(
            Repository\Meta::STATUS_INSTALL_ALT_NAME,
            $this->geoname->getMeta()->getStatus());
    }

    public function testInstallAltName()
    {
        $altNameRepo =
            $this->em->getRepository('Heartsentwined\Geoname\Entity\AltName');

        $fooPlace = new Entity\Place;
        $fooPlace->setId(1);
        $this->em->persist($fooPlace);
        $lang = new Entity\Language;
        $this->em->persist($lang);
        $lang
            ->setIso3('foo')
            ->setIso2('fo')
            ->setIso1('f');
        $this->em->flush();

        mkdir('tmp/geoname/alternateNames');
        $fh = fopen('tmp/geoname/alternateNames/1', 'a+');
        fwrite($fh, "1\t1\tfoo\tfoo alt name\t1\t1\t1\t1\n");
        fwrite($fh, "2\t1\tfo\tكوه زرد سياه لت\t0\t0\t0\t0\n");
        fwrite($fh, "3\t1\tf\tplacé\t\t\t\t\n");
        fwrite($fh, "4\t1\t\tplace\t\t\t\t\n");
        fwrite($fh, "5\t2\t\tplace\t\t\t\t\n");
        fclose($fh);

        $this->geoname->installAltName();

        $this->assertCount(5, $altNameRepo->findAll());

        $altName1 = $altNameRepo->find(1);
        $this->assertNotEmpty($altName1);
        $this->assertSame('foo alt name', $altName1->getName());
        $this->assertSame(true, $altName1->getIsPreferred());
        $this->assertSame(true, $altName1->getIsShort());
        $this->assertSame(true, $altName1->getIsColloquial());
        $this->assertSame(true, $altName1->getIsHistoric());
        $this->assertSame($fooPlace, $altName1->getPlace());
        $this->assertSame($lang, $altName1->getLanguage());

        $altName2 = $altNameRepo->find(2);
        $this->assertNotEmpty($altName2);
        $this->assertSame('كوه زرد سياه لت', $altName2->getName());
        $this->assertSame(false, $altName2->getIsPreferred());
        $this->assertSame(false, $altName2->getIsShort());
        $this->assertSame(false, $altName2->getIsColloquial());
        $this->assertSame(false, $altName2->getIsHistoric());
        $this->assertSame($fooPlace, $altName2->getPlace());
        $this->assertSame($lang, $altName2->getLanguage());

        $altName3 = $altNameRepo->find(3);
        $this->assertNotEmpty($altName3);
        $this->assertSame('placé', $altName3->getName());
        $this->assertSame(false, $altName3->getIsPreferred());
        $this->assertSame(false, $altName3->getIsShort());
        $this->assertSame(false, $altName3->getIsColloquial());
        $this->assertSame(false, $altName3->getIsHistoric());
        $this->assertSame($fooPlace, $altName3->getPlace());
        $this->assertSame($lang, $altName3->getLanguage());

        $altName4 = $altNameRepo->find(4);
        $this->assertNotEmpty($altName4);
        $this->assertSame('place', $altName4->getName());
        $this->assertSame(false, $altName4->getIsPreferred());
        $this->assertSame(false, $altName4->getIsShort());
        $this->assertSame(false, $altName4->getIsColloquial());
        $this->assertSame(false, $altName4->getIsHistoric());
        $this->assertSame($fooPlace, $altName4->getPlace());
        $this->assertEmpty($altName4->getLanguage());

        $altName5 = $altNameRepo->find(5);
        $this->assertNotEmpty($altName5);
        $this->assertSame('place', $altName5->getName());
        $this->assertSame(false, $altName5->getIsPreferred());
        $this->assertSame(false, $altName5->getIsShort());
        $this->assertSame(false, $altName5->getIsColloquial());
        $this->assertSame(false, $altName5->getIsHistoric());
        $this->assertEmpty($altName5->getPlace());
        $this->assertEmpty($altName5->getLanguage());

        $this->geoname->installAltName();
        $count = 0;
        foreach (FileSystemManager::fileIterator('tmp/geoname/alternateNames') as $file) {
            if (strpos($file, '.lock') || strpos($file, '.done')) {
                $count++;
            }
        }
        $this->assertSame(0, $count);
        $this->assertSame(
            Repository\Meta::STATUS_INSTALL_CLEANUP,
            $this->geoname->getMeta()->getStatus());
    }

    public function testInstallCleanup()
    {
        mkdir('tmp/geoname/foo');
        mkdir('tmp/geoname/foo/bar');
        touch('tmp/geoname/foo/baz');
        mkdir('tmp/geoname/update');
        mkdir('tmp/geoname/update/bar');
        touch('tmp/geoname/update/baz');

        $this->geoname->installCleanup();

        $this->assertFalse(file_exists('tmp/geoname/foo'));
        $this->assertFalse(file_exists('tmp/geoname/foo/bar'));
        $this->assertFalse(file_exists('tmp/geoname/foo/baz'));
        $this->assertFileExists('tmp/geoname/update');
        $this->assertFileExists('tmp/geoname/update/bar');
        $this->assertFileExists('tmp/geoname/update/baz');
    }

    public function testUpdatePlaceModify()
    {
        $placeRepo =
            $this->em->getRepository('Heartsentwined\Geoname\Entity\Place');
        $featureRepo =
            $this->em->getRepository('Heartsentwined\Geoname\Entity\Feature');
        $timezoneRepo =
            $this->em->getRepository('Heartsentwined\Geoname\Entity\Timezone');

        $place2 = new Entity\Place;
        $place2->setId(2);
        $this->em->persist($place2);

        $forest = new Entity\Feature;
        $this->em->persist($forest);
        $forest
            ->setCode('V')
            ->setDescription('')
            ->setComment('');
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
            ->setParent($forest);

        $kabul = new Entity\Timezone;
        $this->em->persist($kabul);
        $kabul->setCode('Asia/Kabul');

        $germany = new Entity\Place;
        $germany
            ->setId(90)
            ->setName('germany')
            ->setCountryCode('DE');
        $this->em->persist($germany);
        $usa = new Entity\Place;
        $usa
            ->setId(91)
            ->setName('usa')
            ->setCountryCode('US');
        $this->em->persist($usa);

        $this->em->flush();

        mkdir('tmp/geoname/update/place/modification', 0777, true);
        $fh = fopen('tmp/geoname/update/place/modification/1', 'a+');
        // new place
        fwrite($fh,
            '1'                 // id
            . "\tfoo placé 早"  // name - with funny chars
            . "\tfoo ascii"     // ascii name
            . "\tfoo alt"       // alt name
            . "\t50.1"          // latitude
            . "\t100.2"         // longitude
            . "\tV"             // feature class
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
        // existing place
        fwrite($fh,
            '2'                 // id
            . "\tfoo placé 早"  // name - with funny chars
            . "\tfoo ascii"     // ascii name
            . "\tfoo alt"       // alt name
            . "\t50.1"          // latitude
            . "\t100.2"         // longitude
            . "\tV"             // feature class
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
            '3'                 // id
            . "\t"
            . "\t"
            . "\t"
            . "\t"
            . "\t"
            . "\tV"             // feature class
            . "\tBAZ"           // new feature
            . "\t"
            . "\t"
            . "\t"
            . "\t"
            . "\t"
            . "\t"
            . "\t"
            . "\t"
            . "\t"
            . "\tAsia/Baz"      // new timezone
            . "\t"
            . "\n"
        );
        // germany admin 1
        fwrite($fh, "10\t\t\t\t\t\tA\tADM1\tDE\t\t01\t\t\t\t\t\t\t\t\n");
        // germany admin 2
        fwrite($fh, "11\t\t\t\t\t\tA\tADM2\tDE\t\t01\t01\t\t\t\t\t\t\t\n");
        // germany admin 4
        fwrite($fh, "12\t\t\t\t\t\tA\tADM4\tDE\t\t01\t01\t00\t01\t\t\t\t\t\n");
        // usa admin 1
        fwrite($fh, "21\t\t\t\t\t\tA\tADM1\tUS\t\t01\t\t\t\t\t\t\t\t\n");
        // usa admin 2
        fwrite($fh, "22\t\t\t\t\t\tA\tADM2\tUS\t\t01\t01\t\t\t\t\t\t\t\n");
        fclose($fh);

        $this->geoname->updatePlaceModify();

        $this->assertCount(7, $featureRepo->findAll());

        $bazFeature = $featureRepo->findOneBy(array('code' => 'BAZ'));
        $this->assertNotEmpty($bazFeature);
        $this->assertSame($forest, $bazFeature->getParent());

        $admin1 = $featureRepo->findOneBy(array('code' => 'ADM1'));
        $this->assertNotEmpty($admin1);
        $this->assertSame($admin, $admin1->getParent());

        $admin2 = $featureRepo->findOneBy(array('code' => 'ADM2'));
        $this->assertNotEmpty($admin2);
        $this->assertSame($admin, $admin2->getParent());

        $admin4 = $featureRepo->findOneBy(array('code' => 'ADM4'));
        $this->assertNotEmpty($admin4);
        $this->assertSame($admin, $admin4->getParent());

        $this->assertCount(2, $timezoneRepo->findAll());
        $bazTimezone = $timezoneRepo->findOneBy(array('code' => 'Asia/Baz'));
        $this->assertNotEmpty($bazTimezone);

        $this->assertCount(10, $placeRepo->findAll());

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
        $this->assertEmpty($foo->getParent());
        $this->assertSame($kabul, $foo->getTimezone());

        $foo2 = $placeRepo->find(2);
        $this->assertNotEmpty($foo2);
        $this->assertSame($place2, $foo2);
        $this->assertSame('foo placé 早', $foo2->getName());
        $this->assertSame('50.1', $foo2->getLatitude());
        $this->assertSame('100.2', $foo2->getLongitude());
        $this->assertSame('1000', $foo2->getElevation());
        $this->assertSame('2000', $foo2->getDigiEleModel());
        $this->assertSame('AB', $foo2->getCountryCode());
        $this->assertSame('admin1', $foo2->getAdmin1Code());
        $this->assertSame('admin2', $foo2->getAdmin2Code());
        $this->assertSame('admin3', $foo2->getAdmin3Code());
        $this->assertSame('admin4', $foo2->getAdmin4Code());
        $this->assertSame('100000', $foo2->getPopulation());
        $this->assertSame($bar, $foo2->getFeature());
        $this->assertEmpty($foo2->getParent());
        $this->assertSame($kabul, $foo2->getTimezone());

        $place3 = $placeRepo->find(3);
        $this->assertNotEmpty($place3);
        $this->assertEmpty($place3->getName());
        $this->assertEmpty($place3->getLatitude());
        $this->assertEmpty($place3->getLongitude());
        $this->assertEmpty($place3->getElevation());
        $this->assertEmpty($place3->getDigiEleModel());
        $this->assertEmpty($place3->getCountryCode());
        $this->assertEmpty($place3->getAdmin1Code());
        $this->assertEmpty($place3->getAdmin2Code());
        $this->assertEmpty($place3->getAdmin3Code());
        $this->assertEmpty($place3->getAdmin4Code());
        $this->assertEmpty($place3->getPopulation());
        $this->assertSame($bazFeature, $place3->getFeature());
        $this->assertEmpty($place3->getParent());
        $this->assertSame($bazTimezone, $place3->getTimezone());

        $germany1 = $placeRepo->find(10);
        $this->assertNotEmpty($germany1);
        $this->assertEmpty($germany1->getName());
        $this->assertEmpty($germany1->getLatitude());
        $this->assertEmpty($germany1->getLongitude());
        $this->assertEmpty($germany1->getElevation());
        $this->assertEmpty($germany1->getDigiEleModel());
        $this->assertSame('DE', $germany1->getCountryCode());
        $this->assertSame('01', $germany1->getAdmin1Code());
        $this->assertEmpty($germany1->getAdmin2Code());
        $this->assertEmpty($germany1->getAdmin3Code());
        $this->assertEmpty($germany1->getAdmin4Code());
        $this->assertEmpty($germany1->getPopulation());
        $this->assertSame($admin1, $germany1->getFeature());
        $this->assertSame($germany, $germany1->getParent());
        $this->assertEmpty($germany1->getTimezone());

        $germany2 = $placeRepo->find(11);
        $this->assertNotEmpty($germany2);
        $this->assertEmpty($germany2->getName());
        $this->assertEmpty($germany2->getLatitude());
        $this->assertEmpty($germany2->getLongitude());
        $this->assertEmpty($germany2->getElevation());
        $this->assertEmpty($germany2->getDigiEleModel());
        $this->assertSame('DE', $germany2->getCountryCode());
        $this->assertSame('01', $germany2->getAdmin1Code());
        $this->assertSame('01', $germany2->getAdmin2Code());
        $this->assertEmpty($germany2->getAdmin3Code());
        $this->assertEmpty($germany2->getAdmin4Code());
        $this->assertEmpty($germany2->getPopulation());
        $this->assertSame($admin2, $germany2->getFeature());
        $this->assertSame($germany1, $germany2->getParent());
        $this->assertEmpty($germany2->getTimezone());

        $germany4 = $placeRepo->find(12);
        $this->assertNotEmpty($germany4);
        $this->assertEmpty($germany4->getName());
        $this->assertEmpty($germany4->getLatitude());
        $this->assertEmpty($germany4->getLongitude());
        $this->assertEmpty($germany4->getElevation());
        $this->assertEmpty($germany4->getDigiEleModel());
        $this->assertSame('DE', $germany4->getCountryCode());
        $this->assertSame('01', $germany4->getAdmin1Code());
        $this->assertSame('01', $germany4->getAdmin2Code());
        $this->assertSame('00', $germany4->getAdmin3Code());
        $this->assertSame('01', $germany4->getAdmin4Code());
        $this->assertEmpty($germany4->getPopulation());
        $this->assertSame($admin4, $germany4->getFeature());
        $this->assertSame($germany2, $germany4->getParent());
        $this->assertEmpty($germany4->getTimezone());

        $usa1 = $placeRepo->find(21);
        $this->assertNotEmpty($usa1);
        $this->assertEmpty($usa1->getName());
        $this->assertEmpty($usa1->getLatitude());
        $this->assertEmpty($usa1->getLongitude());
        $this->assertEmpty($usa1->getElevation());
        $this->assertEmpty($usa1->getDigiEleModel());
        $this->assertSame('US', $usa1->getCountryCode());
        $this->assertSame('01', $usa1->getAdmin1Code());
        $this->assertEmpty($usa1->getAdmin2Code());
        $this->assertEmpty($usa1->getAdmin3Code());
        $this->assertEmpty($usa1->getAdmin4Code());
        $this->assertEmpty($usa1->getPopulation());
        $this->assertSame($admin1, $usa1->getFeature());
        $this->assertSame($usa, $usa1->getParent());
        $this->assertEmpty($usa1->getTimezone());

        $usa2 = $placeRepo->find(22);
        $this->assertNotEmpty($usa2);
        $this->assertEmpty($usa2->getName());
        $this->assertEmpty($usa2->getLatitude());
        $this->assertEmpty($usa2->getLongitude());
        $this->assertEmpty($usa2->getElevation());
        $this->assertEmpty($usa2->getDigiEleModel());
        $this->assertSame('US', $usa2->getCountryCode());
        $this->assertSame('01', $usa2->getAdmin1Code());
        $this->assertSame('01', $usa2->getAdmin2Code());
        $this->assertEmpty($usa2->getAdmin3Code());
        $this->assertEmpty($usa2->getAdmin4Code());
        $this->assertEmpty($usa2->getPopulation());
        $this->assertSame($admin2, $usa2->getFeature());
        $this->assertSame($usa1, $usa2->getParent());
        $this->assertEmpty($usa2->getTimezone());
    }

    public function testUpdatePlaceDelete()
    {
        $placeRepo =
            $this->em->getRepository('Heartsentwined\Geoname\Entity\Place');

        $foo = new Entity\Place;
        $foo
            ->setId(1);
        $this->em->persist($foo);
        $bar = new Entity\Place;
        $bar
            ->setId(2);
        $this->em->persist($bar);
        $this->em->flush();

        mkdir('tmp/geoname/update/place/delete', 0777, true);
        $fh = fopen('tmp/geoname/update/place/delete/1', 'a+');
        fwrite($fh, "1\tfoo\tfoo comment\n");
        fclose($fh);

        $this->geoname->updatePlaceDelete();

        $this->assertCount(2, $placeRepo->findAll());

        $this->assertTrue((bool) $foo->getIsDeprecated());
        $this->assertFalse((bool) $bar->getIsDeprecated());
    }

    public function testUpdateAltNameModify()
    {
        $altNameRepo =
            $this->em->getRepository('Heartsentwined\Geoname\Entity\AltName');

        $fooPlace = new Entity\Place;
        $fooPlace->setId(1);
        $this->em->persist($fooPlace);
        $fooLang = new Entity\Language;
        $this->em->persist($fooLang);
        $fooLang->setIso3('foo');
        $fooAltName = new Entity\AltName;
        $fooAltName
            ->setId(1)
            ->setName('dummy')
            ->setIsPreferred(false)
            ->setIsShort(false)
            ->setIsColloquial(false)
            ->setIsHistoric(false);
        $this->em->persist($fooAltName);
        $this->em->flush();

        mkdir('tmp/geoname/update/altName/modification', 0777, true);
        $fh = fopen('tmp/geoname/update/altName/modification/1', 'a+');
        fwrite($fh, "1\t1\tfoo\tfoo alt name\t1\t1\t1\t1\n");
        fwrite($fh, "2\t2\tcustom lang\tfoo alt name\t\t\t\t\n");
        fclose($fh);

        $this->geoname->updateAltNameModify();

        $this->assertCount(2, $altNameRepo->findAll());

        $this->assertSame('foo alt name', $fooAltName->getName());
        $this->assertSame(true, $fooAltName->getIsPreferred());
        $this->assertSame(true, $fooAltName->getIsShort());
        $this->assertSame(true, $fooAltName->getIsColloquial());
        $this->assertSame(true, $fooAltName->getIsHistoric());
        $this->assertSame($fooPlace, $fooAltName->getPlace());
        $this->assertSame($fooLang, $fooAltName->getLanguage());

        $altName = $altNameRepo->find(2);
        $this->assertNotEmpty($altName);
        $this->assertSame('foo alt name', $altName->getName());
        $this->assertSame(false, $altName->getIsPreferred());
        $this->assertSame(false, $altName->getIsShort());
        $this->assertSame(false, $altName->getIsColloquial());
        $this->assertSame(false, $altName->getIsHistoric());
        $this->assertEmpty($altName->getPlace());
        $this->assertEmpty($altName->getLanguage());
    }

    public function testUpdateAltNameDelete()
    {
        $altNameRepo =
            $this->em->getRepository('Heartsentwined\Geoname\Entity\AltName');

        $fooAltName = new Entity\AltName;
        $fooAltName
            ->setId(1)
            ->setName('dummy')
            ->setIsPreferred(false)
            ->setIsShort(false)
            ->setIsColloquial(false)
            ->setIsHistoric(false);
        $this->em->persist($fooAltName);
        $barAltName = new Entity\AltName;
        $barAltName
            ->setId(2)
            ->setName('dummy')
            ->setIsPreferred(false)
            ->setIsShort(false)
            ->setIsColloquial(false)
            ->setIsHistoric(false);
        $this->em->persist($barAltName);
        $this->em->flush();

        mkdir('tmp/geoname/update/altName/delete', 0777, true);
        $fh = fopen('tmp/geoname/update/altName/delete/1', 'a+');
        fwrite($fh, "1\tfoo\tfoo comment\n");
        fclose($fh);

        $this->geoname->updateAltNameDelete();

        $this->assertCount(2, $altNameRepo->findAll());

        $this->assertTrue((bool) $fooAltName->getIsDeprecated());
        $this->assertFalse((bool) $barAltName->getIsDeprecated());
    }

    public function testUpdateCleanup()
    {
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
            $beyond = clone $latest;
            $beyond->setDate(
                $beyond->format('Y'),
                $beyond->format('n'),
                $beyond->format('j')-2);
            $beyondDate = $beyond->format('Y-m-d');
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
            $beyond = clone $latest;
            $beyond->setDate(
                $beyond->format('Y'),
                $beyond->format('n'),
                $beyond->format('j')-2);
            $beyondDate = $beyond->format('Y-m-d');
        }

        $testDirs = array(
            'tmp/geoname/update/foo',
            'tmp/geoname/update/foo/foo',
            'tmp/geoname/update/bar'
        );

        foreach ($testDirs as $dir) {
            mkdir($dir, 0777, true);
            touch("$dir/test$latestDate");
            touch("$dir/test$latestDate.done");
            touch("$dir/test$beforeDate");
            touch("$dir/test$beforeDate.done");
            touch("$dir/test$beyondDate");
            touch("$dir/test$beyondDate.done");
        }

        $this->geoname->updateCleanup();

        foreach ($testDirs as $dir) {
            $this->assertTrue(file_exists("$dir/test$latestDate"));
            $this->assertTrue(file_exists("$dir/test$latestDate.done"));
            $this->assertTrue(file_exists("$dir/test$beforeDate"));
            $this->assertTrue(file_exists("$dir/test$beforeDate.done"));
            $this->assertTrue(file_exists("$dir/test$beyondDate"));
            $this->assertFalse(file_exists("$dir/test$beyondDate.done"));
        }
    }
}
