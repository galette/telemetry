<?php

global $container, $recaptcha;

use Slim\App;

require '../app/init.php';

// route: default
/** @var App $app */
$app->get('/', 'GaletteTelemetry\Controllers\Telemetry:view')
    ->setName('telemetry');

/** References */
//References list
$app->get('/reference[/page/{page:\d+}]', 'GaletteTelemetry\Controllers\Reference:view')
   ->add($container->get('csrf'))
   ->setName('reference');

//References filtering
$app->post(
    '/reference/filter',
    'GaletteTelemetry\Controllers\Reference:filter'
)
   ->add($container->get('csrf'))
   ->setName('filterReferences');

$app->get(
    '/reference/order/{field}',
    'GaletteTelemetry\Controllers\Reference:order'
)
    ->setName('orderReferences');

//Reference registration
$app->post('/reference', 'GaletteTelemetry\Controllers\Reference:register')
   ->add($recaptcha)
   ->add($container->get('csrf'))
   ->setName('registerReference');
/** /References */

// telemetry
$app->get('/telemetry', 'GaletteTelemetry\Controllers\Telemetry:view');
$app->post('/telemetry', 'GaletteTelemetry\Controllers\Telemetry:send');
$app->get('/telemetry/geojson', 'GaletteTelemetry\Controllers\Telemetry:geojson')
    ->setName('geojson');

$app->get('/telemetry/schema.json', 'GaletteTelemetry\Controllers\Telemetry:schema')
    ->setName('schema');

$app->get(
    '/telemetry/plugins/all',
    'GaletteTelemetry\Controllers\Telemetry:allPlugins'
)->setName('allPlugins');

$app->post(
    '/write-dark-css',
    'GaletteTelemetry\Controllers\Telemetry:writeDarkCSS'
)->setName('writeDarkCSS');

$app->get(
    '/get-dark-css',
    'GaletteTelemetry\Controllers\Telemetry:getDarkCSS'
)->setName('getDarkCSS');

// run slim
$app->run();
