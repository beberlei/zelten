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

$cacheTokenFile = sys_get_temp_dir() . "/cache.token";
if (file_exists($cacheTokenFile)) {
    $cacheToken = file_get_contents($cacheTokenFile);
    $cacheDir = sys_get_temp_dir() . "/" . $cacheToken;
} else {
    $cacheToken = filemtime(__DIR__ . "/../composer.json");
    $cacheDir = __DIR__ . "/../cache";
}

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
        'name'          => 'Zelten',
        'description'   => 'Bookmarks, Social Sync and other Tent related services',
        'url'           => 'http://zelten.cc',
        'redirect_uris' => array(
            'http://zelten.cc/oauth/accept',
            'http://zelten.eu1.frbit.net/oauth/accept',
            'http://www.zelten.cc/oauth/accept',
            // for local development
            'http://zelten/oauth/accept',
            'http://zelten/index_dev.php/oauth/accept',
        ),
        'scopes'        => array(
            'read_posts'       => 'Read Posts',
            'write_posts'      => 'Write Posts',
            'read_groups'      => 'Read Groups',
            'write_groups'     => 'Write Groups',
            'read_followings'  => 'List Followings',
            'write_followings' => 'Follow New People',
            'read_permissions' => 'Read Permissions',
        ),
    )
));
$app->register(new FormServiceProvider());
$app->register(new SessionServiceProvider());
$app->register(new UrlGeneratorServiceProvider());
$app->register(new ValidatorServiceProvider());
$app->register(new TwigServiceProvider(), array(
    'twig.path'    => array(__DIR__.'/../templates'),
    'twig.options' => array('cache' => $cacheDir . '/twig'),
));
$app['twig'] = $app->share($app->extend('twig', function($twig, $app) use ($cacheToken) {
    // add custom globals, filters, tags, ...
    $twig->addGlobal('cachetoken', $cacheToken);
    $twig->addExtension(new \Zelten\TwigExtension($app));

    return $twig;
}));

$app['twitter.options'] = array(
    'key'    => null,
    'secret' => null,
);

$app['twitter'] = $app->share(function($app) {
    $options = $app['twitter.options'];
    return new \TwitterOAuth\Api($options['key'], $options['secret']);
});

$app['zelten.stream'] = $app->share(function ($app) {
    return new \Zelten\Stream\StreamRepository(
        $app['tent.client'],
        $app['url_generator'],
        $app['zelten.profile'],
        $app['session']->get('entity_url')
    );
});

$app['zelten.favorite'] = $app->share(function ($app) {
    return new \Zelten\Stream\FavoriteRepository($app['db'], $app['tent.client']);
});

$app['zelten.profile'] = $app->share(function ($app) {
    return new \Zelten\Profile\ProfileRepository($app['db'], $app['tent.client']);
});

return $app;
