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
        $controllers->post('/', array($this, 'postAction'))->bind('stream_write');
        $controllers->get('/u/{entity}/{id}/conversations', array($this, 'conversationAction'))->bind('post_conversation');
        $controllers->get('/u/{entity}', array($this, 'userAction'))->bind('stream_user');

        return $controllers;
    }

    public function conversationAction(Request $request, Application $app, $entity, $id)
    {
        $entityUrl = $app['session']->get('entity_url');

        if (!$entityUrl) {
            return new RedirectResponse($app['url_generator']->generate('homepage'));
        }

        $mentionedEntity   = str_replace(array('http-', 'https-'), array('http://', 'https://'), $entity);
        $criteria = array(
            //'mentioned_entity' => $mentionedEntity,
            'mentioned_post'   => $id,
            'post_types'       => 'https://tent.io/types/post/status/v0.1.0',
        );

        $stream   = $app['zelten.stream'];
        $comments = $stream->getMessages($entityUrl, $criteria);

        return $app['twig']->render('conversation.html', array('comments' => $comments));
    }

    public function postAction(Request $request, Application $app)
    {
        $entityUrl = $app['session']->get('entity_url');

        if (!$entityUrl) {
            return new RedirectResponse($app['url_generator']->generate('homepage'));
        }

        $text = substr(strip_tags($request->request->get('message')), 0, 256);
        $mention = array();

        if ($request->request->has('mentioned_entity')) {
            $mention = array(
                'entity' => str_replace(array('http-', 'https-'), array('http://', 'https://'), $request->request->get('mentioned_entity')),
                'post'   => $request->request->get('mentioned_post')
            );
        }

        $stream  = $app['zelten.stream'];
        $message = $stream->write($text, $mention);

        if ($request->isXmlHttpRequest()) {
            $template = '_message.html';
            return $app['twig']->render($template, array('message' => $message));
        }

        return new RedirectResponse($app['url_generator']->generate('stream'));
    }

    public function userAction(Request $request, Application $app, $entity)
    {
        $entityUrl = $app['session']->get('entity_url');

        if (!$entityUrl) {
            return new RedirectResponse($app['url_generator']->generate('homepage'));
        }
        $entity = str_replace(array('http-', 'https-'), array('http://', 'https://'), $entity);

        $stream   = $app['zelten.stream'];
        $messages = $stream->getMessages($entity, $request->query->get('criteria', array()));

        return $app['twig']->render('user_stream.html', array(
            'messages'   => $messages,
            'profile'    => $stream->getFullProfile($entity),
            'entity'     => $entity,
            'followers'  => $stream->getFollowers($entity),
            'followings' => $stream->getFollowings($entity),
        ));
    }

    public function streamAction(Request $request, Application $app)
    {
        $entityUrl = $app['session']->get('entity_url');

        if (!$entityUrl) {
            return new RedirectResponse($app['url_generator']->generate('homepage'));
        }

        $criteria = $request->query->get('criteria', array());

        if (isset($criteria['since_id_entity'])) {
            $criteria['since_id_entity'] = str_replace(array('http-', 'https-'), array('http://', 'https://'), $criteria['since_id_entity']);
        }

        if (isset($criteria['before_id_entity'])) {
            $criteria['before_id_entity'] = str_replace(array('http-', 'https-'), array('http://', 'https://'), $criteria['before_id_entity']);
        }

        $stream   = $app['zelten.stream'];
        $messages = $stream->getMessages($entityUrl, $criteria);

        return $app['twig']->render('stream.html', array('messages' => $messages));
    }
}

