<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$app->get('/', function () use ($app) {
    return $app['twig']->render('index.html', array());
})
->bind('homepage');

$app->post('/login', function (Request $request) use ($app) {
    $entityUrl = $request->request->get('entity_url');

    if (!$entityUrl) {
        return new RedirectResponse($app['url_generator']->generate('homepage'));
    }

    $loginUrl = $app['tent.client']->getLoginUrl($entityUrl, null, null, null, array(
        'http://www.beberlei.de/tent/v0.0.1/bookmark'
    ));

    return new RedirectResponse($loginUrl);
})->bind('login');

$app->get('/oauth/accept', function(Request $request) use ($app) {
    $app['tent.client']->authorize(
        $request->query->get('state'),
        $request->query->get('code')
    );

    return new RedirectResponse($app['url_generator']->generate('bookmarks'));
})->bind('oauth_accept');

$app->error(function (\Exception $e, $code) use ($app) {
    if ($app['debug']) {
        return;
    }

    $page = 404 == $code ? '404.html' : '500.html';

    return new Response($app['twig']->render($page, array('code' => $code)), $code);
});
