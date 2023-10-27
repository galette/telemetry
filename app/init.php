<?php

use Illuminate\Pagination\Paginator;
use Geggleto\Service\Captcha;
use ReCaptcha\ReCaptcha;
use Twig\Extension\DebugExtension;

// Start PHP session
session_start();

// include user configuration
$config = require __DIR__ .  '/../config.inc.php';
if (file_exists(__DIR__ . '/../local.config.inc.php')) {
    require_once __DIR__ . '/../local.config.inc.php';
}
if (!defined('TELEMETRY_MODE')) {
    define('TELEMETRY_MODE', 'PROD');
}

$config['addContentLengthHeader'] = false;
if (TELEMETRY_MODE == 'DEV') {
    $config['debug'] = true;
    $config['displayErrorDetails'] = true;
}

//check for required options
$valid_conf = true;
if (!isset($config['project']) || empty($config['project'])) {
    throw new \DomainException('project is mandatory in configuration');
}

// autoload composer libs
require __DIR__ . '/../vendor/autoload.php';

// init slim
$app       = new \Slim\App(["settings" => $config]);
$container = $app->getContainer();

$container['project'] = function ($c) use ($config) {
    $project = new \GaletteTelemetry\Project($c->logger);
    $project->setConfig($config['project']);
    return $project;
};

// setup db connection
$capsule = new \Illuminate\Database\Capsule\Manager;
$capsule->addConnection($container->get('settings')['db']);
$capsule->setAsGlobal();
$capsule->bootEloquent();

// setup monolog
$container['logger'] = function ($c) {
    $logger       = new \Monolog\Logger('telemetry');
    $file_handler = new \Monolog\Handler\StreamHandler($c->log_dir . "/app.log", Monolog\Logger::DEBUG);
    $logger->pushHandler($file_handler);

    return $logger;
};

//setup flash messages
$container['flash'] = function () {
    return new \Slim\Flash\Messages();
};

//setup Slim\CSRF middleware
$container['csrf'] = function ($c) {
    return new \Slim\Csrf\Guard;
};

// retrieve countries in json from mledoze/countries package
$container['countries_dir'] = "../vendor/mledoze/countries";
$container['countries']     = json_decode(file_get_contents($container['countries_dir'].
                                                            "/dist/countries.json"), true);

// setup twig
$container['view'] = function ($c) {
    $view = new \Slim\Views\Twig([__DIR__ . '/../app/Templates'], [
      'cache' => $c['settings']['debug'] ? false : $c->cache_dir . '/twig',
      'debug' => $c['settings']['debug']
    ]);

    // Instantiate and add Slim specific extension
    $uri = str_replace(
        ['index.php', $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']],
        ['', ''],
        $c['request']->getUri()->getBaseUrl()
    );
    $view->addExtension(new Slim\Views\TwigExtension($c['router'], $uri));

    if ($c['settings']['debug']) {
        $view->addExtension(new DebugExtension());
    }

    // add some global to view
    $env = $view->getEnvironment();

    // add recaptcha sitekey
    $env->addGlobal('recaptchasitekey', $c['settings']['recaptcha']['sitekey']);

    // add countries geo data
    $env->addGlobal('countries', $c['countries'], true);

    //footer links
    $env->addGlobal('footer_links', $c->project->getFooterLinks());

    //app mode
    $env->addGlobal('mode', TELEMETRY_MODE);

    return $view;
};

//setup recaptcha
if (TELEMETRY_MODE == 'DEV') {
    $recaptcha = function ($request, $response, $next) {
        //does nothing
        return $next($request, $response);
    };
} else {
    $container[Captcha::class] = function ($c) {
        return new Captcha($c[ReCaptcha::class]);
    };
    $container[ReCaptcha::class] = function ($c) {
        return new ReCaptcha($c['settings']['recaptcha']['secret']);
    };
    $recaptcha = $app->getContainer()->get(Captcha::class);
}

// system error handling
$container['errorHandler'] = function ($c) {
 //CUSTOM Error Handler
    return function (
        \Slim\Http\Request $request,
        \Slim\Http\Response $response,
        \Exception $exception
    ) use ($c) {

       // log error
        $c->logger->error('error', [$exception->getMessage()]);

       // return json error
        if (strpos($request->getContentType(), 'application/json') !== false) {
            $answer = [
            'message' => 'Something went wrong!'
            ];

            if ($c['settings']['debug']) {
                $answer['message'] = $exception->getMessage();
            }

            return $response
            ->withStatus(500)
            ->withHeader('Content-Type', 'application/json')
            ->write(json_encode($answer));
        }

       // html error for production env
        if (!$c['settings']['debug']) {
            return $c['view']->render(
                $response,
                "errors/server.html"
            );
        }

       // if not special case, return slim default handler
        $error = new Slim\Handlers\Error($c['settings']['displayErrorDetails']);
        return $error->__invoke($request, $response, $exception);
    };
};

$container['data_dir'] = function ($c) {
    $dir = realpath(__DIR__ . '/../data');
    if ($dir === false || !is_writeable($dir)) {
        throw new \RuntimeException('Data directory "' . $dir . '" does not exists or is readonly!');
    }
    return $dir;
};

$container['cache_dir'] = function ($c) {
    $dir = realpath($c->data_dir . '/cache');
    if ($dir === false || !is_writeable($dir)) {
        throw new \RuntimeException('Cache directory "' . $dir . '" does not exists or is readonly!');
    }
    return $dir;
};

$container['log_dir'] = function ($c) {
    $dir = realpath($c->data_dir . '/logs');
    if ($dir === false || !is_writeable($dir)) {
        throw new \RuntimeException('Log directory "' . $dir . '" does not exists or is readonly!');
    }
    return $dir;
};

$container['cache'] = function ($c) {
    $cache = new \Symfony\Component\Cache\Adapter\FilesystemAdapter(
        'telemetry',
        0,
        $c->cache_dir
    );

    return $cache;
};

// php error handler
if (!$config['debug']) {
    $container['phpErrorHandler'] = function ($c) {
        return function ($request, $response, $error) use ($c) {
            $c->logger->error('error', [$error->getMessage()]);
            return $c['view']->render(
                $response,
                "errors/server.html"
            );
        };
    };
}


// manage page parameter for Eloquent Paginator
// @see https://github.com/mattstauffer/Torch/blob/master/components/pagination/index.php
Paginator::currentPageResolver(function ($pageName = 'page') {
    $page = isset($_REQUEST[$pageName]) ? $_REQUEST[$pageName] : 1;
    return $page;
});

// Set up a current path resolver so Eloquent paginator can generate proper links
// @see https://github.com/mattstauffer/Torch/blob/master/components/pagination/index.php
Paginator::currentPathResolver(function () {
    return isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '/';
});

/**
 * Trailing slash middleware
 */
$app->add(function ($request, $response, $next) {
    $uri = $request->getUri();
    $path = $uri->getPath();
    if ($path != '/' && substr($path, -1) == '/') {
        // permanently redirect paths with a trailing slash
        // to their non-trailing counterpart
        $uri = $uri->withPath(substr($path, 0, -1));

        if ($request->getMethod() == 'GET') {
            return $response->withRedirect((string)$uri, 301);
        } else {
            return $next($request->withUri($uri), $response);
        }
    }

    return $next($request, $response);
});
