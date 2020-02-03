<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\GitRepositoryRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class RepositoriesController extends AbstractController {

    private $gitRepositoryRepository;

    private $userRepository;

    public function __construct(GitRepositoryRepository $gitRepositoryRepository, UserRepository $userRepository) {
        $this->gitRepositoryRepository = $gitRepositoryRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * @Route("/", name="repos")
     */
    public function repos() {
        $repos = $this->gitRepositoryRepository->findAll();

        return $this->render('repos/index.html.twig', [
            'repos' => $repos
        ]);
    }

    /**
     * @Route("/{username}", name="repos_user")
     */
    public function repos_user(Request $request, User $user) {
        $repos = $this->gitRepositoryRepository->findBy(['user' => $user]);

        return $this->render('repos/user.html.twig', [
            'user' => $user,
            'repos' => $repos
        ]);
    }
}
