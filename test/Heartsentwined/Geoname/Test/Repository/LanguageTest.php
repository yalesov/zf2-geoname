<?php
namespace Heartsentwined\Geoname\Test\Repository;

use Heartsentwined\Geoname\Entity;
use Heartsentwined\Geoname\Repository;
use Heartsentwined\Phpunit\Testcase\Doctrine as DoctrineTestcase;

class LanguageTest extends DoctrineTestcase
{
    public function setUp()
    {
        $this
            ->setBootstrap(__DIR__ . '/../../../../../bootstrap.php')
            ->setEmAlias('doctrine.entitymanager.orm_default')
            ->setTmpDir('tmp');
        parent::setUp();

        $this->repo =
            $this->em->getRepository('Heartsentwined\Geoname\Entity\Language');

        $this->foo = new Entity\Language;
        $this->em->persist($this->foo);
        $this->foo
            ->setIso3('foo')
            ->setIso2('fo')
            ->setIso1('f');
        $this->em->flush();
    }

    public function testFindLanguage()
    {
        $this->assertSame($this->foo, $this->repo->findLanguage('foo'));
        $this->assertSame($this->foo, $this->repo->findLanguage('fo'));
        $this->assertSame($this->foo, $this->repo->findLanguage('f'));
    }
}
