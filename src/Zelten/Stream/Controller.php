<?php

namespace Zelten\Stream;

use Zelten\BaseController;
use Silex\Application;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use TentPHP\PostCriteria;

class Controller extends BaseController
{
    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        $controllers->get('/notifications', array($this, 'notificationsAction'))
                    ->bind('stream_notifications')
                    ->before(array($this, 'isAuthenticated'));

        $controllers->get('/notifications/count', array($this, 'notificationsCountAction'))
                    ->bind('stream_notifications_count')
                    ->before(array($this, 'isAuthenticated'));

        $controllers->get('/', array($this, 'streamAction'))
                    ->bind('stream')
                    ->before(array($this, 'isAuthenticated'));

        $controllers->post('/', array($this, 'postAction'))
                    ->bind('stream_write')
                    ->before(array($this, 'isAuthenticated'));

        $controllers->get('/u/{entity}/{id}', array($this, 'conversationAction'))
                    ->bind('post_conversation')
                    ->before(array($this, 'isAuthenticated'));

        $controllers->post('/u/{entity}/{post}', array($this, 'repostAction'))
                    ->bind('repost')
                    ->before(array($this, 'isAuthenticated'));

        $controllers->get('/u/{entity}', array($this, 'profileAction'))
                    ->bind('stream_user')
                    ->before(array($this, 'isAuthenticated'));

        $controllers->post('/followings', array($this, 'followAction'))
                    ->bind('stream_follow')
                    ->before(array($this, 'isAuthenticated'));

        return $controllers;
    }

    public function followAction(Request $request, Application $app)
    {
        $entityUrl = $this->getCurrentEntity();

        $followEntity = $this->urlize($request->request->get('entity'));
        $stream = $app['zelten.stream'];
        $data   = $stream->follow($followEntity);

        if ($request->isXmlHttpRequest()) {
            return $app->json($data);
        }

        return new RedirectResponse($app['url_generator']->generate('stream_user', array(
            'entity' => $request->request->get('entity'),
        )));
    }

    public function conversationAction(Request $request, Application $app, $entity, $id)
    {
        $entityUrl = $this->getCurrentEntity();

        $mentionedEntity = $this->urlize($entity);
        $criteria = array(
            //'mentioned_entity' => $mentionedEntity,
            'mentioned_post'   => $id,
            'post_types'       => 'https://tent.io/types/post/status/v0.1.0',
        );

        $stream   = $app['zelten.stream'];
        $comments = $stream->getMessages($entityUrl, $criteria);
        $post     = $stream->getPost($mentionedEntity, $id);

        $parent = null;
        if ($post && $post->type == "status" && isset($post->content['reply'])) {
            $parent = $stream->getPost(
                $this->urlize($post->content['reply']['entity']['entity']),
                $post->content['reply']['post']
            );
        }

        return $app['twig']->render('conversation.html', array(
            'comments' => $comments,
            'parent'   => $parent,
            'post'     => $post,
        ));
    }

    public function repostAction(Request $request, Application $app, $entity, $post)
    {
        $entityUrl = $this->getCurrentEntity();

        $stream  = $app['zelten.stream'];
        $message = $stream->repost($this->urlize($entity), $post);

        if ($request->isXmlHttpRequest()) {
            $template = '_message.html';
            return $app['twig']->render($template, array('message' => $message));
        }

        return new RedirectResponse($app['url_generator']->generate('stream'));
    }

    public function postAction(Request $request, Application $app)
    {
        $entityUrl = $this->getCurrentEntity();

        $text    = substr(strip_tags($request->request->get('message')), 0, 256);
        $mention = array();

        if ($request->request->has('mentioned_entity')) {
            $mention = array(
                'entity' => $this->urlize($request->request->get('mentioned_entity')),
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

    public function profileAction(Request $request, Application $app, $entity)
    {
        $userEntity = $this->urlize($entity);

        return $app['twig']->render('user_profile.html', array(
            'profile' => $app['zelten.stream']->getFullProfile($userEntity),
            'you'     => ($userEntity === $this->getCurrentEntity())
        ));
    }

    public function userAction(Request $request, Application $app, $entity)
    {
        $userEntity = $this->urlize($entity);

        return $app['twig']->render('user_stream.html', array(
            'messages'   => $this->getMessages($userEntity, array(), $app),
            'profile'    => $stream->getFullProfile($userEntity),
            'entity'     => $userEntity,
        ));
    }

    public function notificationsAction(Request $request, Application $app)
    {
        $entityUrl = $this->getCurrentEntity();

        $criteria  = $request->query->get('criteria', array());
        $criteria['mentioned_entity'] = $entityUrl;

        $messages  = $this->getMessages($entityUrl, $criteria, $app);

        return $app['twig']->render('stream.html', array(
            'messages'         => $messages,
            'mentioned_entity' => $entityUrl,
            'post_add'         => false
        ));
    }

    public function notificationsCountAction(Request $request, Application $app)
    {
        $entityUrl = $this->getCurrentEntity();

        $criteria  = $request->query->get('criteria', array());
        $criteria['mentioned_entity'] = $entityUrl;

        return $app->json(array(
            'count' => $app['zelten.stream']->getMessageCount($entityUrl, $criteria))
        );
    }

    public function streamAction(Request $request, Application $app)
    {
        $entityUrl = $this->getCurrentEntity();
        $messages  = $this->getMessages($entityUrl, $request->query->get('criteria', array()), $app);

        return $app['twig']->render('stream.html', array('messages' => $messages, 'post_add' => true));
    }

    private function getMessages($entityUrl, $criteria, Application $app)
    {
        if (isset($criteria['since_id_entity'])) {
            $criteria['since_id_entity'] = $this->urlize($criteria['since_id_entity']);
        }

        if (isset($criteria['before_id_entity'])) {
            $criteria['before_id_entity'] = $this->urlize($criteria['before_id_entity']);
        }

        if (isset($criteria['entity'])) {
            $criteria['entity'] = $this->urlize($criteria['entity']);
        }

        $stream   = $app['zelten.stream'];
        return $stream->getMessages($entityUrl, $criteria);
    }
}

