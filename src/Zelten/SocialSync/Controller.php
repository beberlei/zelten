<?php
namespace Zelten\SocialSync;

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

        $controllers->get('/', array($this, 'indexAction'))->bind('socialsync');
        $controllers->get('/connect/twitter', array($this, 'connectTwitterAction'))->bind('socialsync_connect_twitter');
        $controllers->get('/oauth/accept/twitter', array($this, 'acceptTwitterAction'))->bind('socialsync_accept_twitter');

        return $controllers;
    }

    public function connectTwitterAction(Application $app)
    {
        $entityUrl = $app['session']->get('entity_url');

        if (!$entityUrl) {
            return new RedirectResponse($app['url_generator']->generate('homepage'));
        }

        $twitter = $app['twitter'];

        $redirectUrl   = $app['url_generator']->generate('socialsync_accept_twitter', array(), true);
        $request_token = $twitter->getRequestToken($redirectUrl);

        $token = $request_token['oauth_token'];
        $app['session']->set('tw_oauth_token', $token);
        $app['session']->set('tw_oauth_token_secret', $request_token['oauth_token_secret']);

        switch ($twitter->http_code) {
            case 200:
                return new RedirectResponse($twitter->getAuthorizeURL($token));
            default:
                return new RedirectResponse($app['url_generator']->generate('homepage'));
        }
    }

    public function indexAction(Application $app)
    {
        $entityUrl = $app['session']->get('entity_url');

        if (!$entityUrl) {
            return new RedirectResponse($app['url_generator']->generate('homepage'));
        }

        $userRow    = $app['db']->fetchAssoc('SELECT * FROM users WHERE entity = ?', array($entityUrl));
        $hasTwitter = $userRow['twitter_oauth_token'] && $userRow['twitter_oauth_secret'];

        return $app['twig']->render('socialsync.html', array(
                    'twitter_connected' => $hasTwitter
                    ));
    }

    public function acceptTwitterAction(Request $request, Application $app)
    {
        $entityUrl = $app['session']->get('entity_url');

        if (!$entityUrl) {
            return new RedirectResponse($app['url_generator']->generate('homepage'));
        }

        $token = $request->query->get('oauth_token');
        if ($token !== $app['session']->get('tw_oauth_token')) {
            return new RedirectResponse($app['url_generator']->generate('homepage'));
        }

        $twitter = $app['twitter'];
        $twitter->setTokens($token, $app['session']->get('tw_oauth_token_secret'));
        $accessToken = $twitter->getAccessToken($request->query->get('oauth_verifier'));

        $query = "UPDATE users SET twitter_oauth_token = ?, twitter_oauth_secret = ? WHERE entity = ?";
        $app['db']->executeUpdate(
                $query,
                array($accessToken['oauth_token'], $accessToken['oauth_token_secret'], $entityUrl)
                );

        return new RedirectResponse($app['url_generator']->generate('socialsync'));
    }
}


