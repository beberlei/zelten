<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$app->post('/hook', function(Request $request) use ($app) {
    $post      = json_decode($request->getContent(), true);
    $entityUrl = $post['entity'];

    if ($request->query->get('hash') !== hash_hmac('sha256', $post['entity'], $app['appsecret'])) {
        return new Response('', 403);
    }

    $client = $app['tent.client']->getUserClient($post['entity']);
    #$client->validateMacAuthorizationHeader($app['url_generator']->generate('hook'));

    $serverPost = $client->getPost($post['id']);

    if ($serverPost['content'] != $post['content'] ||
        $serverPost['published_at'] != $post['published_at'] ||
        ($post['published_at']+10 < time())) {

        return new Response('', 202);
    }

    // do stuff! hacked now. make it event listener and such
    $userRow = $app['db']->fetchAssoc('SELECT * FROM users WHERE entity = ?', array($entityUrl));
    if ($userRow && $post['type'] == 'https://tent.io/types/post/status/v0.1.0') {
        $sync    = strpos($post['content']['text'], '#social') !== false;
        $message = str_replace('#social', '', $post['content']['text']);

        $hasTwitter = $userRow['twitter_oauth_token'] && $userRow['twitter_oauth_secret'];

        if ($sync && $hasTwitter) {
            $twitter = $app['twitter'];
            $twitter->setTokens($userRow['twitter_oauth_token'], $userRow['twitter_oauth_secret']);
            $twitter->post('statuses/update', array('status' => $message));
        }
    }

    return new Response('', 201);
})->bind('hook');

$app->mount('/', new \Zelten\Core\Controller());
$app->mount('/stream', new \Zelten\Stream\Controller());
$app->mount('/socialsync', new \Zelten\SocialSync\Controller());
$app->mount('/bookmarks', new \Zelten\Bookmarks\Controller());
$app->mount('/groups', new \Zelten\Groups\Controller());
$app->mount('/profile', new \Zelten\Profile\Controller());

$app->error(function (\Exception $e, $code) use ($app) {
    if ($app['debug']) {
        return;
    }

    file_put_contents("/tmp/zelten", get_class($e) . ": " . $e->getMessage() . " " . $e->getTraceAsString());

    $page = 404 == $code ? '404.html' : '500.html';

    return new Response($app['twig']->render($page, array('code' => $code)), $code);
});

