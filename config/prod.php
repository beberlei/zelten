<?php

use Zelten\Config\YamlConfigLoader;
use TentPHP\DBAL\DoctrineUserStorage;

$parametersFile = __DIR__ . "/parameters.yml";

if ( ! file_exists($parametersFile)) {
    throw new \RuntimeException("Parameters File 'config/parameters.yml' is missing. Copy the config/defaults.yml to config/parameters.yml and set all the required values.");
}

$loader = new YamlConfigLoader(__DIR__ . "/defaults.yml", $parametersFile);
$config = $loader->create();

if ($config->get('db_type') === 'pdo_sqlite') {
    $app['db.options'] = array(
        'driver' => 'pdo_sqlite',
        'path'   => __DIR__ . '/../zelten.db',
    );
} else {
    $app['db.options'] = array(
        'driver'   => $config->get('db_type'),
        'host'     => $config->get('db_host'),
        'user'     => $config->get('db_user'),
        'password' => $config->get('db_password'),
        'dbname'   => $config->get('db_name'),
    );
}

$app['twitter.options'] = array(
    'key'    => $config->get('twitter_key'),
    'secret' => $config->get('twitter_secret'),
);

// CHANGE!!!
$app['appsecret'] = $config->get('app_secret');
DoctrineUserStorage::registerTentEncryptionStringType($app['appsecret']);
$app['tent.application.options'] = $config->get('zelten');
$app['xhprof'] = $config->get('xhprof');

// only if the current request host equals the given host,
// a notification url will be appended to the login url.
$app['notification_domain'] = $config->get('notification_url');

