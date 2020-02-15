<?php

namespace App\Controller;

use App\Entity\GitRepository;
use App\Entity\User;
use App\Repository\GitRepositoryRepository;
use App\Repository\UserRepository;
use App\Util\GitServer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class DotGitController extends AbstractController
{

    private $gitRepositoryRepository;

    private $userRepository;

    private $passwordEncoder;

    public function __construct(GitRepositoryRepository $gitRepositoryRepository, UserRepository $userRepository, UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->gitRepositoryRepository = $gitRepositoryRepository;
        $this->userRepository = $userRepository;
        $this->passwordEncoder = $passwordEncoder;
    }

    /**
     * @Route("/{username}/{repoName}.git/info/refs", name="git_refs")
     * @Route("/{username}/{repoName}.git/git-receive-pack", name="git_receive_pack")
     * @Route("/{username}/{repoName}.git/git-upload-pack", name="git_upload_pack")
     */
    public function git_http(Request $request, User $user, GitRepository $repo)
    {
        $users[] = $repo->getUser();
        $users = array_merge($users, $repo->getCollaborators()->toArray());

        $server = new GitServer($this->passwordEncoder, $users, $repo->isPrivate());
        $pathInfo = strstr($_SERVER["REQUEST_URI"], "?", true);
        $pathInfo || $pathInfo = $_SERVER["REQUEST_URI"];

        $response = new Response();

        $server->runAndSend($pathInfo, $response);
        return $response;

    }

}
