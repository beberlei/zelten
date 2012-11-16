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

        $controllers->get('/synchronize', array($this, 'synchronizeAction'))
                    ->bind('profile_synchronize')
                    ->before(array($this, 'isAuthenticated'));

        $controllers->post('/synchronize', array($this, 'performSynchronizeAction'))
                    ->before(array($this, 'isAuthenticated'));

        $controllers->get('/synchronize/skip', array($this, 'synchronizeSkipAction'))
                    ->bind('profile_synchronize_skip')
                    ->before(array($this, 'isAuthenticated'));

        $controllers->get('/followers', array($this, 'myFollowersAction'))
                    ->bind('my_followers')
                    ->before(array($this, 'isAuthenticated'));

        $controllers->get('/following', array($this, 'myFollowingAction'))
                    ->bind('my_followings')
                    ->before(array($this, 'isAuthenticated'));

        $controllers->post('/following', array($this, 'followAction'))
                    ->bind('profile_follow')
                    ->before(array($this, 'isAuthenticated'));

        $controllers->get('/{entity}/followers', array($this, 'followersAction'))
                    ->bind('user_followers');

        $controllers->get('/{entity}/following', array($this, 'followingAction'))
                    ->bind('user_following');

        return $controllers;
    }

    public function synchronizeAction(Request $request, Application $app)
    {
        return $app['twig']->render('profile_synchronize.html');
    }

    public function performSynchronizeAction(Request $request, Application $app)
    {
        $app['zelten.profile']->synchronizeRelations($this->getCurrentEntity());
        return new RedirectResponse($app['url_generator']->generate('stream'));
    }

    public function synchronizeSkipAction(Request $request, Application $app)
    {
        $app['zelten.profile']->skipSynchronize($this->getCurrentEntity());
        return new RedirectResponse($app['url_generator']->generate('stream'));
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
        $entity            = $this->urlize($entity);
        $limit             = $request->query->get('limit', $request->isXmlHttpRequest() ? 5 : 50);
        $profileRepository = $app['zelten.profile'];
        $followers         = $profileRepository->getFollowers($entity, $limit);

        if ($this->acceptJson($request)) {
            return $app->json($followers);
        }

        return $app['twig']->render('users.html', array(
            'users'   => $followers,
            'title'   => 'Follower',
            'profile' => $profileRepository->getProfile($entity),
            'route'   => 'user_followers',
        ));
    }

    public function followingAction(Request $request, Application $app, $entity)
    {
        $entity = $this->urlize($entity);
        $limit             = $request->query->get('limit', $request->isXmlHttpRequest() ? 5 : 50);
        $profileRepository = $app['zelten.profile'];
        $following         = $profileRepository->getFollowings($entity, $limit);

        if ($this->acceptJson($request)) {
            return $app->json($following);
        }

        return $app['twig']->render('users.html', array(
            'users'   => $following,
            'title'   => 'Following',
            'profile' => $profileRepository->getProfile($entity),
            'route' => 'user_following',
        ));
    }

    public function followAction(Request $request, Application $app)
    {
        $entityUrl = $this->getCurrentEntity();

        $followEntity = $this->urlize($request->request->get('entity'));
        $stream       = $app['zelten.profile'];

        if ($request->request->get('action', 'follow') == 'follow') {
            $data = $stream->follow($entityUrl, $followEntity);
        } else {
            $data = $stream->unfollow($entityUrl, $followEntity);
        }

        if ($request->isXmlHttpRequest()) {
            return $app->json($data);
        }

        return new RedirectResponse($app['url_generator']->generate('stream_user', array(
            'entity' => $request->request->get('entity'),
        )));
    }
}

