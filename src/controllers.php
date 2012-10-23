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
        'SELECT count(*) as total_users, sum(bookmarks) as total_bookmarks FROM bookmark_statistics'
    );

    return $app['twig']->render('index.html', array('stats' => $stats));
})
->bind('homepage');

$app->get('/bookmarks', function (Request $request) use ($app) {
    $entityUrl = $app['session']->get('entity_url');

    if (!$entityUrl) {
        return new RedirectResponse($app['url_generator']->generate('homepage'));
    }

    $client = $app['tent.client']->getUserClient($entityUrl);

    $criteria = array('post_types' => 'http://www.beberlei.de/tent/bookmark/v0.0.1');

    $status = $request->query->get('mode', 'my');
    if ($status == 'my') {
        $criteria['entity'] = $entityUrl;
    }

    $posts = $client->getPosts(new TentPHP\PostCriteria($criteria));

    $posts = array_filter($posts, function ($post) use($entityUrl, $status) {
        return !($status == 'public' && isset($post['permissions']['public']) && !$post['permissions']['public']);
    });

    $app['db']->executeUpdate('UPDATE bookmark_statistics SET bookmarks = ? WHERE entity = ?', array(count($posts), $entityUrl));

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
    $app['db']->executeUpdate('UPDATE bookmark_statistics SET bookmarks = bookmarks+1 WHERE entity = ?', array($entityUrl));

    return new Response(json_encode(array('id' => $data['id'])), 200, array('Content-Type' => 'application/json'));

})->bind('save_bookmark');

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
        'http://www.beberlei.de/tent/bookmark/v0.0.1'
    ));

    $sql = "SELECT * FROM bookmark_statistics WHERE entity = ?";
    $data = $app['db']->fetchAssoc($sql, array($entityUrl));

    if (!$data) {
        $app['db']->insert('bookmark_statistics', array(
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

    $app['db']->executeUpdate('UPDATE bookmark_statistics SET last_login = NOW(), login_count = login_count + 1 WHERE entity = ?', array($app['session']->get('entity_url')));

    return new RedirectResponse($app['url_generator']->generate('bookmarks'));
})->bind('oauth_accept');

$app->error(function (\Exception $e, $code) use ($app) {
    if ($app['debug']) {
        return;
    }

    $page = 404 == $code ? '404.html' : '500.html';
    syslog(LOG_INFO, "Zelten Error: " . $e->getMessage());

    return new Response($app['twig']->render($page, array('code' => $code)), $code);
});

