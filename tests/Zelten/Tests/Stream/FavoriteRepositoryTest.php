<?php

namespace Zelten\Tests\Stream;

use Zelten\Tests\TestCase;
use Zelten\Stream\FavoriteRepository;

class FavoriteRepositoryTest extends TestCase
{
    const OWNER = 'https://beberlei.tent.is';
    const ENTITY = 'https://jeena.net';
    const POST = 'abcdefg';

    public function testMarkFavorite()
    {
        $userClient = $this->mock('TentPHP\UserClient');
        /*$userClient->shouldReceive('getPost')
                   ->times(1)
                   ->with(self::POST)
                   ->andReturn(new \TentPHP\Post(array()));*/
        $userClient->shouldReceive('createPost')
                   ->times(1)
                   ->with(\Mockery::type('TentPHP\Post'))
                   ->andReturn(array('id' => 'abcdefg'));

        $client = $this->mockTentClientReturnsUserClient($userClient);

        $conn = $this->mock('Doctrine\DBAL\Connection');
        $conn->shouldReceive('insert')->times(1)->with('favorites', array(
                'owner_entity' => self::OWNER,
                'entity'       => self::ENTITY,
                'post'         => self::POST,
                'post_id'      => 'abcdefg',
        ));

        $favoriteRepository= new FavoriteRepository($conn, $client);
        $favoriteRepository->mark(self::OWNER, self::ENTITY, self::POST);
    }

    public function testUnmarkFavorite()
    {
        $userClient = $this->mock('TentPHP\UserClient');
        $userClient->shouldReceive('deletePost')->with('abcdefg');
        $client = $this->mockTentClientReturnsUserClient($userClient);

        $conn = $this->mock('Doctrine\DBAL\Connection');
        $conn->shouldReceive('fetchColumn')->times(1)->andReturn('abcdefg');
        $conn->shouldReceive('delete')->times(1);

        $favoriteRepository= new FavoriteRepository($conn, $client);
        $favoriteRepository->unmark(self::OWNER, self::ENTITY, self::POST);
    }

    public function testIsFavorite()
    {
        $client = $this->mock('TentPHP\Client');
        $conn = $this->mock('Doctrine\DBAL\Connection');
        $conn->shouldReceive('fetchColumn')->times(1)->andReturn('abcdefg');

        $favoriteRepository= new FavoriteRepository($conn, $client);
        $this->assertTrue($favoriteRepository->isFavorite(self::OWNER, self::ENTITY, self::POST));
    }

    public function testIsNotFavorite()
    {
        $client = $this->mock('TentPHP\Client');
        $conn = $this->mock('Doctrine\DBAL\Connection');
        $conn->shouldReceive('fetchColumn')->times(1)->andReturn(false);

        $favoriteRepository= new FavoriteRepository($conn, $client);
        $this->assertFalse($favoriteRepository->isFavorite(self::OWNER, self::ENTITY, self::POST));
    }

    private function mockTentClientReturnsUserClient($userClient)
    {
        $client = $this->mock('TentPHP\Client');
        $client->shouldReceive('getUserClient')
               ->times(1)
               ->with(self::OWNER, true)
               ->andReturn($userClient);

        return $client;
    }
}

