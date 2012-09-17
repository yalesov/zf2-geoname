<?php
namespace Geoname;

use Heartsentwined\Yaml\Yaml;
use Zend\EventManager\Event;
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;

/**
 * Geoname module
 *
 * @author heartsentwined <heartsentwined@cogito-lab.com>
 * @license GPL http://opensource.org/licenses/gpl-license.php
 */
class Module implements AutoloaderProviderInterface
{
    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\ClassMapAutoloader' => array(
                __DIR__ . '/autoload_classmap.php',
            ),
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__,
                ),
            ),
        );
    }

    public function getConfig()
    {
        return Yaml::parse(__DIR__ . '/../../config/module.config.yml');
    }

    public function onBootstrap(Event $e)
    {
        $sm         = $e->getApplication()->getServiceManager();
        $em         = $sm->get('doctrine.entitymanager.orm_default');
        $geoname    = $sm->get('geoname');
        $geoname
            ->setEm($em)
            ->registerCron();
    }
}
