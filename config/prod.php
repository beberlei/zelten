<?php

// configure your app for the production environment

function envvar($name, $default)
{
    if (isset($_SERVER[$name])) {
        return $_SERVER[$name];
    }

    return $default;
}

if (envvar('DB_DRIVER', 'pdo_msyql') == 'pdo_sqlite') {
    $app['db.options'] = array(
        'driver' => 'pdo_sqlite',
        'path'   => __DIR__ . '/../zelten.db',
    );
} else {
    $app['db.options'] = array(
        'driver'   => 'pdo_mysql',
        'user'     => envvar('DB_USER', 'root'),
        'host'     => envvar('DB_HOST', 'localhost'),
        'password' => envvar('DB_PASSWORD', ''),
        'dbname'   => envvar('DB_NAME', 'zelten'),
    );
}

$app['twitter.options'] = array(
    'key'    => envvar('TWITTER_KEY', null),
    'secret' => envvar('TWITTER_SECRET', null),
);

// CHANGE!!!
$app['appsecret']            = envvar('APPSECRET', 'OoH8eevahThahyiinge');
TentPHP\DBAL\DoctrineUserStorage::registerTentEncryptionStringType($app['appsecret']);

// only if the current request host equals the given host,
// a notification url will be appended to the login url.
$app['notification_domain'] = envvar('NOTIFICATION_DOMAIN', false);
