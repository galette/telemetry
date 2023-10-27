<?php

require '../app/init.php';

// route: default
$app->get('/', 'GaletteTelemetry\Controllers\Telemetry:view')
    ->setName('telemetry');

/** References */
//References list
$app->get('/reference[/page/{page:\d+}]', 'GaletteTelemetry\Controllers\Reference:view')
   ->add(new GaletteTelemetry\Middleware\CsrfView($container))
   ->add($container['csrf'])
   ->setName('reference');

//References filtering
$app->map(
    ['get', 'post'],
    '/reference/{action:filter|order}[/{value}]',
    'GaletteTelemetry\Controllers\Reference:filter'
)
   ->add(new GaletteTelemetry\Middleware\CsrfView($container))
   ->add($container['csrf'])
   ->setName('filterReferences');

//Reference registration
$app->post('/reference', 'GaletteTelemetry\Controllers\Reference:register')
   ->add($recaptcha)
   ->add($container['csrf'])
   ->setName('registerReference');
/** /References */

// telemetry
$app->get('/telemetry', 'GaletteTelemetry\Controllers\Telemetry:view');
$app->post('/telemetry', 'GaletteTelemetry\Controllers\Telemetry:send')
   ->add(new \GaletteTelemetry\Middleware\JsonCheck($container));
$app->get('/telemetry/geojson', 'GaletteTelemetry\Controllers\Telemetry:geojson')
    ->setName('geojson');

$app->get('/telemetry/schema.json', 'GaletteTelemetry\Controllers\Telemetry:schema')
    ->setName('schema');

$app->get(
    '/json-data-example',
    function ($request, $response) {
        $response->getBody()->write($this->project->getExampleData());
        return $response;
    }
)->setName('jsonExemple');

$app->get(
    '/telemetry/plugins/all',
    'GaletteTelemetry\Controllers\Telemetry:allPlugins'
)->setName('allPlugins');

$app->post(
    '/references/filter',
    'GaletteTelemetry\Controllers\References\doFilter'
)->setName('filter_references');

// run slim
$app->run();
