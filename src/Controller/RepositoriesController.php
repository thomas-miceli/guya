<?php

namespace App\Controller;

use App\Entity\GitRepository;
use App\Entity\User;
use App\Form\GitRepositoryCreateType;
use App\Repository\GitRepositoryRepository;
use App\Repository\UserRepository;
use App\Util\GitHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class RepositoriesController extends AbstractController
{

    private $gitRepositoryRepository;

    private $userRepository;

    public function __construct(GitRepositoryRepository $gitRepositoryRepository, UserRepository $userRepository)
    {
        $this->gitRepositoryRepository = $gitRepositoryRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * @Route("/", name="repos")
     */
    public function repos()
    {
        $repos = $this->gitRepositoryRepository->findByAllAsUser($this->getUser());

        return $this->render('repos/index.html.twig', [
            'repos' => $repos
        ]);
    }

    /**
     * @Route("/new", name="repo_create")
     */
    public function repo_create(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $repo = new GitRepository();

        $form = $this->createForm(GitRepositoryCreateType::class, $repo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getUser()->addRepository($repo);
            $repoName = $form->get('repoName')->getData();
            $repo->setrepoName($repoName);
            $repo->setPrivate($form->get('private')->getData());

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($repo);
            $entityManager->flush();

            $git = new GitHelper($this->getUser()->getUsername(), $repoName);
            $git->init();

            return $this->redirectToRoute('repo_browse', [
                'username' => $this->getUser()->getUsername(),
                'repoName' => $repoName
            ]);
        }

        return $this->render('repo/new.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/{username}", name="repos_user")
     */
    public function repos_user(Request $request, User $user)
    {
        if ($user == $this->getUser()) {
            $repos = $this->gitRepositoryRepository->findBy(['user' => $user]);
        } else {
            $repos = $this->gitRepositoryRepository->findByUserAsUser($this->getUser(), $user);
        }

        return $this->render('repos/user.html.twig', [
            'user' => $user,
            'repos' => $repos
        ]);
    }

}
