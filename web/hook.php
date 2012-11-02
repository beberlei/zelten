<?php

if (!isset($_SERVER['HOOK'])) {
    header("HTTP/1.1 403 Forbidden");
    exit;
}

header( 'Content-type: text/json' );
if ( ! isset( $_SERVER[ 'HTTP_X_FRBIT_TOKEN' ] ) || $_SERVER[ 'HTTP_X_FRBIT_TOKEN' ] != $_SERVER['HOOK'] ) {
    print json_encode(array(
        'status'  => 'fail',
        'message' => 'illegal login'
    ) );
}

$before = json_decode( $_POST[ 'before' ] );
$after  = json_decode( $_POST[ 'after' ] );

file_put_contents(sys_get_temp_dir() . "/cache.token", $after->commit);

print json_encode(array(
    'status'  => 'ok',
    'message' => 'Busted cache'
) );

