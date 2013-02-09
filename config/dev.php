<?php

use Silex\Provider\MonologServiceProvider;
use Silex\Provider\WebProfilerServiceProvider;

use Zelten\Util\Profiler\GuzzleDataCollector;

// include the prod configuration
require __DIR__.'/prod.php';

// enable the debug mode
$app['debug'] = true;

$app->register(new MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/../silex.log',
));

$app->register($p = new WebProfilerServiceProvider(), array(
    'profiler.cache_dir' => __DIR__.'/../cache/profiler',
    ));
$app->mount('/_profiler', $p);

$templates = $app['data_collector.templates'];
$templates[] = array('guzzle', 'Toolbar/guzzle.html.twig');
$app['data_collector.templates'] = $templates;

$collectors = $app['data_collectors'];
$collectors['guzzle'] = $app->share(function ($app) {
    return new GuzzleDataCollector($app['tent.client.history']);
});
$app['data_collectors'] = $collectors;

$app['tent.client.history'] = $app->share(function ($app) {
    $plugin = new \Guzzle\Plugin\History\HistoryPlugin;
    $plugin->setLimit(1000);
    return $plugin;
});
$app['tent.http.client'] = $app->share($app->extend('tent.http.client', function($client, $app) {
    $client->addSubscriber($app['tent.client.history']);

    return $client;
}));
