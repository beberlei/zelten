<?php

namespace Zelten\Stream;

class Message
{
    public $id;
    public $type;
    public $entity;
    public $content;
    public $app;
    public $mentions;
    public $published;
    public $permissions;
    public $repostedBy;

    public function getEntityShortname()
    {
        return str_replace(array('https://', 'http://'), array('https-', 'http-'), $this->entity['entity']);
    }
}

