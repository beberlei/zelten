<?php

namespace Zelten\Stream;

use TentPHP\PostCriteria;
use TentPHP\Post;
use TentPHP\Util\Mentions;
use Kwi\UrlLinker;
use Zend\Escaper\Escaper;

class StreamRepository
{
    private $tentClient;
    private $urlGenerator;
    private $currentEntity;
    private $linker;
    private $escaper;
    private $mentions;

    private $supportedTypes = array(
        'https://tent.io/types/post/status/v0.1.0'    => 'status',
        'http://www.beberlei.de/tent/bookmark/v0.0.1' => 'bookmark',
        'https://tent.io/types/post/essay/v0.1.0'     => 'essay',
        'https://tent.io/types/post/repost/v0.1.0'    => 'repost',
        'https://tent.io/types/post/follower/v0.1.0'  => 'follower',
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
        $this->tentClient    = $tentClient;
        $this->urlGenerator  = $urlGenerator;
        $this->currentEntity = $currentEntity;
        $this->linker        = new UrlLinker();
        $this->escaper       = new Escaper();
        $this->mentions      = new Mentions();
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

        $mentions = $this->mentions->extractMentions($message, $this->currentEntity);

        foreach ($mentions as $mention) {
            if ($mention['entity'] === $this->currentEntity) {
                continue;
            }

            $post->addMention($mention['entity']);
        }

        $data = $client->createPost($post);
        return $this->createMessage($data);
    }

    /**
     * Repost the post of a given entity.
     *
     * @param string $entity
     * @param string $post
     * @return Message
     */
    public function repost($entity, $id)
    {
        $client = $this->tentClient->getUserClient($this->currentEntity, true);

        if (empty($entity) || empty($id)) {
            throw new \RuntimeException("Repost content data missing.");
        }

        $exists = $this->getPost($entity, $id);

        if (!$exists) {
            throw new \RuntimeException("Repost original post missing.");
        }

        $post = Post::create('https://tent.io/types/post/repost/v0.1.0');
        $post->setContent(array('entity' => $entity, 'id' => $id));
        $post->markPublic();

        $data = $client->createPost($post);
        return $this->createMessage($data);
    }

    /**
     * Get a post
     *
     * @param string $entityUrl
     * @param string $id
     * @return Message
     */
    public function getPost($entityUrl, $id)
    {
        $key     = sprintf('post_%s#%s#%s', $entityUrl, $entityUrl == $this->currentEntity, $id);
        $message = apc_fetch($key, $fetched);

        if ($fetched) {
            return $message;
        }

        try {
            $client = $this->tentClient->getUserClient($entityUrl, $entityUrl == $this->currentEntity);

            return $this->createMessage($client->getPost($id));
        } catch(\Exception $e) {
            return;
        }
    }

    public function getMessageCount($entity, array $criteria = array())
    {
        $criteria = $this->mergeMessageCriteria($criteria);
        $client   = $this->tentClient->getUserClient($entity, $entity == $this->currentEntity);
        return $client->getPostCount(new PostCriteria($criteria));
    }

    private function mergeMessageCriteria($criteria)
    {
        $types = array(
            'http://www.beberlei.de/tent/bookmark/v0.0.1',
            'https://tent.io/types/post/status/v0.1.0',
            'https://tent.io/types/post/essay/v0.1.0',
            'https://tent.io/types/post/repost/v0.1.0',
            'https://tent.io/types/post/follower/v0.1.0',
        );

        $criteria = array_merge(array(
                'post_types' => implode(",", $types),
                'limit'      => 10,
            ), $criteria);

        $supportedTypes = array_flip($this->supportedTypes);
        if (isset($supportedTypes[$criteria['post_types']])) {
            $criteria['post_types'] = $supportedTypes[$criteria['post_types']];
        }

        return $criteria;
    }

    public function getMessages($entityUrl, array $criteria = array())
    {
        $criteria = $this->mergeMessageCriteria($criteria);
        $client   = $this->tentClient->getUserClient($entityUrl, $entityUrl == $this->currentEntity);
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

            $message = $this->createMessage($post);
            if ($message) {
                $result['messages'][] = $message;
            }
        }

        return $result;
    }

    private function createMessage($post)
    {
        $key     = sprintf('post_%s#%s#%s', $post['entity'], $post['entity'] == $this->currentEntity, $post['id']);

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
            if (preg_match_all('((\^[^\s]+))', $message->content['text'], $matches)) {
                $matches[1][] = "^". str_replace(array("https://", "http://"), "", $post['entity']);
                $message->content['mentions'] = array_unique($matches[1]);
            } else {
                $message->content['mentions'] = array("^". str_replace(array("https://", "http://"), "", $post['entity']));
            }

            $currentEntity = $this->currentEntity;
            $message->content['mentions'] = implode(" ", array_filter(
                $message->content['mentions'],
                function($mentionedEntity) use($currentEntity) {
                    return strpos($currentEntity, ltrim($mentionedEntity, "^")) === false;
            }));

            $message->content['text'] = nl2br($this->linker->parse($message->content['text']));

            foreach ($message->mentions as $mention) {
                if (!empty($mention['post'])) {
                    $message->content['reply'] = array(
                        'post'   => $mention['post'],
                        'entity' => $this->getPublicProfile($mention['entity'])
                    );
                }

                $parts = parse_url($mention['entity']);

                if (!isset($parts['host'])) {
                    continue;
                }

                $shortname = "^" . substr($parts['host'], 0, strpos($parts['host'], "."));
                $userLink = $this->urlGenerator->generate('stream_user', array('entity' => $this->getEntityShortname($mention['entity'])));

                $mentionNames = array("^" . $mention['entity'], "^" . $parts['host'], $shortname);
                $message->content['text'] = str_replace(
                    $mentionNames,
                    '<a class="user-details" href="' . $userLink .'">' . $shortname . '</a>',
                    $message->content['text']
                );
            }
        } else if ($message->type == 'follower') {
            $message->content['follower'] = $this->getPublicProfile($message->content['entity']);
        } else if ($message->type == 'repost') {

            if (!empty($message->content['entity']) && !empty($message->content['id'])) {
                $message->content['original'] = $this->getPost($message->content['entity'], @$message->content['id']);
            }

            if (!isset($message->content['original'])) {
                return;
            }

        } else if ($message->type == 'essay') {
            $message->content['body'] = $this->escaper->escapeHtml($message->content['body']);
        }

        apc_store($key, $message, 600);

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
            'uri'    => $entity,
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

    public function follow($followEntity)
    {
        $userClient = $this->tentClient->getUserClient($this->currentEntity, true);
        return $userClient->follow($followEntity);
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

