<?php
/**
 * Zelten
 *
 * LICENSE
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file LICENSE.txt.
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to kontakt@beberlei.de so I can send you a copy immediately.
 */

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

        $controllers->get('/followers', array($this, 'myFollowersAction'))
                    ->bind('my_followers')
                    ->before(array($this, 'isAuthenticated'));

        $controllers->get('/following', array($this, 'myFollowingAction'))
                    ->bind('my_followings')
                    ->before(array($this, 'isAuthenticated'));

        $controllers->get('/{entity}/followers', array($this, 'followersAction'))
                    ->bind('user_followers')
                    ->before(array($this, 'isAuthenticated'));

        $controllers->get('/{entity}/following', array($this, 'followingAction'))
                    ->bind('user_following')
                    ->before(array($this, 'isAuthenticated'));

        return $controllers;
    }

    public function myFollowingAction(Request $request, Application $app)
    {
        return $this->followingAction($request, $app, $this->getCurrentEntity());
    }

    public function myFollowersAction(Request $request, Application $app)
    {
        return $this->followersAction($request, $app, $this->getCurrentEntity());
    }

    public function followersAction(Request $request, Application $app, $entity)
    {
        $limit     = $request->query->get('limit', $request->isXmlHttpRequest() ? 5 : 20);
        $stream    = $app['zelten.stream'];
        $followers = $stream->getFollowers($this->urlize($entity, $limit));

        if ($this->acceptJson($request)) {
            return $app->json($followers);
        }

        return $app['twig']->render('users.html', array(
            'users' => $followers,
            'title' => 'Follower',
        ));
    }

    public function followingAction(Request $request, Application $app, $entity)
    {
        $limit  = $request->query->get('limit', $request->isXmlHttpRequest() ? 5 : 20);
        $stream = $app['zelten.stream'];
        $following = $stream->getFollowings($this->urlize($entity), $limit);

        if ($this->acceptJson($request)) {
            return $app->json($following);
        }

        return $app['twig']->render('users.html', array(
            'users' => $following,
            'title' => 'Following',
        ));
    }
}
