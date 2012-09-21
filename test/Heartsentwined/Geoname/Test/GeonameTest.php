<?php
namespace Heartsentwined\Geoname\Test;

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

        $this->geoname = $this->sm->get('geoname')
            ->setEm($this->em);
    }

    public function tearDown()
    {
        unset($this->geoname);
        parent::tearDown();
    }

    public function testInstance()
    {
        $this->assertInstanceOf('Heartsentwined\Geoname\Service\Geoname',
            $this->geoname);
    }
}
