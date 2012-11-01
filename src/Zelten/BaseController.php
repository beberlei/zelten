<?php

namespace Zelten;

use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

abstract class BaseController implements ControllerProviderInterface
{
    public function isAuthenticated(Request $request)
    {
        $this->entityUrl = $request->getSession()->get('entity_url');

        if (!$this->entityUrl) {
            return new RedirectResponse($app['url_generator']->generate('homepage'));
        }
    }

    protected function getCurrentEntity()
    {
        if (!$this->entityUrl) {
            throw new AccessDeniedHttpException();
        }
        return $this->entityUrl;
    }

    protected function urlize($entity)
    {
        return str_replace(array('http-', 'https-'), array('http://', 'https://'), $entity);
    }
}

