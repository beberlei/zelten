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
        );
    }

    public function isFavoriteMessage(Message $message)
    {
        $ownerEntity = $this->app['session']->get('entity_url');
        $entity      = str_replace(array("http-", "https-"), array("http://", "https://"), $message->entity['entity']);

        return $this->app['zelten.favorite']->isFavorite($ownerEntity, $entity, $message->id);
    }
}

