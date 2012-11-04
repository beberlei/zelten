<?php

namespace Zelten\Tests\Profile;

use Zelten\Tests\TestCase;
use Zelten\Profile\ProfileRepository;

class ProfileRepositoryTest extends TestCase
{
    private static $databaseRow = array(
        'entity'     => 'https://beberlei.tent.is',
        'name'       => 'Benjamin Eberlei',
        'avatar'     => 'https://beberlei',
        'location'   => 'Bonn',
        'bio'        => 'Some Bio',
        'gender'     => 'Male',
        'birthday'   => '2012-01-01',
        'email'      => 'foo@example.com',
        'occupation' => 'Something',
        'school'     => 'Some School',
        'updated'    => 0,
        'id'         => 1,
    );

    public function testGetProfileFromDatabase()
    {
        $client = $this->mock('TentPHP\Client');
        $conn = $this->mock('Doctrine\DBAL\Connection');
        $conn->shouldReceive('fetchAssoc')
             ->times(1)
             ->andReturn(self::$databaseRow);

        $profileRepository = new ProfileRepository($conn, $client);
        $data = $profileRepository->getProfile(self::$databaseRow['entity']);

        $this->assertEquals(array(
            'id' => 1,
            'uri' => self::$databaseRow['entity'],
            'entity' => 'https-beberlei.tent.is',
            'core' => array(
                'entity' => self::$databaseRow['entity'],
            ),
            'basic' => array(
                'name'       => 'Benjamin Eberlei',
                'avatar'     => 'https://beberlei',
                'location'   => 'Bonn',
                'bio'        => 'Some Bio',
                'gender'     => 'Male',
                'birthday'   => '2012-01-01',
            ),
            'name' => 'Benjamin Eberlei',
        ), $data);
    }

    public function testGetProfileFromTent()
    {
        $tentData = array(
            'entity' => 'https://beberlei.tent.is',
            'https://tent.io/types/info/core/v0.1.0' => array(
                'entity' => self::$databaseRow['entity'],
            ),
            'https://tent.io/types/info/basic/v0.1.0' => array(
                'name'       => 'Benjamin Eberlei',
                'avatar_url' => 'https://beberlei',
                'location'   => 'Bonn',
                'bio'        => 'Some Bio',
                'gender'     => 'Male',
                'birthdate'  => '2012-01-01',
            )
        );

        $userClient = $this->mock('TentPHP\UserClient');
        $userClient->shouldReceive('getProfile')
                   ->times(1)
                   ->andReturn($tentData);

        $client = $this->mock('TentPHP\Client');
        $client->shouldReceive('getUserClient')
               ->times(1)
               ->with(self::$databaseRow['entity'], false)
               ->andReturn($userClient);

        $conn = $this->mock('Doctrine\DBAL\Connection');
        $conn->shouldReceive('fetchAssoc')
             ->times(1)
             ->andReturn(false);
        $conn->shouldReceive('insert')
             ->times(1);
        $conn->shouldReceive('lastInsertId')->andReturn(1);

        $profileRepository = new ProfileRepository($conn, $client);
        $data = $profileRepository->getProfile(self::$databaseRow['entity']);

        $this->assertEquals(array(
            'id' => 1,
            'uri' => self::$databaseRow['entity'],
            'entity' => str_replace("https://", "https-", self::$databaseRow['entity']),
            'core' => array(
                'entity' => self::$databaseRow['entity'],
            ),
            'basic' => array(
                'name'       => 'Benjamin Eberlei',
                'avatar'     => 'https://beberlei',
                'location'   => 'Bonn',
                'bio'        => 'Some Bio',
                'gender'     => 'Male',
                'birthday'   => '2012-01-01',
            ),
            'name' => 'Benjamin Eberlei',
        ), $data);
    }
}

