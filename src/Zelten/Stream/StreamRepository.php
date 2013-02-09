<?php

namespace Zelten\Stream;

use TentPHP\PostCriteria;
use TentPHP\Post;
use TentPHP\Client;
use TentPHP\Util\Mentions;
use Zelten\Util\String;
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
        $this->mentions          = new Mentions();
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

        if (strlen($message) <= 256) {
            $post = Post::create('https://tent.io/types/post/status/v0.1.0');
            $post->setContent(array('text' => $message));
        } else {
            $message = String::autoParagraph($message);
            $post = Post::create('https://tent.io/types/post/essay/v0.1.0');
            $post->setContent(array(
                'body'    => $message,
                'excerpt' => String::getFirstParagraph($message)
            ));
        }

        foreach ($permissions as $permission) {
            switch(strtolower($permission)) {
                case 'public':
                case 'everybody':
                    $post->markPublic();
                    break;
                default:
                    $post->markVisibleEntity($this->mentions->normalize($permission, $this->currentEntity));
                    break;
            }
        }

        $alreadyMentioned = array();

        if ($mention) {
            $alreadyMentioned[$mention['entity']] = true;
            $post->addMention($mention['entity'], $mention['post']);
        }

        $mentions = $this->mentions->extractMentions($message, $this->currentEntity);

        foreach ($mentions as $mention) {
            if ($mention['entity'] === $this->currentEntity ||
                isset($alreadyMentioned[$mention['entity']])) {

                continue;
            }

            $post->addMention($mention['entity']);
            $alreadyMentioned[$mention['entity']] = true;
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
                'limit'      => 20,
            ), $criteria);

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
        $posts  = $client->getPosts(new PostCriteria($criteria));

        $result = array(
            'messages' => array(),
            'first'    => null,
            'last'     => null
        );

        foreach ($posts as $post) {
            // If this posts type is not supported by Zelten, bypass the post.
            if (!isset($this->supportedTypes[$post['type']])) {
                continue;
            }

            if (!$result['first']) {
                $result['first'] = array('id' => $post['id'], 'entity' => $post['entity']);
            }
            $result['last'] = array('id' => $post['id'], 'entity' => $post['entity']);

            // Create a Zelten message from this Tent post
            $message = $this->createMessage($post);
            if ($message) {
                $result['messages'][] = $message;
            }
        }

        return $result;
    }

    /**
     * Convert a Tent post into a Zelten message
     *
     * @param string $post
     * @return Message
     */
    private function createMessage($post)
    {
        $key     = sprintf('post_%s#%s#%s', $post['entity'], $post['entity'] == $this->currentEntity, $post['id']);

        $message = $this->messageParser->parse($post, $this->currentEntity, $this);

        apc_store($key, $message, 600);

        return $message;
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

