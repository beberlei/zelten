<?php

use Silex\Application;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Silex\Provider\ValidatorServiceProvider;
use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\FormServiceProvider;
use Silex\Provider\TranslationServiceProvider;

$app = new Application();
$app->register(new DoctrineServiceProvider(), array(
    'db.options' => array(
        'driver'   => 'pdo_mysql',
        'user'     => 'root',
        'password' => '',
        'hostname' => 'localhost',
        'dbname'   => 'zelten',
    ),
));
$app->register(new TranslationServiceProvider(), array(
    'locale_fallback' => 'en',
));
$app->register(new FormServiceProvider());
$app->register(new SessionServiceProvider());
$app->register(new UrlGeneratorServiceProvider());
$app->register(new ValidatorServiceProvider());
$app->register(new TwigServiceProvider(), array(
    'twig.path'    => array(__DIR__.'/../templates'),
    'twig.options' => array('cache' => __DIR__.'/../cache'),
));
$app['twig'] = $app->share($app->extend('twig', function($twig, $app) {
    // add custom globals, filters, tags, ...

    return $twig;
}));

$app['tent.application.options'] = array(
    'name'          => 'Zelten Bookmarks',
    'description'   => 'Save, share and manage your bookmarks using your Tent Profile',
    'url'           => 'http://zelten.beberlei.de',
    'redirect_uris' => array('http://zelten.beberlei.de/oauth/accept'),
    'scopes'        => array(
        'read_posts'  => 'Read Bookmarks from your Tent Account',
        'write_posts' => 'Add and update Bookmarks',
    ),
);

$app['tent.application'] = $app->share(function($app) {
    return new TentPHP\Application($app['tent.application.options']);
});

$app['tent.application_state'] = $app->share(function ($app) {
    return new TentPHP\DBAL\DoctrineDBALState($app['db']);
});

$app['tent.client'] = $app->share(function($app) {
    $httpClient = new Guzzle\Http\Client();

    return new TentPHP\Client(
        $app['tent.application'],
        $httpClient,
        $app['tent.application_state']
    );
});

return $app;
