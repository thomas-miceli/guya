<?php

namespace App\Controller;

use App\Entity\GitRepository;
use App\Entity\User;
use App\Repository\GitRepositoryRepository;
use App\Repository\UserRepository;
use GitServer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class DotGitController extends AbstractController {

    private $gitRepositoryRepository;

    private $userRepository;

    private $passwordEncoder;

    public function __construct(GitRepositoryRepository $gitRepositoryRepository, UserRepository $userRepository, UserPasswordEncoderInterface $passwordEncoder) {
        $this->gitRepositoryRepository = $gitRepositoryRepository;
        $this->userRepository = $userRepository;
        $this->passwordEncoder = $passwordEncoder;
    }

    /**
     * @Route("/{username}/{repoName}.git/info/refs", name="git-refs")
     * @Route("/{username}/{repoName}.git/git-receive-pack", name="git-receive-pack")
     * @Route("/{username}/{repoName}.git/git-upload-pack", name="git-upload-pack")
     */
    public function delete(Request $request, User $user, GitRepository $repo) {
        $server = new GitServer($this->passwordEncoder, $repo->getUser(), $repo->getPrivate());
        $pathInfo = strstr($_SERVER["REQUEST_URI"], "?", true);
        $pathInfo || $pathInfo = $_SERVER["REQUEST_URI"];

        $response = new Response();

        $server->runAndSend($pathInfo, $response);
        return $response;

    }

}
