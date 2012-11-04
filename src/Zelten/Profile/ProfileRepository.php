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

namespace Zelten\Profile;

use Doctrine\DBAL\Connection;
use TentPHP\Client;
use Guzzle\Common\Exception\GuzzleException;

/**
 * Repository for User Profiles
 *
 * The most important public information is cached, so
 * that we never have to hit the actual Tent servers
 * getting these.
 *
 * - Entity URL
 * - Name
 * - Avatar
 * - Location
 * - Bio
 * - Birthday
 * - Email
 * - Occupation
 * - School
 * - Gender
 *
 * We also manage followings and follower caches here.
 * We have a populate method to fetch all related
 * entities and then update them.
 */
class ProfileRepository
{
    /**
     * @var Connection
     */
    private $conn;

    /**
     * @var Client
     */
    private $tentClient;

    /**
     * @var array
     */
    private $supportedProfileTypes = array(
        'https://tent.io/types/info/core/v0.1.0' => array(
            'name'   => 'core',
            'fields' => array(
                'entity' => 'entity',
            ),
        ),
        'https://tent.io/types/info/basic/v0.1.0'  => array(
            'name'   => 'basic',
            'fields' => array(
                'name'       => 'name',
                'avatar_url' => 'avatar',
                'birthdate'  => 'birthday',
                'location'   => 'location',
                'gender'     => 'gender',
                'bio'        => 'bio',
            ),
        ),
    );

    public function __construct(Connection $conn, Client $tentClient)
    {
        $this->conn       = $conn;
        $this->tentClient = $tentClient;
    }

    /**
     * Get the profile for a given entity.
     *
     * @return array
     */
    public function getProfile($entityUrl)
    {
        $sql = 'SELECT * FROM profiles WHERE entity = ?';
        $row = $this->conn->fetchAssoc($sql, array($entityUrl));

        if ($row && strtotime($row['updated'])-3600 < time()) {
            return $this->parseDatabaseProfile($row);
        }

        $id = isset($row['id']) ? $row['id'] : null;

        try {
            $userClient = $this->tentClient->getUserClient($entityUrl, false);
            $data       = $userClient->getProfile();
        } catch(GuzzleException $e) {
            $data = array();
        }

        return $this->parseTentProfile($entityUrl, $data, $id);
    }

    private function fixUri($entityUri)
    {
        return str_replace(array('https://', 'http://'), array('https-', 'http-'), $entityUri);
    }

    private function parseDatabaseProfile($row)
    {
        $profile = array('id' => $row['id'], 'entity' => $this->fixUri($row['entity']), 'uri' => $row['entity']);
        foreach ($this->supportedProfileTypes as $profileType => $data) {
            $name = $data['name'];

            foreach ($data['fields'] as $tent => $field) {
                if (empty($profile[$name][$field])) {
                    $profile[$name][$field] = $row[$field];
                }
            }
        }

        $profile['name'] = $profile['basic']['name'];

        return $profile;
    }

    private function parseTentProfile($entity, $data, $id = false)
    {
        $profile = array('name' => $entity, 'entity' => $this->fixUri($entity), 'uri' => $entity);
        $row     = array('updated' => date('Y-m-d H:i:s'));

        foreach ($this->supportedProfileTypes as $profileType => $profileData) {
            $name = $profileData['name'];

            foreach ($profileData['fields'] as $tentName => $fieldName) {
                if (empty($profile[$name][$fieldName])) {
                    $profile[$name][$fieldName] = "";
                }
            }

            if (isset($data[$profileType])) {
                foreach ($profileData['fields'] as $tentName => $fieldName) {
                    if (isset($data[$profileType][$tentName])) {
                        $profile[$name][$fieldName] = $data[$profileType][$tentName];
                        $row[$fieldName]            = $data[$profileType][$tentName];
                    }
                }
            }
        }

        if (!empty($profile['basic']['name'])) {
            $profile['name'] = $profile['basic']['name'];
        }

        if (!empty($profile['core']['entity'])) {
            $profile['entity'] = $this->fixUri($profile['core']['entity']);
        }

        if (empty($row['entity'])) {
            return $profile;
        }

        if ($id) {
            $this->conn->update('profiles', $row, array('id' => $id));
        } else {
            $this->conn->insert('profiles', $row);
            $profile['id'] = $this->conn->lastInsertId();
        }

        return $profile;
    }

    public function getFollowings($entityUrl, $limit = 5)
    {
        $profile = $this->getProfile($entityUrl);
        $table = 'followings';
        $column = 'following_id';
        $listMethod = 'getFollowings';
        $countMethod = 'getFollowingCount';

        return $this->getPeoples($profile, $table, $column, $listMethod, $countMethod, $limit);
    }

    private function getPeoples($profile, $table, $column, $listMethod, $countMethod, $limit)
    {
        $userClient = $this->tentClient->getUserClient($profile['uri'], false);
        $sql = "SELECT count(*) FROM $table f INNER JOIN profiles p ON p.id = f.$column WHERE profile_id = ?";
        $cnt = $this->conn->fetchColumn($sql, array($profile['id']));

        if ($cnt == 0) {
            $results = $userClient->$listMethod(array('limit' => $limit));
            $cnt     = $userClient->$countMethod();

            $peoples = array();
            foreach ($results as $result) {
                $peoples[] = $this->getProfile($result['entity']);
            }
            $peoples = array_slice($peoples, 0, $limit);
        } else {
            $sql = "SELECT * FROM followings f INNER JOIN profiles p ON p.id = f.following_id WHERE profile_id = ? LIMIT " . intval($limit);
            $rows = $this->conn->fetchAll($sql, array($profile['id']));

            $peoples = array();
            foreach ($rows as $row) {
                $peoples[] = $this->parseDatabaseProfile($row);
            }
        }

        return array('list' => $peoples, 'total' => $cnt);
    }

    public function getFollowers($entityUrl, $limit = 5)
    {
        $profile = $this->getProfile($entityUrl);
        $table = 'followers';
        $column = 'follower_id';
        $listMethod = 'getFollowers';
        $countMethod = 'getFollowerCount';

        return $this->getPeoples($profile, $table, $column, $listMethod, $countMethod, $limit);
    }

    public function synchronizeRelationsOverdue($entityUrl)
    {
        $sql             = "SELECT UNIX_TIMESTAMP(last_synchronized_relations) FROM profiles WHERE entity = ?";
        $lastSynchronize = (int)$this->conn->fetchColumn($sql, array($entityUrl));
        $resyncTreshold  = time() - 60 * 60 * 24 * 7;

        return ($lastSynchronize < $resyncTreshold);
    }

    public function skipSynchronize($entityUrl)
    {
        $profile    = $this->getProfile($entityUrl);
        $this->updateLastSynchronizedRelations($profile['id']);
    }

    private function updateLastSynchronizedRelations($id)
    {
        $sql = "UPDATE profiles SET last_synchronized_relations = NOW() WHERE id = ?";
        $this->conn->executeUpdate($sql, array($id));
    }

    public function synchronizeRelations($entityUrl)
    {
        $profile    = $this->getProfile($entityUrl);
        $userClient = $this->tentClient->getUserClient($entityUrl, false);

        $this->conn->beginTransaction();

        try {
            $this->synchronizeRelationGroup($userClient, $profile['id'], 'followings', 'following_id', 'getFollowings');
            $this->synchronizeRelationGroup($userClient, $profile['id'], 'followers', 'follower_id', 'getFollowers');

            $this->updateLastSynchronizedRelations($profile['id']);

            $this->conn->commit();

        } catch(\Exception $e) {
            $this->conn->rollback();
            throw $e;
        }

        return $profile['id'];
    }

    private function synchronizeRelationGroup($userClient, $profileId, $tableName, $columnName, $method)
    {
        $sql = 'INSERT IGNORE INTO ' . $tableName .' (profile_id, ' . $columnName . ', tent_id) VALUES (?, ?, ?)';
        $this->conn->delete($tableName, array('profile_id' => $profileId));
        $stmt = $this->conn->prepare($sql);
        $params = array('limit' => 50);

        $seenBefore = array();
        do {
            try {
                $persons = $userClient->$method($params);
            } catch(GuzzleException $e) {
                $persons = array();
            }

            foreach ($persons as $person) {
                $personProfile    = $this->getProfile($person['entity']);

                if (isset($personProfile['id']) && isset($seenBefore[$personProfile['id']])) {
                    return;
                }

                if (isset($personProfile['id'])) {
                    $params['before_id'] = $person['id'];
                    $seenBefore[$personProfile['id']] = true;

                    $stmt->bindValue(1, $profileId);
                    $stmt->bindValue(2, $personProfile['id']);
                    $stmt->bindValue(3, $person['id']);
                    $stmt->execute();
                }
            }

        } while(count($persons) == 50);
    }

    /**
     * Follow an entity
     *
     * @param string $currentEntity
     * @param string $followEntity
     * @return
     */
    public function follow($currentEntity, $followEntity)
    {
        $profileEntity  = $this->getProfile($currentEntity);
        $followerEntity = $this->getProfile($followEntity);

        $userClient = $this->tentClient->getUserClient($currentEntity, true);
        try {
            $data       = $userClient->follow($followEntity);

            $this->updateRelationship('followings', 'following_id', $profileEntity['id'], $followerEntity['id'], $data['id'], 'follow');
            return $data;
        } catch(GuzzleException $e) {
            return array();
        }
    }

    /**
     * Unfollow an entity
     *
     * @param string $currentEntity
     * @param string $followEntity
     * @return
     */
    public function unfollow($currentEntity, $unfollowEntity)
    {
        $profileEntity  = $this->getProfile($currentEntity);
        $followerEntity = $this->getProfile($unfollowEntity);

        $sql        = "SELECT tent_id FROM followings WHERE profile_id = ? AND following_id = ?";
        $unfollowId = $this->conn->fetchColumn($sql, array($profileEntity['id'], $followerEntity['id']));

        if (empty($unfollowId)) {
            return;
        }

        $this->updateRelationship('followings', 'following_id', $profileEntity['id'], $followerEntity['id'], $unfollowId, 'unfollow');

        $userClient = $this->tentClient->getUserClient($currentEntity, true);

        try {
            $userClient->unfollow($unfollowId);
        } catch(GuzzleException $e) {
        }
    }

    /**
     * Is entity following the other entity?
     *
     * @param string $entity
     * @param string $followEntity
     * @return
     */
    public function isFollowing($entity, $followEntity)
    {
        $profileEntity  = $this->getProfile($entity);
        $followerEntity = $this->getProfile($followEntity);

        $sql = "SELECT count(*) FROM followings WHERE profile_id = ? AND following_id = ?";

        $isFollowing = $this->conn->fetchColumn($sql, array($profileEntity['id'], $followerEntity['id'])) > 0;
        return $isFollowing;
    }

    public function updateFollowing($entityUrl, $tentId, $followEntity, $action)
    {
        $profileEntity  = $this->getProfile($entity);
        $followerEntity = $this->getProfile($followEntity);

        return $this->updateRelationship('followings', 'following_id', $profileEntity['id'], $followerEntity['id'], $tentId, $action);
    }

    public function updateFollower($entityUrl, $tentId, $followEntity, $action)
    {
        $profileEntity  = $this->getProfile($entity);
        $followerEntity = $this->getProfile($followEntity);

        return $this->updateRelationship('followers', 'follower_id', $profileEntity['id'], $followerEntity['id'], $tentId, $action);
    }

    private function updateRelationship($table, $relatedTable, $profileId, $followerId, $tentId, $action)
    {
        if ($action == "follow") {
            $sql    = "INSERT IGNORE INTO $table (profile_id, $relatedTable, tent_id) VALUES (?, ?, ?)";
            $params = array($profileId, $followerId, $tentId);
        } else if ($action == "unfollow") {
            $sql = "DELETE FROM $table WHERE profile_id = ? AND $relatedTable = ?";
            $params = array($profileId, $followerId);
        }

        $this->conn->executeUpdate($sql, $params);
    }
}

