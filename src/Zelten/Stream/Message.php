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
        return $this->entity['entity'];
    }

    public function canBeReposted()
    {
        return in_array($this->type, array('status', 'essay'));
    }

    public function isLimited()
    {
        return !$this->isPublic() && (!empty($this->permissions['groups']) || !empty($this->permissions['entities']));
    }

    public function isPrivate()
    {
        return !$this->isPublic() && !$this->isLimited();
    }

    public function isPublic()
    {
        return isset($this->permissions['public']) && $this->permissions['public'];
    }

    public function getVisibleGroups()
    {
        return isset($this->permissions['groups'])
            ? array_keys($this->permissions['groups'])
            : array();
    }

    public function getVisibleEntities()
    {
        return isset($this->permissions['entities'])
            ? array_keys($this->permissions['entities'])
            : array();
    }
}

