<?php
namespace Heartsentwined\Geoname\Test\Repository;

use Heartsentwined\Geoname\Entity;
use Heartsentwined\Geoname\Repository;
use Heartsentwined\Phpunit\Testcase\Doctrine as DoctrineTestcase;

class PlaceTest extends DoctrineTestcase
{
    public function setUp()
    {
        $this
            ->setBootstrap(__DIR__ . '/../../../../../bootstrap.php')
            ->setEmAlias('doctrine.entitymanager.orm_default')
            ->setTmpDir('tmp');
        parent::setUp();

        $this->repo =
            $this->em->getRepository('Heartsentwined\Geoname\Entity\Place');

        $this->admin = new Entity\Feature;
        $this->em->persist($this->admin);
        $this->admin->setCode('A');
        $this->admin1 = new Entity\Feature;
        $this->em->persist($this->admin1);
        $this->admin1
            ->setCode('ADM1')
            ->setParent($this->admin);
        $this->admin2 = new Entity\Feature;
        $this->em->persist($this->admin2);
        $this->admin2
            ->setCode('ADM2')
            ->setParent($this->admin);

        $this->foo = new Entity\Place;
        $this->foo
            ->setId(1)
            ->setCountryCode('DE')
            ->setAdmin1Code('01')
            ->setAdmin2Code('00')
            ->setAdmin3Code('03')
            ->setAdmin4Code('')
            ->setFeature($this->admin1);
        $this->em->persist($this->foo);
        $this->bar = new Entity\Place;
        $this->bar
            ->setId(2)
            ->setCountryCode('BA')
            ->setFeature($this->admin2);
        $this->em->persist($this->bar);

        $this->em->flush();
    }

    public function testFindPlace()
    {
        $this->assertSame(array($this->foo, $this->bar),
            $this->repo->findPlace());
        $this->assertSame(array($this->foo, $this->bar),
            $this->repo->findPlace(array()));
        $this->assertSame(array($this->foo), $this->repo->findPlace(array(
            'countryCode'   => 'DE',
        )));
        $this->assertSame(array($this->foo), $this->repo->findPlace(array(
            'admin1Code'    => '01',
        )));
        $this->assertSame(array($this->foo), $this->repo->findPlace(array(
            'admin2Code'    => '00',
        )));
        $this->assertSame(array($this->foo), $this->repo->findPlace(array(
            'admin3Code'    => '03',
        )));
        $this->assertSame(array($this->foo, $this->bar),
            $this->repo->findPlace(array(
                'admin4Code'    => '',
            )));
        $this->assertSame(array($this->foo, $this->bar),
            $this->repo->findPlace(array(
                'featureClass'  => 'A',
            )));
        $this->assertSame(array($this->foo), $this->repo->findPlace(array(
            'featureCode'   => 'ADM1',
        )));
        $this->assertSame(array($this->foo), $this->repo->findPlace(array(
            'featureClass'  => 'A',
            'countryCode'   => 'DE',
        )));
        $this->assertSame(array($this->foo), $this->repo->findPlace(array(
            'featureCode'   => 'ADM1',
            'countryCode'   => 'DE',
        )));
        $this->assertSame(array($this->foo), $this->repo->findPlace(array(
            'featureClass'  => 'A',
            'featureCode'   => 'ADM1',
            'countryCode'   => 'DE',
        )));

        $this->assertEmpty($this->repo->findPlace(array(
            'featureClass'  => 'B',
            'featureCode'   => 'ADM1',
            'countryCode'   => 'DE',
        )));
        $this->assertEmpty($this->repo->findPlace(array(
            'featureClass'  => 'A',
            'featureCode'   => '',
            'countryCode'   => 'DE',
        )));
        $this->assertEmpty($this->repo->findPlace(array(
            'countryCode'   => '',
        )));
    }
}
