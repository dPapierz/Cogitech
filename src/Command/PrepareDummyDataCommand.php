<?php

namespace App\Command;

use App\Entity\Post;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'dummy-data:prepare',
    description: 'Download dummy data from https://jsonplaceholder.typicode.com',
)]
class PrepareDummyDataCommand extends Command
{
    private $client;
    private $manager;
    private $hasher;

    public function __construct(HttpClientInterface $client, EntityManagerInterface $manager, UserPasswordHasherInterface $hasher)
    {
        $this->client = $client;
        $this->manager = $manager;
        $this->hasher = $hasher;

        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $url = "https://jsonplaceholder.typicode.com";

        $usersRequest = $this->client->request('GET', $url . "/users");
        $postsRequest = $this->client->request('GET', $url . "/posts");

        if ($postsRequest->getStatusCode() !== 200 || $usersRequest->getStatusCode() !== 200) {
            $io->error('Failed to download dummy data');
            return Command::FAILURE;
        }

        $POSTS = $postsRequest->toArray();
        $USERS = $usersRequest->toArray();

        try {
            foreach ($USERS as $USER) {
                $user = new User();
                
                $user->setUsername($USER['username']);
                $user->setPassword($this->hasher->hashPassword($user, $USER['username']));
                $user->setName($USER['name']);
                $user->setEmail($USER['email']);

                $this->manager->persist($user);
                $this->manager->flush();

                foreach ($POSTS as $POST) {
                    if ($POST['userId'] != $USER['id']) {
                        continue;
                    }

                    $post = new Post();

                    $post->setTitle($POST['title']);
                    $post->setBody($POST['body']);
                    $post->setUserName($USER['name']);
                    $post->setUser($user);

                    $this->manager->persist($post);
                }

                $this->manager->flush();
            }
        } catch (Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->success('Dummy data has been downloaded into the database');
        return Command::SUCCESS;
    }
}
