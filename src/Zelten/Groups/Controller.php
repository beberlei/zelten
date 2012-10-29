<?php

namespace Zelten\Groups;

use Silex\ControllerProviderInterface;
use Silex\Application;

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

        $controllers->get('/', array($this, 'indexAction'))->bind('groups');
        $controllers->post('/', array($this, 'createAction'))->bind('group_create');
        $controllers->get('/followings', array($this, 'followingsAction'))->bind('group_followings');

        return $controllers;
    }

    public function followingsAction(Request $request, Application $app)
    {
        $entityUrl = $app['session']->get('entity_url');

        if (!$entityUrl) {
            return new RedirectResponse($app['url_generator']->generate('homepage'));
        }

        $client     = $app['tent.client']->getUserClient($entityUrl);
        $followings = $client->getFollowings(); // bug! need all

        $data = array();
        foreach ($followings as $follower) {
            $data[] = array(
                'id'     => $follower['id'],
                'entity' => $follower['entity'],
                'avatar' => isset($follower['profile']['https://tent.io/types/info/basic/v0.1.0']['avatar_url'])
                    ? $follower['profile']['https://tent.io/types/info/basic/v0.1.0']['avatar_url']
                    : null,
                'name'   => isset($follower['profile']['https://tent.io/types/info/basic/v0.1.0']['name'])
                    ? $follower['profile']['https://tent.io/types/info/basic/v0.1.0']['name']
                    : $follower['entity'],
                'groups' => $follower['groups'],
            );
        }

        return $app->json($data);
    }

    public function indexAction(Request $request, Application $app)
    {
        $entityUrl = $app['session']->get('entity_url');

        if (!$entityUrl) {
            return new RedirectResponse($app['url_generator']->generate('homepage'));
        }

        $groups = $app['tent.client']->getUserClient($entityUrl)->getGroups();

        return $app['twig']->render('groups.html', array('groups' => $groups));
    }

    public function createAction(Request $request, Application $app)
    {
        $entityUrl = $app['session']->get('entity_url');

        if (!$entityUrl) {
            return new RedirectResponse($app['url_generator']->generate('homepage'));
        }

        $name = $request->request->get('name');
        if (!$name) {
            throw new \RuntimeException("Invalid!");
        }

        $group = $app['tent.client']->getUserClient($entityUrl)->createGroup($name);

        return $app['twig']->render('_group.html', array('group' => $group));
    }
}

