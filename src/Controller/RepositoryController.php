<?php

namespace App\Controller;

use App\Entity\GitRepository;
use App\Entity\User;
use App\Form\GitRepositoryType;
use App\Form\RepositoryType;
use App\Repository\GitRepositoryRepository;
use App\Repository\UserRepository;
use App\Util\GitHelper;
use finfo;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
            $repoName = $form->get('repoName')->getData();
            $repo->setrepoName($repoName);
            $repo->setPrivate(false);//$form->get('private')->getData());

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($repo);
            $entityManager->flush();

            $git = new GitHelper($this->getUser(), $repoName);
            $git->init();

            return $this->redirectToRoute('repo_browse', [
                'username' => $this->getUser(),
                'repoName' => $repoName
            ]);
        }

        return $this->render('repo/new.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/{username}/{repoName}", name="repo_browse")
     */
    public function repo_browse(User $user, GitRepository $repo) {
        $git = new GitHelper($user, $repo->getrepoName());

        $nbCommits = $git->getNbCommits();
        $files = [];

        if ($nbCommits != 0) {
            $files = $git->getFilesOrFolder();
        }

        return $this->render('repo/browse.html.twig', [
            'user' => $user,
            'repo' => $repo,
            'nbCommits' => $nbCommits,
            'files' => $files,
            'folder' => '',
            'path' => [],
            'object' => 'master',
            'branches' => $git->getBranches(),
        ]);
    }

    /**
     * @Route("/{username}/{repoName}/commits", name="repo_browse_commits")
     */
    public function repo_browse_commits(User $user, GitRepository $repo) {
        $git = new GitHelper($user->getUsername(), $repo->getrepoName());

        return $this->render('repo/commits.html.twig', [
            'user' => $user,
            'repo' => $repo,
            'object' => 'master',
            'branches' => $git->getBranches(),
            'log' => $git->getLog(),
        ]);
    }

    /**
     * @Route("/{username}/{repoName}/commits/{object}", name="repo_browse_commits_object", requirements={"object"="^[0-9A-Za-z]+$"})
     */
    public function repo_browse_commits_object(User $user, GitRepository $repo, $object) {
        $git = new GitHelper($user->getUsername(), $repo->getrepoName());

        return $this->render('repo/commits.html.twig', [
            'user' => $user,
            'repo' => $repo,
            'object' => $object,
            'branches' => $git->getBranches(),
            'log' => $git->getLog($object),
        ]);
    }

    /**
     * @Route("/{username}/{repoName}/tree/{object}", name="repo_browse_commit", requirements={"object"="^[0-9A-Za-z]+$"})
     */
    public function repo_browse_commit(User $user, GitRepository $repo, $object) {
        $git = new GitHelper($user->getUsername(), $repo->getrepoName());

        return $this->render('repo/browse.html.twig', [
            'nbCommits' => $git->getNbCommits($object),
            'files' => $git->getFilesOrFolder($object),
            'folder' => '',
            'path' => [],
            'user' => $user,
            'repo' => $repo,
            'object' => $object,
            'branches' => $git->getBranches(),
        ]);
    }

    /**
     * @Route("/{username}/{repoName}/tree/{object}/{folder}", name="repo_browse_folder", requirements={"folder"=".+", "object"="^[0-9A-Za-z]+$"})
     */
    public function repo_browse_folder(User $user, GitRepository $repo, $object, $folder) {
        $git = new GitHelper($user->getUsername(), $repo->getrepoName());

        if (substr($folder, -1) == '/') $folder = substr_replace($folder, '', -1); // url ends with /

        $path = explode('/', $folder);
        $filename = array_pop($path);

        $nbCommits = $git->getNbCommits($object);
        $files = [];

        if ($nbCommits != 0) {
            $files = $git->getFilesOrFolder($object, $folder);
        }

        return $this->render('repo/browse.html.twig', [
            'nbCommits' => $nbCommits,
            'files' => $files,
            'filename' => $filename,
            'folder' => $folder . '/',
            'path' => $path,
            'user' => $user,
            'repo' => $repo,
            'object' => $object,
            'branches' => $git->getBranches(),
        ]);
    }

    /**
     * @Route("/{username}/{repoName}/blob/{object}/{file}", name="repo_get_file", requirements={"file"=".+", "object"="^[0-9A-Za-z]+$"})
     */
    public function repo_browse_blob(User $user, GitRepository $repo, $object, $file) {
        $git = new GitHelper($user->getUsername(), $repo->getrepoName());

        $path = explode('/', $file);
        $filename = array_pop($path);

        $content = $git->getFile($file, $object);

        $finfo = new finfo(FILEINFO_MIME);
        $mimetype = $finfo->buffer($content) . PHP_EOL;
        $mimetype = explode('/', $mimetype)[0];

        if ($mimetype == 'image') {
            $content = 'data:image/png;base64,' . base64_encode($content);
        }

        preg_match("/\.[0-9a-z]+$/i", $file, $extension);
        if (isset($extension[0])) {
            $extension = substr($extension[0], 1);
        } else {
            $extension = 'txt';
        }

        return $this->render('repo/file.html.twig', [
            'user' => $user,
            'repo' => $repo,
            'object' => $object,
            'path' => $path,
            'filename' => $filename,
            'file' => $file,
            'content' => $content,
            'extension' => $extension,
            'mimetype' => $mimetype
        ]);
    }

    /**
     * @Route("/{username}/{repoName}/download/{object}/{file}", name="repo_download_file", requirements={"file"=".+", "object"="^[0-9A-Za-z]+$"})
     */
    public function repo_download_file(User $user, GitRepository $repo, $object, $file) {
        $git = new GitHelper($user->getUsername(), $repo->getrepoName());
        $content = $git->getFile($file, $object);

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

    /**
     * @Route("/{username}/{repoName}/options", name="repo_options")
     */
    public function repo_options(Request $request, User $user, GitRepository $repo)
    {
        if ($this->getUser() != $user) {
            return $this->redirect('/');
        }

        $oldRepoName = $repo->getRepoName();

        $form = $this->createForm(RepositoryType::class, $repo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($oldRepoName != $repo->getRepoName()) {
                $git = new GitHelper($user->getUsername(), $oldRepoName);
                $git->rename($repo->getRepoName());
            }

            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('repo_browse', [
                'username' => $this->getUser(),
                'repoName' => $repo
            ]);
        }

        return $this->render('repo/options.html.twig', [
            'user' => $user,
            'repo' => $repo,
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/{username}/{repoName}/delete", methods="POST", name="repo_delete")
     */
    public function delete(Request $request, User $user, GitRepository $repo): Response
    {
        if ($this->getUser() != $user) {
            return $this->redirectToRoute('repos');
        }

        if (!$this->isCsrfTokenValid('delete', $request->request->get('token'))) {
            return $this->redirectToRoute('repo_browse', ['username' => $user, 'repoName' => $repo]);
        }

        $git = new GitHelper($user->getUsername(), $repo->getrepoName());

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->remove($repo);
        $entityManager->flush();
        $git->delete();

        return $this->redirectToRoute('repos');
    }
}
