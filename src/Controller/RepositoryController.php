<?php

namespace App\Controller;

use App\Entity\GitRepository;
use App\Form\GitRepositoryType;
use App\Repository\GitRepositoryRepository;
use App\Repository\UserRepository;
use App\Util\GitSf;
use finfo;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RepositoryController extends AbstractController {

    private $gitRepositoryRepository;

    private $userRepository;

    public function __construct(GitRepositoryRepository $gitRepositoryRepository, UserRepository $userRepository) {
        $this->gitRepositoryRepository = $gitRepositoryRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * @Route("/new", name="repo_create")
     */
    public function repo_create(Request $request) {
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

    private function checkRepo(string $user, string $repo) {
        $userRepo = $this->userRepository->findOneBy(['username' => $user]);
        $repoRepo = $this->gitRepositoryRepository->findOneBy(['user' => $userRepo, 'name' => $repo]);

        if ($userRepo == null || $repoRepo == null) {
            throw $this->createNotFoundException('Repo inconnu');
        }

        return new GitSf($user, $repo);
    }

    /**
     * @Route("/{user}/{repo}", name="repo_browse")
     */
    public function repo_browse(Request $request) {
        $git = $this->checkRepo($request->get('user'), $request->get('repo'));

        $nbCommits = $git->getNbCommits();
        $files = [];

        if ($nbCommits != 0) {
            $files = $git->getFilesOrFolder();
        }

        return $this->render('repo/browse.html.twig', [
            'user' => $request->get('user'),
            'repo' => $request->get('repo'),
            'nbCommits' => $nbCommits,
            'files' => $files,
            'folder' => '',
            'path' => [],
            'object' => 'master',
        ]);
    }

    /**
     * @Route("/{user}/{repo}/commits", name="repo_browse_commits")
     */
    public function repo_browse_commits(Request $request) {
        $git = $this->checkRepo($request->get('user'), $request->get('repo'));

        return $this->render('repo/commits.html.twig', [
            'user' => $request->get('user'),
            'repo' => $request->get('repo'),
            'log' => $git->getLog(),
        ]);
    }

    /**
     * @Route("/{user}/{repo}/commits/{object}", name="repo_browse_commits_object")
     */
    public function repo_browse_commits_object(Request $request, $object) {
        $git = $this->checkRepo($request->get('user'), $request->get('repo'));

        return $this->render('repo/commits.html.twig', [
            'user' => $request->get('user'),
            'repo' => $request->get('repo'),
            'log' => $git->getLog($object),
        ]);
    }

    /**
     * @Route("/{user}/{repo}/tree/{object}", name="repo_browse_commit")
     */
    public function repo_browse_commit(Request $request) {
        $git = $this->checkRepo($request->get('user'), $request->get('repo'));

        return $this->render('repo/browse.html.twig', [
            'nbCommits' => $git->getNbCommits($request->get('object')),
            'files' => $git->getFilesOrFolder($request->get('object')),
            'folder' => '',
            'path' => [],
            'user' => $request->get('user'),
            'repo' => $request->get('repo'),
            'object' => $request->get('object')
        ]);
    }

    /**
     * @Route("/{user}/{repo}/tree/{object}/{folder}", name="repo_browse_folder", requirements={"folder"=".+"})
     */
    public function repo_browse_folder(Request $request) {
        $git = $this->checkRepo($request->get('user'), $request->get('repo'));

        $folder = $request->get('folder');
        if (substr($folder, -1) == '/') $folder = substr_replace($folder, '', -1); // url ends with /

        $path = explode('/', $folder);
        $filename = array_pop($path);

        $commit = $request->get('object');

        $nbCommits = $git->getNbCommits($request->get('object'));
        $files = [];

        if ($nbCommits != 0) {
            $files = $git->getFilesOrFolder($commit, $folder);
        }

        return $this->render('repo/browse.html.twig', [
            'nbCommits' => $nbCommits,
            'files' => $files,
            'filename' => $filename,
            'folder' => $folder . '/',
            'path' => $path,
            'user' => $request->get('user'),
            'repo' => $request->get('repo'),
            'object' => $request->get('object')
        ]);
    }

    /**
     * @Route("/{user}/{repo}/blob/{object}/{file}", name="repo_get_file", requirements={"file"=".+"})
     */
    public function repo_browse_blob(Request $request) {
        $git = $this->checkRepo($request->get('user'), $request->get('repo'));

        $file = $request->get('file');

        $path = explode('/', $file);
        $filename = array_pop($path);

        $content = $git->getFile($file, $request->get('object'));

        $finfo = new finfo(FILEINFO_MIME);
        $mimetype = $finfo->buffer($content) . PHP_EOL;
        $mimetype = explode('/', $mimetype)[0];

        if ($mimetype == 'image') {
            $content = 'data:image/png;base64,' . base64_encode($content);
        }

        preg_match("/\.[0-9a-z]+$/i", $file, $extension);

        return $this->render('repo/file.html.twig', [
            'user' => $request->get('user'),
            'repo' => $request->get('repo'),
            'object' => $request->get('object'),
            'path' => $path,
            'filename' => $filename,
            'file' => $file,
            'content' => $content,
            'extension' => substr($extension[0], 1),
            'mimetype' => $mimetype
        ]);
    }

    /**
     * @Route("/{user}/{repo}/download/{object}/{file}", name="repo_download_file", requirements={"file"=".+"})
     */
    public function repo_download_file(Request $request) {
        $git = $this->checkRepo($request->get('user'), $request->get('repo'));
        $file = $request->get('file');
        $content = $git->getFile($file, $request->get('object'));

        if (strpos($file, '/')) {
            $file = explode('/', $file);
            $file = array_pop($file);
        }

        $response = new Response($content);
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $file
        );


        $response->headers->set('Content-Type', 'application/force-download');
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
}
