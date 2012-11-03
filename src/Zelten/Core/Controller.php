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

        $controllers->get('/', function (Application $app) {
            $stats = $app['db']->fetchAssoc(
                'SELECT count(*) as total_users, sum(bookmarks) as total_bookmarks FROM users'
            );

            return $app['twig']->render('index.html', array('stats' => $stats));
        })->bind('homepage');

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

            $entityUrl = trim($request->request->get('entity_url'));

            if (!$entityUrl) {
                return new RedirectResponse($app['url_generator']->generate('homepage'));
            }

            if (strpos($entityUrl, ".") === false) {
                $entityUrl = "https://" . $entityUrl . ".tent.is";
            } else if (strpos($entityUrl, "http") === false) {
                $entityUrl = "https://" . $entityUrl;
            }

            $callbackUrl = $app['url_generator']->generate('oauth_accept', array(), true);

            try {
                $loginUrl    = $app['tent.client']->getLoginUrl($entityUrl, null, $callbackUrl, null, array(
                    'http://www.beberlei.de/tent/bookmark/v0.0.1',
                    'https://tent.io/types/post/status/v0.1.0',
                    'https://tent.io/types/post/essay/v0.1.0',
                    'https://tent.io/types/post/repost/v0.1.0',
                    'https://tent.io/types/post/follower/v0.1.0',
                    'http://www.beberlei.de/tent/favorite/v0.0.1',
                ), 'http://zelten.eu1.frbit.net/hook?hash='.hash_hmac('sha256', $entityUrl, $app['appsecret']));
            } catch(\TentPHP\Exception\EntityNotFoundException $e) {
                return new RedirectResponse($app['url_generator']->generate('homepage', array('error' => 'invaild_tent_entity')));
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
            $app['session']->set('entity_url', $app['tent.client']->authorize(
                $request->query->get('state'),
                $request->query->get('code')
            ));

            $app['db']->executeUpdate('UPDATE users SET last_login = NOW(), login_count = login_count + 1 WHERE entity = ?', array($app['session']->get('entity_url')));

            return new RedirectResponse($app['url_generator']->generate('stream'));
        })->bind('oauth_accept');

        return $controllers;
    }
}

