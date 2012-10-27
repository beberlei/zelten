<?php

use Silex\Application;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Silex\Provider\ValidatorServiceProvider;
use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\FormServiceProvider;
use Silex\Provider\TranslationServiceProvider;
use TentPHP\Silex\TentServiceProvider;

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
$app->register(new TentServiceProvider(), array(
    'tent.application.options' => array(
        'name'          => 'Zelten Bookmarks',
        'description'   => 'Save, share and manage your bookmarks using your Tent Profile',
        'url'           => 'http://zelten.eu1.frbit.net/login',
        'redirect_uris' => array(
            'http://zelten.eu1.frbit.net/oauth/accept',
            'http://zelten/oauth/accept',
            'http://zelten/index_dev.php/oauth/accept',
        ),
        'scopes'        => array(
            'read_posts'  => 'Read Bookmarks from your Tent Account',
            'write_posts' => 'Add and update Bookmarks',
        ),
    )
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

$app['twitter.options'] = array(
    'key'    => null,
    'secret' => null,
);

$app['twitter'] = $app->share(function($app) {
    $options = $app['twitter.options'];
    return new EpiTwitter($options['key'], $options['secret']);
});

return $app;
