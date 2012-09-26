<?php
namespace Heartsentwined\Geoname\Test\Repository;

use Heartsentwined\Geoname\Entity;
use Heartsentwined\Geoname\Repository;
use Heartsentwined\Phpunit\Testcase\Doctrine as DoctrineTestcase;

class FeatureTest extends DoctrineTestcase
{
    public function setUp()
    {
        $this
            ->setBootstrap(__DIR__ . '/../../../../../bootstrap.php')
            ->setEmAlias('doctrine.entitymanager.orm_default')
            ->setTmpDir('tmp');
        parent::setUp();

        $this->repo =
            $this->em->getRepository('Heartsentwined\Geoname\Entity\Feature');

        $this->fooParent = new Entity\Feature;
        $this->em->persist($this->fooParent);
        $this->fooParent->setCode('F');
        $this->foo = new Entity\Feature;
        $this->em->persist($this->foo);
        $this->foo
            ->setCode('FOO')
            ->setParent($this->fooParent);
        $this->barParent = new Entity\Feature;
        $this->em->persist($this->barParent);
        $this->barParent->setCode('B');
        $this->bar = new Entity\Feature;
        $this->em->persist($this->bar);
        $this->bar
            ->setCode('BAR')
            ->setParent($this->barParent);
        $this->em->flush();
    }

    public function testFindByGeonameCode()
    {
        $this->assertSame($this->foo, $this->repo->findByGeonameCode('F.FOO'));
        $this->assertSame($this->bar, $this->repo->findByGeonameCode('B.BAR'));
        $this->assertEmpty($this->repo->findByGeonameCode('F.BAR'));
        $this->assertEmpty($this->repo->findByGeonameCode('B.FOO'));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFindByGeonameCodeTooFewComponents()
    {
        $this->repo->findByGeonameCode('F');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFindByGeonameCodeTooManyComponents()
    {
        $this->repo->findByGeonameCode('F.FOO.FOO');
    }
}
