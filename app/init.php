<?php

use GaletteTelemetry\Twig\CsrfExtension;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use DI\Bridge\Slim\Bridge;
use DI\ContainerBuilder;
use Illuminate\Pagination\Paginator;
use Geggleto\Service\Captcha;
use Psr\Container\ContainerInterface;
use ReCaptcha\ReCaptcha;
use Slim\Psr7\Response;
use Slim\Routing\RouteParser;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
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

//init slim
$builder = new ContainerBuilder();
$builder->useAttributes(true);
$builder->addDefinitions($config);
$container = $builder->build();

$app = Bridge::create($container);
$container = $app->getContainer();

$routeParser = $app->getRouteCollector()->getRouteParser();
$container->set(RouteParser::class, $routeParser);

$app->setBasePath((function () {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $uri = (string)parse_url('http://a' . $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (stripos($uri, $_SERVER['SCRIPT_NAME']) === 0) {
        return dirname($_SERVER['SCRIPT_NAME']);
    }
    if ($scriptDir !== '/' && stripos($uri, $scriptDir) === 0) {
        return $scriptDir;
    }
    return '';
})());

$app->addRoutingMiddleware();

// init slim
/*$app       = new \Slim\App(["settings" => $config]);
$container = $app->getContainer();*/

$container->set('mail_from', $config['mail_from']);
$container->set('mail_admin', $config['mail_admin']);
$container->set('is_debug', $config['debug']);

$container->set(
    'project',
    function ($c) use ($config) {
        $project = new \GaletteTelemetry\Project($c->get('logger'));
        $project->setConfig($config['project']);
        return $project;
    }
);

// setup db connection
$capsule = new \Illuminate\Database\Capsule\Manager;
$capsule->addConnection($config['db']);
$capsule->setAsGlobal();
$capsule->bootEloquent();

// setup monolog
$container->set(
    'logger',
    function ($c) {
        $logger       = new \Monolog\Logger('telemetry');
        $file_handler = new \Monolog\Handler\StreamHandler(
            $c->get('log_dir') . "/app.log",
            Monolog\Logger::DEBUG
        );
        $logger->pushHandler($file_handler);

        return $logger;
    }
);

//setup flash messages
$container->set(
    'flash',
    function () {
        return new \Slim\Flash\Messages();
    }
);

//setup Slim\CSRF middleware
$container->set(
    'csrf',
    function (ContainerInterface $c) use ($app) {
        $responseFactory = $app->getResponseFactory();
        $storage = null;
        $guard = new \Slim\Csrf\Guard(
            $responseFactory,
            'csrf',
            $storage,
            null,
            200,
            16,
            true
        );
        return $guard;
    }
);

// retrieve countries in json from mledoze/countries package
$container->set('countries_dir', "../vendor/mledoze/countries");
$container->set(
    'countries',
    json_decode(
        file_get_contents(
            $container->get('countries_dir') . "/dist/countries.json"
        ),
        true
    )
);

//countries names
$container->set(
    'countries_names',
    function ($c) {
        $names = [];
        foreach ($c->get('countries') as $country) {
            $names[strtolower($country['cca2'])] = $country['name']['common'];
        }
        return $names;
    }
);

// setup twig
$container->set(
    Twig::class,
    function ($c) use ($config) {
        $view = Twig::create(
            [__DIR__ . '/../app/Templates'],
            [
                'cache' => $config['debug'] ? false : $c->get('cache_dir') . '/twig',
                'debug' => $config['debug'],
                //'strict_variables' => $config['debug']
            ]
        );

        // Instantiate and add Slim specific extension
        /*$uri = str_replace(
            ['index.php', $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']],
            ['', ''],
            $c['request']->getUri()->getBaseUrl()
        );
        $view->addExtension(new Slim\Views\TwigExtension($c->get('router'), $uri));*/
        $view->addExtension(new CsrfExtension($c->get('csrf')));

        if ($config['debug']) {
            $view->addExtension(new DebugExtension());
        }

        // add some global to view
        $env = $view->getEnvironment();

        // add recaptcha sitekey
        $env->addGlobal('recaptchasitekey', $config['recaptcha']['sitekey']);

        $env->addGlobal('flash', $c->get('flash'));

        // add countries geo data
        $env->addGlobal('countries', $c->get('countries'));
        $env->addGlobal('countries_names', $c->get('countries_names'));

        //footer links
        $env->addGlobal('footer_links', $c->get('project')->getFooterLinks());
        //social links
        $env->addGlobal('social_links', $c->get('project')->getSocialLinks());

        //app mode
        $env->addGlobal('mode', TELEMETRY_MODE);

        //dark css state
        $env->addGlobal('darkcss_created', $c->get('darkcss_created'));
        $env->addGlobal('darkcss_enabled', ($_COOKIE['galettetelemetry_dark_mode'] ?? 0) == 1);

        return $view;
    }
);

//setup recaptcha
if (TELEMETRY_MODE == 'DEV') {
    $recaptcha = function (Request $request, RequestHandler $handler) {
        //does nothing
        $response = $handler->handle($request);
        return $response;
    };
} else {
    $container->set(
        Captcha::class,
        function ($c) {
            return new Captcha($c->get(ReCaptcha::class));
        }
    );
    $container->set(
        ReCaptcha::class,
        function ($c) use ($config) {
            return new ReCaptcha($config['recaptcha']['secret']);
        }
    );
    $recaptcha = $container->get(Captcha::class);
}

// system error handling
/*$customErrorHandler = function (
    ServerRequestInterface $request,
    Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails,
    ?LoggerInterface $logger = null
) use ($container, $app, $config) {
    // log error
    $container->get('logger')->error('error', [$exception->getMessage()]);
    $response = $app->getResponseFactory()->createResponse();

    // return json error
    if (strpos($request->getHeaderLine('Content-Type'), 'application/json') !== false) {
        $answer = [
        'message' => 'Something went wrong!'
        ];

        if ($config['debug']) {
            $answer['message'] = $exception->getMessage();
        }

        return $response
            ->withStatus(500)
            ->withHeader('Content-Type', 'application/json')
            ->write(json_encode($answer));
    }

    // html error for production env
    if (!$config['debug']) {
        return $config->get(Twig::class)->render(
            $response,
            "errors/server.html"
        );
    }

    // if not special case, return slim default handler
    $error = new ErrorHandler();
    return $error->__invoke($request, $response, $exception);
};
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setDefaultErrorHandler($customErrorHandler);*/
$app->addErrorMiddleware(true, true, true);

$container->set(
    'data_dir',
    function ($c) {
        $dir = realpath(__DIR__ . '/../data');
        if ($dir === false || !is_writeable($dir)) {
            throw new \RuntimeException('Data directory "' . $dir . '" does not exists or is readonly!');
        }
        return $dir;
    }
);

$container->set(
    'cache_dir',
    function ($c) {
        $dir = realpath($c->get('data_dir') . '/cache');
        if ($dir === false || !is_writeable($dir)) {
            throw new \RuntimeException('Cache directory "' . $dir . '" does not exists or is readonly!');
        }
        return $dir;
    }
);

$container->set(
    'log_dir',
    function ($c) {
        $dir = realpath($c->get('data_dir') . '/logs');
        if ($dir === false || !is_writeable($dir)) {
            throw new \RuntimeException('Log directory "' . $dir . '" does not exists or is readonly!');
        }
        return $dir;
    }
);

$container->set(
    'cache',
    function ($c) {
        $cache = new \Symfony\Component\Cache\Adapter\FilesystemAdapter(
            'telemetry',
            0,
            $c->get('cache_dir')
        );

        return $cache;
    }
);

$container->set(
    'darkcss_created',
    function ($c) {
        $cache = $c->get('cache');
        if ($cache->hasItem('darkcss')) {
            return true;
        } else {
            return false;
        }
    }
);

// php error handler
/*if (!$config['debug']) {
    $container['phpErrorHandler'] = function ($c) {
        return function ($request, $response, $error) use ($c) {
            $c->get('logger')->error('error', [$error->getMessage()]);
            return $c->get(Twig::class)->render(
                $response,
                "errors/server.html"
            );
        };
    };
}*/


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

// Add Routing Middleware
$app->addRoutingMiddleware();
$app->add(TwigMiddleware::createFromContainer($app, Twig::class));

/**
 * Trailing slash middleware
 */
$app->add(function (Request $request, RequestHandler $handler) {
    $uri = $request->getUri();
    $path = $uri->getPath();

    if ($path != '/' && substr($path, -1) == '/') {
        // recursively remove slashes when its more than 1 slash
        $path = rtrim($path, '/');

        // permanently redirect paths with a trailing slash
        // to their non-trailing counterpart
        $uri = $uri->withPath(substr($path, 0, -1));

        if ($request->getMethod() == 'GET') {
            $response = new Response();
            return $response
                ->withHeader('Location', (string) $uri)
                ->withStatus(301);;
        } else {
            $request = $request->withUri($uri);
        }
    }

    return $handler->handle($request);
});
