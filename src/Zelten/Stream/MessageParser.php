<?php

namespace Zelten\Stream;

use DateTime;
use Kwi\UrlLinker;
use Zelten\Util\Washtml;

class MessageParser
{
    private $supportedTypes = array(
        'https://tent.io/types/post/status/v0.1.0'    => 'status',
        'https://tent.io/types/post/essay/v0.1.0'     => 'essay',
        'https://tent.io/types/post/repost/v0.1.0'    => 'repost',
        'https://tent.io/types/post/follower/v0.1.0'  => 'follower',
        'http://www.beberlei.de/tent/bookmark/v0.0.1' => 'bookmark',
        'http://www.beberlei.de/tent/favorite/v0.0.1' => 'favorite',
    );

    private $linker;
    private $escaper;
    private $urlGenerator;

    public function __construct($urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
        $this->linker       = new UrlLinker();
        $this->escaper      = new Washtml(array('charset' => 'UTF-8'));
    }

    /**
     * Parse a tent post into a Zelten Message
     *
     * @param array $post
     * @param string $currentEntity
     * @param StreamRepository $repository
     * @return Message|null
     */
    public function parse(array $post, $currentEntity, StreamRepository $repository)
    {
        $message              = new Message();
        $message->id          = $post['id'];
        $message->type        = $this->supportedTypes[$post['type']];
        $message->content     = $post['content'];
        $message->entity      = $repository->getFullProfile($post['entity']);
        $message->app         = $post['app'];
        $message->mentions    = $post['mentions'];
        $message->permissions = $post['permissions'];
        $message->published   = new DateTime('@' . $post['published_at']);

        // switch a favorite
        if ($message->type == 'favorite') {
            return $repository->getPost($message->content['entity'], $message->content['post']);
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

                $profile = $repository->getFullProfile($mention['entity']);

                // If the mention contains a post id, this message is replying to that post id.
                if (!empty($mention['post'])) {
                    $message->content['reply'] = array(
                        'post'   => $mention['post'],
                        'entity' => $profile,
                    );
                }

                // Add all mentions to the message unless the mentioned entity is the logged-in entity.
                if ($mention['entity'] !== $currentEntity) {
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
            if ($message->entity['uri'] !== $currentEntity) {
                return;
            }

            // For new follower notifications, retrieve their full profile
            $message->content['follower'] = $repository->getFullProfile($message->content['entity']);

        } else if ($message->type == 'repost') {

            // For reposts, retrieve the original post
            if (!empty($message->content['entity']) && !empty($message->content['id'])) {
                $message->content['original'] = $repository->getPost($message->content['entity'], @$message->content['id']);
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

        return $message;
    }
}
