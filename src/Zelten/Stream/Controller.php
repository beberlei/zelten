<?php

namespace Zelten\Stream;

use Silex\ControllerProviderInterface;
use Silex\Application;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use TentPHP\PostCriteria;

class Controller implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        $controllers->get('/', array($this, 'streamAction'))->bind('stream');
        $controllers->get('/u/{entity}', array($this, 'userAction'))->bind('stream_user');

        return $controllers;
    }

    public function userAction(Request $request, Application $app, $entity)
    {
        $entityUrl = $app['session']->get('entity_url');

        if (!$entityUrl) {
            return new RedirectResponse($app['url_generator']->generate('homepage'));
        }
        $entity = str_replace(array('http-', 'https-'), array('http://', 'https://'), $entity);

        $stream = $app['zelten.stream'];
        $messages  = $stream->getMessages($entity);

        return $app['twig']->render('stream.html', array('messages' => $messages));
    }

    public function streamAction(Request $request, Application $app)
    {
        $entityUrl = $app['session']->get('entity_url');

        if (!$entityUrl) {
            return new RedirectResponse($app['url_generator']->generate('homepage'));
        }

        $stream = $app['zelten.stream'];
        $messages  = $stream->getMessages($entityUrl);

        return $app['twig']->render('stream.html', array('messages' => $messages));
    }
}

