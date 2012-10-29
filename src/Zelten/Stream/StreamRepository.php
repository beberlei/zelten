<?php

namespace Zelten\Stream;

use TentPHP\PostCriteria;
use TentPHP\Post;
use Kwi\UrlLinker;

class StreamRepository
{
    private $tentClient;
    private $urlGenerator;
    private $currentEntity;
    private $linker;

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

    public function __construct($tentClient, $urlGenerator, $currentEntity)
    {
        $this->tentClient = $tentClient;
        $this->urlGenerator = $urlGenerator;
        $this->currentEntity = $currentEntity;
        $this->linker = new UrlLinker();
    }

    public function write($message, $mention = null)
    {
        $client = $this->tentClient->getUserClient($this->currentEntity, true);

        $post = Post::create('https://tent.io/types/post/status/v0.1.0');
        $post->setContent(array('text' => substr($message, 0, 256)));
        $post->markPublic();

        if ($mention) {
            $post->addMention($mention['entity'], $mention['post']);
        }

        $data = $client->createPost($post);
        return $this->createMessage($data);
    }

    public function getMessages($entityUrl, array $criteria = array())
    {
        $client   = $this->tentClient->getUserClient($entityUrl, $entityUrl == $this->currentEntity);
        $criteria = array_merge(array(
                'post_types' => 'https://tent.io/types/post/status/v0.1.0,http://www.beberlei.de/tent/bookmark/v0.0.1',
                'limit'      => 10,
            ), $criteria);
        $posts  = $client->getPosts(new PostCriteria($criteria));

        $result = array(
            'messages' => array(),
            'first'    => null,
            'last'     => null
        );

        foreach ($posts as $post) {
            if (!isset($this->supportedTypes[$post['type']])) {
                continue;
            }

            if (!$result['first']) {
                $result['first'] = array('id' => $post['id'], 'entity' => $post['entity']);
            }
            $result['last'] = array('id' => $post['id'], 'entity' => $post['entity']);

            $result['messages'][] = $this->createMessage($post);
        }

        return $result;
    }

    private function createMessage($post)
    {
        $message              = new Message();
        $message->id          = $post['id'];
        $message->type        = $this->supportedTypes[$post['type']];
        $message->content     = $post['content'];
        $message->entity      = $this->getPublicProfile($post['entity']);
        $message->app         = $post['app'];
        $message->mentions    = $post['mentions'];
        $message->permissions = $post['permissions'];
        $message->published   = new \DateTime('@' . $post['published_at']);

        if ($message->type == 'status') {
            $message->content['text'] = $this->linker->parse($message->content['text']);

            foreach ($message->mentions as $mention) {
                $parts = parse_url($mention['entity']);

                if (!isset($parts['host'])) {
                    continue;
                }

                $shortname = "^" . substr($parts['host'], 0, strpos($parts['host'], "."));
                $userLink = $this->urlGenerator->generate('stream_user', array('entity' => $this->getEntityShortname($mention['entity'])));

                $message->content['text'] = str_replace(
                    array($shortname, "^".$mention['entity']),
                    '<a href="' . $userLink .'">' . $shortname . '</a>',
                    $message->content['text']
                );
            }
        }

        return $message;
    }

    public function getEntityShortname($url)
    {
        return rtrim(str_replace(array('https://', 'http://'), array('https-', 'http-'), $url), '/');
    }

    public function getFullProfile($entity)
    {
        $profile = array(
            'name'   => str_replace(array('https://', 'http://'), '', $entity),
            'entity' => $this->getEntityShortname($entity),
        );

        try {
            $userClient = $this->tentClient->getUserClient($entity, $entity == $this->currentEntity);
            $data = $userClient->getProfile();
        } catch(\Guzzle\Http\Exception\CurlException $e) {
            $data = array();
        }

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

        try {
            $userClient = $this->tentClient->getUserClient($entity, $entity == $this->currentEntity);
            $profile = $userClient->getProfile();
        } catch(\Guzzle\Http\Exception\CurlException $e) {
            $profile = array();
        }

        $data = array('entity' => $this->getEntityShortname($entity), 'name' => $entity, 'avatar' => null);
        if (isset($profile['https://tent.io/types/info/basic/v0.1.0'])) {
            $data['name']   = $profile['https://tent.io/types/info/basic/v0.1.0']['name'];
            $data['avatar'] = $profile['https://tent.io/types/info/basic/v0.1.0']['avatar_url'];
        }

        apc_store($key, $data, 3600);

        return $data;
    }

    public function getFollowers($entity, $limit = 5)
    {
        $userClient = $this->tentClient->getUserClient($entity, $entity == $this->currentEntity);
        $followers  = $userClient->getFollowers();
        $count      = $userClient->getFollowerCount();

        return $this->preparePeopleList($count, $followers, $limit);
    }

    public function getFollowings($entity, $limit = 5)
    {
        $userClient = $this->tentClient->getUserClient($entity, $entity == $this->currentEntity);
        $followings = $userClient->getFollowings();
        $count      = $userClient->getFollowingCount();

        return $this->preparePeopleList($count, $followings, $limit);
    }

    private function preparePeopleList($count, $peoples, $limit)
    {
        $result = array('total' => $count, 'list' => array());

        foreach ($peoples as $people) {
            $result['list'][] = $this->getPublicProfile($people['entity']);

            if (count($result['list']) >= $limit) {
                break;
            }
        }

        return $result;
    }
}

