<?php
use Zend\Mvc\Application;
chdir(__DIR__);
require 'vendor/autoload.php';
return Application::init(array(
    'modules'   => array(
        'DoctrineModule',
        'DoctrineORMModule',
        'Heartsentwined\\Geoname',
    ),
    'module_listener_options' => array(
        'module_paths' => array(
            'Heartsentwined\\Geoname' => __DIR__,
            'vendor',
        ),
    ),
));
