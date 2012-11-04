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
        $userClient = $this->tentClient->getUserClient($profile['uri']);
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

    public function sychronizeRelations($entityUrl)
    {
        $profile    = $this->getProfile($entityUrl);
        $userClient = $this->tentClient->getUserClient($entityUrl, false);

        $this->conn->beginTransaction();

        try {
            $this->synchronizeRelationGroup($userClient, $profile['id'], 'followings', 'INSERT IGNORE INTO followings (profile_id, following_id) VALUES (?, ?)', 'getFollowings');
            $this->synchronizeRelationGroup($userClient, $profile['id'], 'followers', 'INSERT IGNORE INTO followers (profile_id, follower_id) VALUES (?, ?)', 'getFollowers');

            $sql = "UPDATE profiles SET last_synchronized_relations = NOW() WHERE id = ?";
            $this->conn->executeUpdate($sql, array($profile['id']));

            $this->conn->commit();

        } catch(\Exception $e) {
            $this->conn->rollback();
            throw $e;
        }

        return $profile['id'];
    }

    private function synchronizeRelationGroup($userClient, $profileId, $tableName, $sql, $method)
    {
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
                    $stmt->execute();
                }
            }

        } while(count($persons) == 50);
    }

    public function updateFollowing($entityUrl, $followingEntity, $action)
    {
    }

    public function updateFollower($entityUrl, $followerEntity, $action)
    {
    }
}

