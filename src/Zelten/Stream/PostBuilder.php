<?php

namespace Zelten\Stream;

use Zelten\Util\String;
use TentPHP\Util\Mentions;
use TentPHP\Post;

/**
 * Builder to create a tent post object
 */
class PostBuilder
{
    /**
     * @var \TentPHP\Util\Mentions
     */
    private $mentions;

    public function __construct()
    {
        $this->mentions = new Mentions();
    }

    /**
     * Create a repost
     *
     * @param string $entity
     * @param string $id
     *
     * @return \TentPHP\Post
     */
    public function createRepost($entity, $id)
    {
        if (empty($entity) || empty($id)) {
            throw new \RuntimeException("Repost content data missing.");
        }

        $post = Post::create('https://tent.io/types/post/repost/v0.1.0');
        $post->setContent(array('entity' => $entity, 'id' => $id));
        $post->markPublic();

        return $post;
    }

    /**
     * Create a Post object
     *
     * @param string $message
     * @param array $mention
     * @param array $permissions
     *
     * @return TentPHP\Post
     */
    public function create($message, $mention, array $permissions = array())
    {
        $post = $this->createPost($message);

        $this->addPermissions($post, $permissions);
        $this->addMentions($post, $message, $mention);

        return $post;
    }

    private function createPost($message)
    {
        if (strlen($message) <= 256) {
            return $this->createStatusPost($message);
        }

        return $this->createEssayPost($message);
    }

    private function createEssayPost($message)
    {
        $message = String::autoParagraph($message);
        $post = Post::create('https://tent.io/types/post/essay/v0.1.0');
        $post->setContent(array(
            'body'    => $message,
            'excerpt' => String::getFirstParagraph($message)
        ));

        return $post;
    }

    private function createStatusPost($message)
    {
        $post = Post::create('https://tent.io/types/post/status/v0.1.0');
        $post->setContent(array('text' => $message));

        return $post;
    }

    private function addMentions(Post $post, $message, $mention)
    {
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
    }

    private function addPermissions(Post $post, array $permissions)
    {
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
    }
}
