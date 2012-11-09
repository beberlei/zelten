<?php

namespace Zelten;

use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

abstract class BaseController implements ControllerProviderInterface
{
    private $entityUrl;

    public function isAuthenticated(Request $request)
    {
        $this->entityUrl = $request->getSession()->get('entity_url');

        if (!$this->entityUrl) {
            return new RedirectResponse("/");
        }
    }

    protected function acceptJson(Request $request)
    {
        return in_array('application/json', $request->getAcceptableContentTypes());
    }

    protected function hasCurrentEntity()
    {
        return $this->entityUrl !== null;
    }

    protected function getCurrentEntity()
    {
        return $this->entityUrl;
    }

    protected function urlize($entity)
    {
        return urldecode(str_replace(array('http-', 'https-'), array('http://', 'https://'), $entity));
    }
}

