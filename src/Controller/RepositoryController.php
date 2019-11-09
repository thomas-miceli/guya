<?php

namespace App\Controller;

use App\Entity\GitRepository;
use App\Form\GitRepositoryType;
use App\Repository\GitRepositoryRepository;
use App\Repository\UserRepository;
use App\Util\GitRepo;
use App\Util\GitSf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;
use TQ\Git\Repository\Repository;

class RepositoryController extends AbstractController
{

    private $gitRepositoryRepository;

    private $userRepository;

    public function __construct(GitRepositoryRepository $gitRepositoryRepository, UserRepository $userRepository)
    {
        $this->gitRepositoryRepository = $gitRepositoryRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * @Route("/new", name="repo_create")
     */
    public function repo_create(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $repo = new GitRepository();

        $form = $this->createForm(GitRepositoryType::class, $repo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getUser()->addRepository($repo);
            $repo->setName($form->get('name')->getData());
            $repo->setPrivate(false);//$form->get('private')->getData());

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($repo);
            $entityManager->flush();

            $git = new GitSf($this->getUser()->getUsername(), $form->get('name')->getData());
            $git->init();

            return $this->redirectToRoute('repo_browse', ['user' => $this->getUser()->getUsername(), "repo" => $form->get('name')->getData()]);
        }

        return $this->render('repo/new.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/{user}/{repo}", name="repo_browse")
     */
    public function repo_browse(Request $request)
    {
        $git = $this->checkRepo($request->get('user'), $request->get('repo'));

        $nbCommits = $git->getNbCommits();
        $files = [];

        if ($nbCommits != 0) {
            $files = $git->getFiles();
        }
        return $this->render('repo/browse.html.twig', [
            'nbCommits' => $nbCommits,
            'files' => $files,
            'user' => $request->get('user'),
            'repo' => $request->get('repo'),
            'commit' => 'master'
        ]);
    }

    /**
     * @Route("/{user}/{repo}/commits", name="repo_browse_commits")
     */
    public function repo_browse_commits(Request $request)
    {
        $git = $this->checkRepo($request->get('user'), $request->get('repo'));

        return $this->render('repo/commits.html.twig', [
            'log' => $git->getLog(),
            'user' => $request->get('user'),
            'repo' => $request->get('repo')
        ]);
    }

    /**
     * @Route("/{user}/{repo}/commit/{commit}", name="repo_browse_commit")
     */
    public function repo_browse_commit(Request $request)
    {
        $git = $this->checkRepo($request->get('user'), $request->get('repo'));

        return $this->render('repo/browse.html.twig', [
            'nbCommits' => $git->getNbCommits($request->get('commit')),
            'files' => $git->getFiles($request->get('commit')),
            'user' => $request->get('user'),
            'repo' => $request->get('repo'),
            'commit' => $request->get('commit')
        ]);
    }

    /**
     * @Route("/{user}/{repo}/file/{commit}/{file}", name="repo_get_file", requirements={"file"=".+"})
     */
    public function repo_browse_file(Request $request)
    {
        $git = $this->checkRepo($request->get('user'), $request->get('repo'));

        $file = $request->get('file');

        $content = $git->getFile($file, $request->get('commit'));

        if (strpos($file, '/')) {
            $file = explode('/', $file);
            $file = array_pop($file);
        }

        $response = new Response($content);
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $file
        );

        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    private function checkRepo(string $user, string $repo) {
        $userRepo = $this->userRepository->findOneBy(['username' => $user]);
        $repoRepo = $this->gitRepositoryRepository->findOneBy(['user' => $userRepo, 'name' => $repo]);

        if ($userRepo == null || $repoRepo == null) {
            throw $this->createNotFoundException('Repo inconnu');
        }

        return new GitSf($user, $repo);
    }
}
