<?php

namespace Zelten\Core;

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

        $controllers->get('/', function (Application $app, Request $request) {
            $entityUrl = $app['session']->get('entity_url');

            $stats = $app['db']->fetchAssoc(
                'SELECT count(*) as total_users, sum(bookmarks) as total_bookmarks FROM users'
            );

            return $app['twig']->render('index.html', array('stats' => $stats));
        })->bind('homepage');

        $controllers->get('/developers', function(Application $app) {
            return $app['twig']->render('developers.html', array());
        })->bind('developers');

        $controllers->get('/logout', function(Application $app) {
            $entityUrl = $app['session']->set('entity_url', null);
            return new RedirectResponse($app['url_generator']->generate('homepage'));
        })->bind('logout');

        $controllers->get('/login', function (Application $app) {
            return new RedirectResponse($app['url_generator']->generate('homepage'));
        });

        $controllers->post('/login', function (Request $request, Application $app) {
            $entityUrl = $app['session']->get('entity_url');

            if ($entityUrl) {
                return new RedirectResponse($app['url_generator']->generate('stream'));
            }

            $entityUrl = rtrim(trim($request->request->get('entity_url')), '/');

            if (!$entityUrl) {
                return new RedirectResponse($app['url_generator']->generate('homepage'));
            }

            if (strpos($entityUrl, ".") === false) {
                $entityUrl = "https://" . $entityUrl . ".tent.is";
            } else if (strpos($entityUrl, "http") === false) {
                $entityUrl = "https://" . $entityUrl;
            }

            $callbackUrl = $app['url_generator']->generate('oauth_accept', array(), true);

            $notificationsUrl = ($request->server->get('HTTP_HOST') == $app['notification_domain'])
                ? $app['url_generator']->generate('hook', array('hash' => hash_hmac('sha256', $entityUrl, $app['appsecret'])), true)
                : null;

            try {
                $loginUrl    = $app['tent.client']->getLoginUrl($entityUrl, null, $callbackUrl, null, array(
                    'http://www.beberlei.de/tent/bookmark/v0.0.1',
                    'https://tent.io/types/post/status/v0.1.0',
                    'https://tent.io/types/post/essay/v0.1.0',
                    'https://tent.io/types/post/repost/v0.1.0',
                    'https://tent.io/types/post/follower/v0.1.0',
                    'https://tent.io/types/post/following/v0.1.0',
                    'http://www.beberlei.de/tent/favorite/v0.0.1',
                ), $notificationsUrl);


            } catch(\TentPHP\Exception\EntityNotFoundException $e) {
                return new RedirectResponse($app['url_generator']->generate('homepage', array('error' => 'invaild_tent_entity')));
            }

            try {
                $appData = $app['tent.client']->getApplication($entityUrl);
                if (!in_array($callbackUrl, $appData['redirect_uris'])) {
                    $app['tent.client']->updateApplication($entityUrl);
                }
            } catch(\Exception $e) {
            }

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

        $controllers->get('/oauth/accept', function(Request $request, Application $app) {
            $entityUrl = $app['tent.client']->authorize(
                $request->query->get('state'),
                $request->query->get('code')
            );

            $app['session']->set('entity_url', $entityUrl);

            $app['db']->executeUpdate('UPDATE users SET last_login = NOW(), login_count = login_count + 1 WHERE entity = ?', array($entityUrl));

            $app['zelten.profile']->synchronizeRelations($entityUrl);
            /*if ($app['zelten.profile']->synchronizeRelationsOverdue($entityUrl)) {
                return new RedirectResponse($app['url_generator']->generate('profile_synchronize'));
            }*/

            return new RedirectResponse($app['url_generator']->generate('stream'));
        })->bind('oauth_accept');

        return $controllers;
    }
}

