<?php
use Zend\Mvc\Application;
chdir(__DIR__);
require 'vendor/autoload.php';
return Application::init(array(
    'modules'   => array(
        'DoctrineModule',
        'DoctrineORMModule',
        'Geoname',
    ),
    'module_listener_options' => array(
        'module_paths' => array(
            'Geoname' => __DIR__,
            'vendor',
        ),
    ),
));
