<?php
namespace Zelten\Profile;

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

        $controllers->get('/{entity}/followers', array($this, 'followersAction'))
                    ->bind('user_followers')
                    ->before(array($this, 'isAuthenticated'));

        $controllers->get('/{entity}/following', array($this, 'followingAction'))
                    ->bind('user_following')
                    ->before(array($this, 'isAuthenticated'));

        return $controllers;
    }

    public function followersAction(Request $request, Application $app, $entity)
    {
        $stream = $app['zelten.stream'];
        return $app['twig']->render('users.html', array(
            'users' => $stream->getFollowers($this->urlize($entity)),
            'title' => 'Follower',
        ));
    }

    public function followingAction(Request $request, Application $app, $entity)
    {
        $stream = $app['zelten.stream'];
        return $app['twig']->render('users.html', array(
            'users' => $stream->getFollowings($this->urlize($entity)),
            'title' => 'Following',
        ));
    }
}
