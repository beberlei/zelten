<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$app->get('/', function () use ($app) {
    $stats = $app['db']->fetchAssoc(
        'SELECT count(*) as total_users, sum(bookmarks) as total_bookmarks FROM users'
    );

    return $app['twig']->render('index.html', array('stats' => $stats));
})
->bind('homepage');

$app->get('/logout', function() use ($app) {
    $entityUrl = $app['session']->set('entity_url', null);
    return new RedirectResponse($app['url_generator']->generate('homepage'));
})->bind('logout');

$app->post('/login', function (Request $request) use ($app) {
    $entityUrl = $app['session']->get('entity_url');

    if ($entityUrl) {
        return new RedirectResponse($app['url_generator']->generate('bookmarks'));
    }

    $entityUrl = $request->request->get('entity_url');

    if (!$entityUrl) {
        return new RedirectResponse($app['url_generator']->generate('homepage'));
    }

    if (strpos($entityUrl, ".") === false) {
        $entityUrl = "https://" . $entityUrl . ".tent.is";
    } else if (strpos($entityUrl, "http") === false) {
        $entityUrl = "https://" . $entityUrl;
    }

    $callbackUrl = $app['url_generator']->generate('oauth_accept', array(), true);
    $loginUrl    = $app['tent.client']->getLoginUrl($entityUrl, null, $callbackUrl, null, array(
        'http://www.beberlei.de/tent/bookmark/v0.0.1',
        'https://tent.io/types/post/status/v0.1.0',
    ), 'http://zelten.eu1.frbit.net/hook?hash='.hash_hmac('sha256', $entityUrl, $app['appsecret']));

    $sql = "SELECT * FROM users WHERE entity = ?";
    $data = $app['db']->fetchAssoc($sql, array($entityUrl));

    if (!$data) {
        $app['db']->insert('users', array(
            'entity' => $entityUrl,
            'last_login' => time(),
        ));
    }

    return new RedirectResponse($loginUrl);
})->bind('login');

$app->get('/oauth/accept', function(Request $request) use ($app) {
    $app['session']->set('entity_url', $app['tent.client']->authorize(
        $request->query->get('state'),
        $request->query->get('code')
    ));

    $app['db']->executeUpdate('UPDATE users SET last_login = NOW(), login_count = login_count + 1 WHERE entity = ?', array($app['session']->get('entity_url')));

    return new RedirectResponse($app['url_generator']->generate('bookmarks'));
})->bind('oauth_accept');

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

$app->mount('/socialsync', new \Zelten\SocialSync\Controller());
$app->mount('/bookmarks', new \Zelten\Bookmarks\Controller());

$app->error(function (\Exception $e, $code) use ($app) {
    if ($app['debug']) {
        return;
    }

    error_log(get_class($e) . ": " . $e->getMessage() . " " . $e->getTraceAsString());

    $page = 404 == $code ? '404.html' : '500.html';

    return new Response($app['twig']->render($page, array('code' => $code)), $code);
});

