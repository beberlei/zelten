<?php

namespace Zelten\Stream;

use TentPHP\PostCriteria;
use TentPHP\Client;
use Zelten\Profile\ProfileRepository;

class StreamRepository
{
    private $tentClient;
    private $urlGenerator;
    private $currentEntity;
    private $mentions;

    private $supportedTypes = array(
        'https://tent.io/types/post/status/v0.1.0'    => 'status',
        'https://tent.io/types/post/essay/v0.1.0'     => 'essay',
        'https://tent.io/types/post/repost/v0.1.0'    => 'repost',
        'https://tent.io/types/post/follower/v0.1.0'  => 'follower',
        'http://www.beberlei.de/tent/bookmark/v0.0.1' => 'bookmark',
        'http://www.beberlei.de/tent/favorite/v0.0.1' => 'favorite',
    );

    private $profileTypeDefaults = array(
        'basic' => array('name' => '', 'bio' => '', 'avatar_url' => '', 'birthdate' => '', 'location' => ''),
        'core'  => array('entity' => '', 'server' => ''),
    );

    private $profileRepository;
    private $messageParser;

    /**
     * @param \TentPHP\Client $tentClient
     * @param \Zelten\Stream\MessageParser $messageParser
     * @param \Zelten\Profile\ProfileRepository $profileRepository
     * @param string $currentEntity
     */
    public function __construct(Client $tentClient, MessageParser $messageParser, ProfileRepository $profileRepository, $currentEntity)
    {
        $this->tentClient        = $tentClient;
        $this->currentEntity     = $currentEntity;
        $this->profileRepository = $profileRepository;
        $this->messageParser     = $messageParser;
        $this->postBuilder       = new PostBuilder();
    }

    /**
     * Write Status Post or Essay depending on the length.
     *
     * @param string $message
     * @param array $mention
     * @param array $permissions
     */
    public function write($message, $mention = null, array $permissions = array())
    {
        $client = $this->tentClient->getUserClient($this->currentEntity, true);
        $post   = $this->postBuilder->create($message, $mention, $permissions);
        $data   = $client->createPost($post);

        return $this->createMessage($data);
    }

    /**
     * Repost the post of a given entity.
     *
     * @param string $entity
     * @param string $post
     * @return \Zelten\Stream\Message
     */
    public function repost($entity, $id)
    {
        $post = $this->postBuilder->createRepost($entity, $id);

        $exists = $this->getPost($entity, $id);

        if (!$exists) {
            throw new \RuntimeException("Repost original post missing.");
        }

        $client = $this->tentClient->getUserClient($this->currentEntity, true);
        $data   = $client->createPost($post);

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
        $client = $this->tentClient->getUserClient($entityUrl, $entityUrl == $this->currentEntity);

        return $this->createMessage($client->getPost($id));
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
            'limit'      => 20), $criteria);

        $supportedTypes = array_flip($this->supportedTypes);

        if (isset($supportedTypes[$criteria['post_types']])) {
            $criteria['post_types'] = $supportedTypes[$criteria['post_types']];
        }

        return $criteria;
    }

    /**
     * Get a list of messages on the stream of the given entity.
     *
     * @param string $entityUrl
     * @param array $criteria
     *
     * @return \Zelten\Stream\Message[]
     */
    public function getMessages($entityUrl, array $criteria = array())
    {
        $criteria = $this->mergeMessageCriteria($criteria);
        $client   = $this->tentClient->getUserClient($entityUrl, $entityUrl == $this->currentEntity);
        $posts    = $client->getPosts(new PostCriteria($criteria));

        $result = array(
            'messages' => array(),
            'first'    => null,
            'last'     => null
        );

        foreach ($posts as $post) {
            if ( ! $this->isSupportedPostType($post)) {
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

    /**
     * Check if post is supported by Zelten
     *
     * @param array $post
     * @return bool
     */
    private function isSupportedPostType(array $post)
    {
        return isset($this->supportedTypes[$post['type']]);
    }

    /**
     * Convert a Tent post into a Zelten message
     *
     * @param string $post
     * @return Message
     */
    private function createMessage($post)
    {
        return $this->messageParser->parse($post, $this->currentEntity, $this);
    }

    public function getEntityShortname($url)
    {
        return rtrim(str_replace(array('https://', 'http://'), array('https-', 'http-'), $url), '/');
    }

    public function getFullProfile($entity)
    {
        return $this->profileRepository->getProfile($entity);
    }
}

