<?php

namespace Zelten\Stream;

use TentPHP\PostCriteria;
use TentPHP\Post;
use TentPHP\Util\Mentions;
use Kwi\UrlLinker;

use Zelten\Util\Washtml;
use Zelten\Util\String;

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

    public function __construct($tentClient, $urlGenerator, $profileRepository, $currentEntity)
    {
        $this->tentClient        = $tentClient;
        $this->urlGenerator      = $urlGenerator;
        $this->currentEntity     = $currentEntity;
        $this->profileRepository = $profileRepository;
        $this->linker            = new UrlLinker();
        $this->escaper           = new Washtml(array('charset' => 'UTF-8'));
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

        $message              = new Message();
        $message->id          = $post['id'];
        $message->type        = $this->supportedTypes[$post['type']];
        $message->content     = $post['content'];
        $message->entity      = $this->getFullProfile($post['entity']);
        $message->app         = $post['app'];
        $message->mentions    = $post['mentions'];
        $message->permissions = $post['permissions'];
        $message->published   = new \DateTime('@' . $post['published_at']);

        // switch a favorite
        if ($message->type == 'favorite') {
            return $this->getPost($message->content['entity'], $message->content['post']);
        }

        if ($message->type == 'status') {
            $message->content['text'] = nl2br($this->linker->parse($message->content['text']));
            $message->content['mentions']  = array();

            foreach ($message->mentions as $mention) {
                $parts = @parse_url($mention['entity']);

                // Tent entities are valid URLs. If there is no host, it is not a valid URL.
                if (!isset($parts['host'])) {
                    continue;
                }

                $profile = $this->getFullProfile($mention['entity']);
                // If the mention contains a post id, this message is replying to that post id.
                if (!empty($mention['post'])) {
                    $message->content['reply'] = array(
                        'post'   => $mention['post'],
                        'entity' => $profile,
                    );
                }

                // Add all mentions to the message unless the mentioned entity is the logged-in entity.
                if ($mention['entity'] !== $this->currentEntity) {
                    $message->content['mentions'][] = $mention['entity'];
                }

                // In the post, replace each mention of this entity with a link to the Zelten profile page for the entity
                $shortname = "^" . substr($parts['host'], 0, strpos($parts['host'], "."));
                $userLink = $this->urlGenerator->generate('stream_user', array('entity' => urlencode($mention['entity'])));

                // Entity formats to look for: ^https://daniel.tent.is, ^daniel.tent.is, ^daniel
                $mentionNames = array("^" . $mention['entity'], "^" . $parts['host'], $shortname);
                $message->content['text'] = str_replace(
                    $mentionNames,
                    '<a class="label label-info user-details" href="' . $userLink .'">' . isset($profile['name']) ? $profile['name'] : $mention['entity'] . '</a>',
                    $message->content['text']
                );
            }

            $message->content['mentions'][] = $post['entity'];
            $message->content['mentions'] = implode(" ", array_map(function ($mentionedEntity) {
                return "^" . $mentionedEntity;
            }, $message->content['mentions']));

        } else if ($message->type == 'follower') {

            // For new follower notifications, retrieve their full profile
            $message->content['follower'] = $this->getFullProfile($message->content['entity']);

        } else if ($message->type == 'repost') {

            // For reposts, retrieve the original post
            if (!empty($message->content['entity']) && !empty($message->content['id'])) {
                $message->content['original'] = $this->getPost($message->content['entity'], @$message->content['id']);
            }

            // Determine if we were unable to retrieve the original post
            if (!isset($message->content['original'])) {
                return;
            }

        } else if ($message->type == 'essay') {

            // For essays, allow access to the full text within the timeline.
            // Attempt to eliminate any threats hidden in the text.
            $message->content['body'] = $this->escaper->wash($message->content['body']);
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
        return $this->profileRepository->getProfile($entity);
    }
}

