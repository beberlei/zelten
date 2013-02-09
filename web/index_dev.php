<?php

use Symfony\Component\ClassLoader\DebugClassLoader;
use Symfony\Component\HttpKernel\Debug\ErrorHandler;
use Symfony\Component\HttpKernel\Debug\ExceptionHandler;

require_once __DIR__.'/../vendor/autoload.php';

ini_set('display_errors', 1);
error_reporting(-1);
DebugClassLoader::enable();
ErrorHandler::register();
if ('cli' !== php_sapi_name()) {
    ExceptionHandler::register();
}

if (extension_loaded('xhprof')) {
    xhprof_enable();
}

$app = require __DIR__.'/../src/app.php';
require __DIR__.'/../config/dev.php';
require __DIR__.'/../src/controllers.php';
$app->run();

if (extension_loaded('xhprof')) {
    $xhprof_data = xhprof_disable();

    if ($app['xhprof']) {
        include_once $app['xhprof']['root'] . "/xhprof_lib/utils/xhprof_lib.php";
        include_once $app['xhprof']['root'] . "/xhprof_lib/utils/xhprof_runs.php";

        // save raw data for this profiler run using default
        // implementation of iXHProfRuns.
        $xhprof_runs = new XHProfRuns_Default();

        // save the run under a namespace "xhprof_foo"
        $run_id = $xhprof_runs->save_run($xhprof_data, "zelten");
        echo '<div style="background-color:#fff;position:absolute;top:0;left:0;border:1px solid #000;z-index:1000"><a href="'.$app['xhprof']['url'] . '/index.php?run=' . $run_id . '&source=zelten">XHPROF</a></div>';
    }
}
