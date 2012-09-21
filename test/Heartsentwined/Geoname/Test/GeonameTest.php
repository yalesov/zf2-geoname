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
}
