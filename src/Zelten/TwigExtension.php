<?php

namespace Zelten;

use Zelten\Stream\Message;

class TwigExtension extends \Twig_Extension
{
    public function __construct($app)
    {
        $this->app = $app;
    }

    public function getName()
    {
        return 'zelten';
    }

    public function getFunctions()
    {
        return array(
            'message_is_favorite' => new \Twig_Function_Method($this, 'isFavoriteMessage'),
            'is_following'        => new \Twig_Function_Method($this, 'isFollowing'),
            'current_entity'      => new \Twig_Function_Method($this, 'currentEntity'),
        );
    }

    public function currentEntity()
    {
        return $this->app['zelten.profile']->getProfile($this->app['session']->get('entity_url'));
    }

    public function isFavoriteMessage(Message $message)
    {
        $ownerEntity = $this->app['session']->get('entity_url');
        $entity      = str_replace(array("http-", "https-"), array("http://", "https://"), $message->entity['entity']);

        return $this->app['zelten.favorite']->isFavorite($ownerEntity, $entity, $message->id);
    }

    public function isFollowing($entity)
    {
        $ownerEntity = $this->app['session']->get('entity_url');
        $entity      = str_replace(array("http-", "https-"), array("http://", "https://"), $entity);

        return $this->app['zelten.profile']->isFollowing($ownerEntity, $entity);
    }
}

