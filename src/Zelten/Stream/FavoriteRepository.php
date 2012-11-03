<?php
/**
 * Zelten
 *
 * LICENSE
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file LICENSE.txt.
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to kontakt@beberlei.de so I can send you a copy immediately.
 */

namespace Zelten\Stream;

use Doctrine\DBAL\Connection;
use TentPHP\Client;
use TentPHP\Post;

/**
 * Repository that handles favorite of posts.
 *
 * Favorites are saved on the tent server as posttype similar
 * to a repost:
 *
 *  - entity (required)
 *  - post (required)
 *
 * Because we want to ask the question "is_favorite" we need to cache this
 * information on the server, both in the in memory also in the database.
 */
class FavoriteRepository
{
    private $conn;
    private $tentClient;

    public function __construct(Connection $conn, Client $client)
    {
        $this->conn       = $conn;
        $this->tentClient = $client;
    }

    public function mark($ownerEntity, $entity, $post)
    {
        $favorite   = Post::create('http://www.beberlei.de/tent/favorite/v0.0.1');
        $favorite->setContent(array(
            'entity' => $entity,
            'post'   => $post,
        ));

        $userClient = $this->tentClient->getUserClient($ownerEntity, true);
        /*$original   = $userClient->getPost($post);

        if (!$original) {
            throw new \RuntimeException("not found");
        }*/

        $data        = $userClient->createPost($favorite);

        $this->conn->insert('favorites', array(
            'owner_entity' => $ownerEntity,
            'entity'       => $entity,
            'post'         => $post,
            'post_id'      => $data['id'],
        ));
    }

    public function unmark($ownerEntity, $entity, $post)
    {
        $id = $this->getPostId($ownerEntity, $entity, $post);

        if (empty($id)) {
            return;
        }

        $userClient = $this->tentClient->getUserClient($ownerEntity, true);
        $userClient->deletePost($id);

        $this->conn->delete('favorites', array(
            'owner_entity' => $ownerEntity,
            'entity'       => $entity,
            'post'         => $post,
        ));
    }

    private function getPostId($ownerEntity, $entity, $post)
    {
        $sql = 'SELECT post_id FROM favorites WHERE owner_entity = ? AND entity = ? AND post = ?';
        return $this->conn->fetchColumn($sql, array($ownerEntity, $entity, $post));
    }

    public function isFavorite($ownerEntity, $entity, $post)
    {
        $id = $this->getPostId($ownerEntity, $entity, $post);
        return !empty($id);
    }
}

