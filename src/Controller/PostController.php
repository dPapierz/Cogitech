<?php

namespace App\Controller;

use App\Entity\Post;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class PostController extends AbstractController
{
    private $manager;

    public function __construct(EntityManagerInterface $manager) {
        $this->manager = $manager;
    }

    #[Route('/lista', name: 'app_post', methods: ['GET'])]
    public function index(): Response {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $repository = $this->manager->getRepository(Post::class);
        $POSTS = $repository->findAll();

        return $this->render('post/index.html.twig', [
            'posts' => $POSTS,
        ]);
    }

    #[Route('/lista/delete/{id}', name: 'app_delete_post', methods: ['GET', 'DELETE'])]
    public function delete($id): Response {
        $repository = $this->manager->getRepository(Post::class);
        $post = $repository->find($id);

        if ($this->getUser()->getId() !== $post->getUser()->getId()) {
            return $this->redirect('/lista');
        }

        $this->manager->remove($post);
        $this->manager->flush();

        return $this->redirect('/lista');
    }

    #[Route('/posts', name: 'app_api_post', methods: ['GET'])]
    public function posts(): Response {
        $repository = $this->manager->getRepository(Post::class);
        $POSTS = $repository->findAll();

        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));

        $encoders = [new JsonEncoder()];
        $normalizers = [new ObjectNormalizer($classMetadataFactory)];
        $serializer = new Serializer($normalizers, $encoders);

        return new Response(
            $serializer->serialize($POSTS, 'json', ['groups' => 'api']),
            Response::HTTP_OK,
            ['Content-type' => 'application/json']
        );
    }
}
