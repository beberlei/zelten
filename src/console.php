<?php

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use Doctrine\DBAL\Schema\Comparator;

$console = new Application('Zelten Console', '1.0');

$console
    ->register('doctrine:schema:update')
    ->setDescription('Update Doctrine Schema to match current definition.')
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {
        $conn       = $app['db'];
        $fromSchema = $conn->getSchemaManager()->createSchema();
        $toSchema   = $app['tent.user_storage']->createSchema();

        $userTable = $toSchema->createTable('users');
        $userTable->addColumn('entity', 'string');
        $userTable->addColumn('twitter_oauth_token', 'tentecstring', array('notnull' => false));
        $userTable->addColumn('twitter_oauth_secret', 'tentecstring', array('notnull' => false));
        $userTable->addColumn('last_login', 'datetime');
        $userTable->addColumn('login_count', 'integer', array('default' => 0));
        $userTable->addColumn('last_notification_update', 'datetime', array('default' => '2012-11-03 00:00:00'));
        $userTable->addColumn('bookmarks', 'integer', array('default' => 0));
        $userTable->setPrimaryKey(array('entity'));

        $favoriteTable = $toSchema->createTable('favorites');
        $favoriteTable->addColumn('id', 'integer', array('autoincrement' => true));
        $favoriteTable->addColumn('owner_entity', 'string');
        $favoriteTable->addColumn('entity', 'string');
        $favoriteTable->addColumn('post', 'string');
        $favoriteTable->addColumn('post_id', 'string');
        $favoriteTable->setPrimaryKey(array('id'));

        $profilesTable = $toSchema->createTable('profiles');
        $profilesTable->addColumn('id', 'integer', array('autoincrement' => true));
        $profilesTable->addColumn('entity', 'string');
        $profilesTable->addColumn('normalized_entity', 'string');
        $profilesTable->addColumn('name', 'string');
        $profilesTable->addColumn('avatar', 'string', array('default' => ''));
        $profilesTable->addColumn('location', 'string', array('default' => ''));
        $profilesTable->addColumn('bio', 'string', array('default' => ''));
        $profilesTable->addColumn('birthday', 'string', array('default' => ''));
        $profilesTable->addColumn('gender', 'string', array('default' => ''));
        $profilesTable->addColumn('email', 'string', array('default' => ''));
        $profilesTable->addColumn('occupation', 'string', array('default' => ''));
        $profilesTable->addColumn('school', 'string', array('default' => ''));
        $profilesTable->addColumn('updated', 'datetime');
        $profilesTable->addColumn('last_synchronized_relations', 'datetime');
        $profilesTable->setPrimaryKey(array('id'));
        $profilesTable->addUniqueIndex(array('entity'));

        $followingsTable = $toSchema->createTable('followings');
        $followingsTable->addColumn('profile_id', 'integer');
        $followingsTable->addColumn('following_id', 'integer');
        $followingsTable->addColumn('tent_id', 'string');
        $followingsTable->setPrimaryKey(array('profile_id', 'following_id'));

        $followerTable = $toSchema->createTable('followers');
        $followerTable->addColumn('profile_id', 'integer');
        $followerTable->addColumn('follower_id', 'integer');
        $followerTable->addColumn('tent_id', 'string');
        $followerTable->setPrimaryKey(array('profile_id', 'follower_id'));

        $comp = new Comparator();
        $diff = $comp->compare($fromSchema, $toSchema);

        foreach ($diff->toSQL($conn->getDatabasePlatform()) as $sql) {
            $output->writeln($sql);
            $conn->exec($sql);
        }
    })
;

$console
    ->register('tent:application')
    ->addArgument('server', InputArgument::REQUIRED, null, null)
    ->setDescription('Show details of the application on a given tent server')
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {
        $serverUrl = $input->getArgument('server');
        $client = $app['tent.client'];
        $client->updateApplication($serverUrl);
    });

return $console;
