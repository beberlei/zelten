<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Zelten\Bookmark;
use Zelten\BookmarkParser;
use Zelten\Form\BookmarkType;

$app->get('/', function () use ($app) {
    $stats = $app['db']->fetchAssoc(
        'SELECT count(*) as total_users, sum(bookmarks) as total_bookmarks FROM users'
    );

    return $app['twig']->render('index.html', array('stats' => $stats));
})
->bind('homepage');

$app->get('/bookmarks', function (Request $request) use ($app) {
    $entityUrl = $app['session']->get('entity_url');

    if (!$entityUrl) {
        return new RedirectResponse($app['url_generator']->generate('homepage'));
    }

    $client   = $app['tent.client']->getUserClient($entityUrl);
    $criteria = array('post_types' => 'http://www.beberlei.de/tent/bookmark/v0.0.1');

    $status = $request->query->get('mode', 'my');

    if ($status == 'my') {
        $criteria['entity'] = $entityUrl;
    }

    $posts = $client->getPosts(new TentPHP\PostCriteria($criteria));

    $posts = array_values(array_filter($posts, function ($post) use($entityUrl, $status) {
        return !($status == 'public' && isset($post['permissions']['public']) && !$post['permissions']['public']);
    }));

    $app['db']->executeUpdate('UPDATE users SET bookmarks = ? WHERE entity = ?', array(count($posts), $entityUrl));

    if ($request->isXmlHttpRequest()) {
        return new Response(json_encode($posts), 200, array('Content-Type' => 'application/json'));
    }

    return $app['twig']->render('bookmarks.html', array(
        'posts' => $posts,
        'form'  => $app['form.factory']->create(new BookmarkType())->createView()
    ));
})->bind('bookmarks');

$app->get('/bookmarks/parse', function(Request $request) use ($app) {
    $url      = $request->query->get('url');
    $bookmark = new Bookmark($url);

    if (!$bookmark->getUrl()) {
        return new Response('{"error": "Invalid url given"}', 400, array('Content-Type' => 'application/json'));
    }

    $client = new Guzzle\Http\Client();
    $html = $client->get($bookmark->getUrl())->send()->getBody();

    $parser = new BookmarkParser();
    $parser->enrich($bookmark, $html);

    return new Response(json_encode(array(
        'bookmark' => $bookmark->toArray(),
        'images'   => $parser->extractAllImages($bookmark->getUrl(), $html)
    )), 200, array('Content-Type' => 'application/json'));

})->bind('bookmark_parse');

function form_errors_array($form, array $errors = array())
{
    foreach ($form->getErrors() as $error) {
        $errors[] = $error->getMessage();
    }

    foreach ($form->getChildren() as $child) {
        $errors = array_merge($errors, form_errors_array($child));
    }

    return $errors;
}

$app->get('/bookmarks/{id}', function($id) use ($app) {
    $entityUrl = $app['session']->get('entity_url');

    if (!$entityUrl) {
        return new RedirectResponse($app['url_generator']->generate('homepage'));
    }

    $client    = $app['tent.client']->getUserClient($entityUrl);

    $post = $client->getPost($id);

    if (!$post) {
        throw new NotFoundHttpException();
    }


    $bookmark = new Bookmark($post['content']['url']);

    if (isset($post['content']['content'])) {
        $bookmark->setContent($post['content']['content']);
    } else {
        $client = new Guzzle\Http\Client();
        $html   = $client->get($bookmark->getUrl())->send()->getBody();

        $parser = new BookmarkParser();
        $parser->readablityContent($bookmark, $html);
    }

    return new Response($bookmark->getContent(), 200);
});

$app->delete('/bookmarks/{id}', function ($id) use ($app) {
    $entityUrl = $app['session']->get('entity_url');

    if (!$entityUrl) {
        return new RedirectResponse($app['url_generator']->generate('homepage'));
    }

    $client    = $app['tent.client']->getUserClient($entityUrl);

    $client->deletePost($id);

    return new Response('', 204);
});

$app->post('/bookmarks', function(Request $request) use ($app) {
    $entityUrl = $app['session']->get('entity_url');

    if (!$entityUrl) {
        return new RedirectResponse($app['url_generator']->generate('homepage'));
    }

    $bookmark = new Bookmark();

    $data = json_decode($request->getContent(), true);

    $form = $app['form.factory']->create(new BookmarkType(), $bookmark);
    $form->bind(array_map('strip_tags', $data['content']));

    if ( ! $form->isValid()) {
        $errors = array('error' => true, 'messages' => form_errors_array($form));
        return new Response(json_encode($errors), 400, array('Content-Type' => 'application/json'));
    }

    $client = $app['tent.client']->getUserClient($entityUrl);
    $post   = \TentPHP\Post::create('http://www.beberlei.de/tent/bookmark/v0.0.1');

    if ($bookmark->getPrivacy() == 'public') {
        $post->markPublic();
    } else {
        $post->markPrivate();
    }

    $post->setContent($bookmark->toArray());

    $data = $client->createPost($post);
    $app['db']->executeUpdate('UPDATE users SET bookmarks = bookmarks+1 WHERE entity = ?', array($entityUrl));

    return new Response(json_encode(array('id' => $data['id'])), 200, array('Content-Type' => 'application/json'));

})->bind('save_bookmark');

$app->get('/socialsync', function() use ($app) {
    $entityUrl = $app['session']->get('entity_url');

    if (!$entityUrl) {
        return new RedirectResponse($app['url_generator']->generate('homepage'));
    }

    $userRow    = $app['db']->fetchAssoc('SELECT * FROM users WHERE entity = ?', array($entityUrl));
    $hasTwitter = $userRow['twitter_oauth_token'] && $userRow['twitter_oauth_secret'];

    return $app['twig']->render('socialsync.html', array(
        'twitter_connected' => $hasTwitter
    ));
})->bind('socialsync');

$app->get('/socialsync/connect/twitter', function() use ($app) {
    $entityUrl = $app['session']->get('entity_url');

    if (!$entityUrl) {
        return new RedirectResponse($app['url_generator']->generate('homepage'));
    }

    $twitter = $app['twitter'];

    $request_token = $twitter->getRequestToken($app['url_generator']->generate('socialsync_accept_twitter', array(), true));

    $token = $request_token['oauth_token'];
    $app['session']->set('tw_oauth_token', $token);
    $app['session']->set('tw_oauth_token_secret', $request_token['oauth_token_secret']);

    switch ($twitter->http_code) {
      case 200:
        return new RedirectResponse($twitter->getAuthorizeURL($token));
      default:
        return new RedirectResponse($app['url_generator']->generate('homepage'));
    }
})->bind('socialsync_connect_twitter');

$app->get('/socialsync/oauth/accept/twitter', function(Request $request) use ($app) {
    $entityUrl = $app['session']->get('entity_url');

    if (!$entityUrl) {
        return new RedirectResponse($app['url_generator']->generate('homepage'));
    }

    $token = $request->query->get('oauth_token');
    if ($token !== $app['session']->get('tw_oauth_token')) {
        return new RedirectResponse($app['url_generator']->generate('homepage'));
    }

    $twitter = $app['twitter'];
    $twitter->setTokens($token, $app['session']->get('tw_oauth_token_secret'));
    $accessToken = $twitter->getAccessToken($request->query->get('oauth_verifier'));

    $query = "UPDATE users SET twitter_oauth_token = ?, twitter_oauth_secret = ? WHERE entity = ?";
    $app['db']->executeUpdate($query, array($accessToken['oauth_token'], $accessToken['oauth_token_secret'], $entityUrl));

    return new RedirectResponse($app['url_generator']->generate('socialsync'));
})->bind('socialsync_accept_twitter');

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
    $post       = json_decode($request->getContent(), true);
    $entityUrl = $post['entity'];

    if ($request->query->get('hash') !== hash_hmac('sha256', $post['entity'], $app['appsecret'])) {
        return new Response('', 403);
    }

    $client = $app['tent.client']->getUserClient($post['entity']);
    $client->validateMacAuthorizationHeader($app['url_generator']->generate('hook'));

    $serverPost = $client->getPost($post['id']);

    if ($serverPost['content'] != $post['content'] ||
        $serverPost['published_at'] != $post['published_at'] ||
        ($post['published_at']+10 < time())) {

        return new Response('', 202);
    }

    // do stuff! hacked now. make it event listener and such
    $userRow = $app['db']->fetchAssoc('SELECT * FROM users WHERE entity = ?', array($entityUrl));
    if ($userRow && $post['type'] == 'https://tent.io/types/post/status/v0.1.0') {
        $hasTwitter = $userRow['twitter_oauth_token'] && $userRow['twitter_oauth_secret'];

        if ($hasTwitter) {
            $twitter = $app['twitter'];
            $twitter->setTokens($userRow['twitter_oauth_token'], $userRow['twitter_oauth_secret']);
            $twitter->post('statuses/update', array('status' => $post['content']['text']));
        }
    }

    return new Response('', 201);
});

$app->error(function (\Exception $e, $code) use ($app) {
    if ($app['debug']) {
        return;
    }

    error_log(get_class($e) . ": " . $e->getMessage() . " " . $e->getTraceAsString());

    $page = 404 == $code ? '404.html' : '500.html';

    return new Response($app['twig']->render($page, array('code' => $code)), $code);
});

