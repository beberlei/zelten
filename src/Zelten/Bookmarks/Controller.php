<?php

namespace Zelten\Bookmarks;

use Silex\ControllerProviderInterface;
use Silex\Application;

use Zelten\Bookmark;
use Zelten\BookmarkParser;
use Zelten\Form\BookmarkType;

use TentPHP\PostCriteria;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Controller implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];
        $controllers->get('/', array($this, 'indexAction'))->bind('bookmarks');
        $controllers->get('/parse', array($this, 'parseAction'))->bind('bookmark_parse');
        $controllers->delete('/{id}', array($this, 'deleteAction'));
        $controllers->post('/', array($this, 'saveAction'))->bind('save_bookmark');

        return $controllers;
    }

    function indexAction(Request $request, Application $app)
    {
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

        $posts = $client->getPosts(new PostCriteria($criteria));

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
    }

    public function parseAction(Request $request, Application $app)
    {
        $url      = $request->query->get('url');
        $bookmark = new Bookmark($url);

        if (!$bookmark->getUrl()) {
            return new Response('{"error": "Invalid url given"}', 400, array('Content-Type' => 'application/json'));
        }

        $client = new \Guzzle\Http\Client();
        $html = $client->get($bookmark->getUrl())->send()->getBody();

        $parser = new BookmarkParser();
        $parser->enrich($bookmark, $html);

        return new Response(json_encode($bookmark->toArray()), 200, array('Content-Type' => 'application/json'));
    }

    public function deleteAction($id, Application $app)
    {
        $entityUrl = $app['session']->get('entity_url');

        if (!$entityUrl) {
            return new RedirectResponse($app['url_generator']->generate('homepage'));
        }

        $client    = $app['tent.client']->getUserClient($entityUrl);

        $client->deletePost($id);

        return new Response('', 204);
    }
    public function saveAction(Request $request, Application $app)
    {
        $entityUrl = $app['session']->get('entity_url');

        if (!$entityUrl) {
            return new RedirectResponse($app['url_generator']->generate('homepage'));
        }

        $bookmark = new Bookmark();

        $data = json_decode($request->getContent(), true);

        $form = $app['form.factory']->create(new BookmarkType(), $bookmark);
        $form->bind(array_map('strip_tags', $data['content']));

        if ( ! $form->isValid()) {
            $errors = array('error' => true, 'messages' => $this->formErrorsArray($form));
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
    }

    protected function formErrorsArray($form, array $errors = array())
    {
        foreach ($form->getErrors() as $error) {
            $errors[] = $error->getMessage();
        }

        foreach ($form->getChildren() as $child) {
            $errors = array_merge($errors, $this->formErrorsArray($child));
        }

        return $errors;
    }
}

