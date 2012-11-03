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
use Guzzle\Http\Exception\CurlException;

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
        $sql = 'SELECT * FROM profiles WHERE entity = ? AND updated > ?';
        $row = $this->conn->fetchAssoc($sql, array($entityUrl, strtotime('-1 day')));

        if ($row) {
            return $this->parseDatabaseProfile($row);
        }

        try {
            $userClient = $this->tentClient->getUserClient($entityUrl, false);
            $data       = $userClient->getProfile();
        } catch(CurlException $e) {
            $data = array();
        }

        return $this->parseTentProfile($entityUrl, $data);
    }

    private function fixUri($entityUri)
    {
        return str_replace(array('https://', 'http://'), array('https-', 'http-'), $entityUri);
    }

    private function parseDatabaseProfile($row)
    {
        $profile = array('entity' => $this->fixUri($row['entity']), 'uri' => $row['entity']);
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

    private function parseTentProfile($entity, $data)
    {
        $profile = array('name' => $entity, 'entity' => $this->fixUri($entity), 'uri' => $entity);
        $row     = array('updated' => date('Y-m-d H:i:s'));

        foreach ($this->supportedProfileTypes as $profileType => $profileData) {
            $name = $profileData['name'];

            if (isset($data[$profileType])) {
                foreach ($profileData['fields'] as $tentName => $fieldName) {
                    $profile[$name][$fieldName] = $data[$profileType][$tentName];
                    $row[$fieldName]            = $data[$profileType][$tentName];
                }
            } else {
                foreach ($profileData['fields'] as $tentName => $fieldName) {
                    if (empty($profile[$name][$fieldName])) {
                        $profile[$name][$fieldName] = "";
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

        try {
            $this->conn->beginTransaction();
            $this->conn->delete('profiles', array('entity' => $row['entity']));
            $this->conn->insert('profiles', $row);
            $this->conn->commit();
        } catch(\Exception $e) {
            $this->conn->rollback();
            throw $e;
        }

        return $profile;
    }

    public function getFollowings($entityUrl)
    {
    }

    public function getFollowers($entityUrl)
    {
    }

    public function sychronizeRelations($entityUrl)
    {
    }

    public function updateFollowing($entityUrl, $followingEntity, $action)
    {
    }

    public function updateFollower($entityUrl, $followerEntity, $action)
    {
    }
}

