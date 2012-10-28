<?php

namespace Zelten\Stream;

use TentPHP\PostCriteria;
use Kwi\UrlLinker;

class StreamRepository
{
    private $tentClient;
    private $urlGenerator;

    private $supportedTypes = array(
        'https://tent.io/types/post/status/v0.1.0'    => 'status',
        'http://www.beberlei.de/tent/bookmark/v0.0.1' => 'bookmark',
    );
    private $supportedProfileTypes = array(
        'https://tent.io/types/info/basic/v0.1.0' => 'basic',
        'https://tent.io/types/info/core/v0.1.0' => 'core',
    );
    private $profileTypeDefaults = array(
        'basic' => array('name' => '', 'bio' => '', 'avatar_url' => '', 'birthdate' => '', 'location' => ''),
        'core'  => array('entity' => '', 'server' => ''),
    );

    public function __construct($tentClient, $urlGenerator)
    {
        $this->tentClient = $tentClient;
        $this->urlGenerator = $urlGenerator;
    }

    public function getMessages($entityUrl)
    {
        $client   = $this->tentClient->getUserClient($entityUrl);
        $criteria = array(
            'post_types' => 'https://tent.io/types/post/status/v0.1.0,http://www.beberlei.de/tent/bookmark/v0.0.1',
            'limit'      => 10,
        );
        $posts    = $client->getPosts(new PostCriteria($criteria));

        $messages = array();
        $linker = new UrlLinker();
        foreach ($posts as $post) {
            if (!isset($this->supportedTypes[$post['type']])) {
                continue;
            }

            $message              = new Message();
            $message->type        = $this->supportedTypes[$post['type']];
            $message->content     = $post['content'];
            $message->entity      = $this->getPublicProfile($post['entity']);
            $message->app         = $post['app'];
            $message->mentions    = $post['mentions'];
            $message->permissions = $post['permissions'];
            $message->published   = new \DateTime('@' . $post['published_at']);

            if ($message->type == 'status') {
                $message->content['text'] = $linker->parse($message->content['text']);

                foreach ($message->mentions as $mention) {
                    $parts = parse_url($mention['entity']);
                    $shortname = "^" . substr($parts['host'], 0, strpos($parts['host'], "."));
                    $userLink = $this->urlGenerator->generate('stream_user', array('entity' => $this->getEntityShortname($mention['entity'])));

                    $message->content['text'] = str_replace(
                        array($shortname, "^".$mention['entity']),
                        '<a href="' . $userLink .'">' . $shortname . '</a>',
                        $message->content['text']
                    );
                }
            }

            $messages[] = $message;
        }

        return $messages;
    }

    public function getEntityShortname($url)
    {
        return str_replace(array('https://', 'http://'), array('https-', 'http-'), $url);
    }

    public function getFullProfile($entity)
    {
        $userClient = $this->tentClient->getUserClient($entity);
        $data = $userClient->getProfile();
        $profile = array('name' => str_replace(array('https://', 'http://'), '', $entity));

        foreach ($this->supportedProfileTypes as $profileType => $name) {
            if (isset($data[$profileType])) {
                $profile[$name] = $data[$profileType];
            } else {
                $profile[$name] = $this->profileTypeDefaults[$name];
            }
        }

        if (!empty($profile['basic']['name'])) {
            $profile['name'] = $profile['basic']['name'];
        }

        return $profile;
    }

    public function getPublicProfile($entity)
    {
        $key = "userprofile_" . $entity;
        $data = apc_fetch($key);

        if ($data) {
            return $data;
        }

        $userClient = $this->tentClient->getUserClient($entity);
        $profile = $userClient->getProfile();

        $data = array('entity' => $entity, 'name' => $entity, 'avatar' => null);
        if (isset($profile['https://tent.io/types/info/basic/v0.1.0'])) {
            $data['name']   = $profile['https://tent.io/types/info/basic/v0.1.0']['name'];
            $data['avatar'] = $profile['https://tent.io/types/info/basic/v0.1.0']['avatar_url'];
        }

        apc_store($key, $data, 3600);

        return $data;
    }

    public function getFollowers($entity, $limit = 5)
    {
        $userClient = $this->tentClient->getUserClient($entity);
        $followers  = $userClient->getFollowers();

        return $this->preparePeopleList($followers, $limit);
    }

    public function getFollowings($entity, $limit = 5)
    {
        $userClient = $this->tentClient->getUserClient($entity);
        $followings = $userClient->getFollowings();

        return $this->preparePeopleList($followings, $limit);
    }

    private function preparePeopleList($followers, $limit)
    {
        $result = array('total' => count($followers), 'list' => array());
        foreach ($followers as $follower) {
            $profile = array(
                'entity' => $this->getEntityShortname($follower['entity']),
                'name'   => str_replace(array('https://', 'http://'), '', $follower['entity']),
            );

            foreach ($this->supportedProfileTypes as $profileType => $name) {
                if (isset($follower['profile'][$profileType])) {
                    $profile[$name] = $follower['profile'][$profileType];
                } else {
                    $profile[$name] = $this->profileTypeDefaults[$name];
                }
            }

            if (!empty($profile['basic']['name'])) {
                $profile['name'] = $profile['basic']['name'];
            }

            $result['list'][] = $profile;

            if (count($result['list']) >= $limit) {
                break;
            }
        }

        return $result;
    }
}

