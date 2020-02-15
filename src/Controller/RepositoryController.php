<?php

namespace App\Controller;

use App\Entity\GitRepository;
use App\Entity\User;
use App\Form\AddContributorType;
use App\Form\GitRepositoryCreateType;
use App\Form\GitRepositoryOptionsType;
use App\Repository\GitRepositoryRepository;
use App\Repository\UserRepository;
use App\Util\GitHelper;
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
        $git = new GitHelper($user->getUsername(), $repo->getrepoName());

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
            return $this->redirectToRoute('repo_browse', ['username' => $user->getUsername(), 'repoName' => $repo->getRepoName()]);
        }

        $oldRepoName = $repo->getRepoName();

        $form = $this->createForm(GitRepositoryOptionsType::class, $repo);
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
            return $this->redirectToRoute('repo_browse', ['username' => $user->getUsername(), 'repoName' => $repo->getRepoName()]);
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

    /**
     * @Route("/{username}/{repoName}/collaborators", name="repo_collaborators")
     */
    public function collaborators(Request $request, User $user, GitRepository $repo) {
        if ($this->getUser() != $user) {
            return $this->redirectToRoute('repos');
        }

        $form = $this->createForm(AddContributorType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $username = $form->get('username')->getData();
            $userObj = $this->userRepository->findOneBy(['username' => $username]);

            if ($userObj == null) {
                $this->addFlash('error', 'Utilisateur inconnu.');
                return $this->redirectToRoute('repo_collaborators', ['username' => $user->getUsername(), 'repoName' => $repo->getRepoName()]);
            }
            if ($userObj == $this->getUser()) {
                $this->addFlash('error', 'Vous êtes déjà admin de ce dépôt.');
                return $this->redirectToRoute('repo_collaborators', ['username' => $user->getUsername(), 'repoName' => $repo->getRepoName()]);
            }
            if (in_array($userObj, $repo->getCollaborators()->toArray())) {
                $this->addFlash('error', 'Utilisateur déjà ajouté.');
                return $this->redirectToRoute('repo_collaborators', ['username' => $user->getUsername(), 'repoName' => $repo->getRepoName()]);
            }

            $this->addFlash('success', 'Utilisateur <b>' . $userObj->getUsername() . '</b> ajouté.');

            $repo->addCollaborator($userObj);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($repo);
            $entityManager->flush();

        }

        return $this->render('repo/collaborators.html.twig', [
            'user' => $user,
            'repo' => $repo,
            'collaborators' => $repo->getCollaborators(),
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/{username}/{repoName}/collaborators/remove", methods="POST", name="repo_collaborators_remove")
     */
    public function collaborators_delete(Request $request, User $user, GitRepository $repo): Response
    {
        if ($this->getUser() != $user) {
            return $this->redirectToRoute('repo_browse', ['username' => $user->getUsername(), 'repoName' => $repo->getRepoName()]);
        }

        if (!$this->isCsrfTokenValid('remove_collab', $request->request->get('token'))) {
            return $this->redirectToRoute('repo_browse', ['username' => $user, 'repoName' => $repo]);
        }

        if (($collborator = $this->userRepository->findOneBy(['username' => $request->query->get('collaborator')])) == null
        || !$repo->getCollaborators()->contains($collborator)) {
            $this->addFlash('error', 'L\'utilisateur n\'est pas invité dans ce dépôt.');
        } else {
            $this->addFlash('success', 'Utilisateur <b>'.$collborator->getUsername().'</b> retiré des collaborateurs du dépôt.');
            $repo->removeCollaborator($collborator);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($repo);
            $entityManager->flush();
        }

        return $this->redirectToRoute('repo_collaborators', ['username' => $user->getUsername(), 'repoName' => $repo->getRepoName()]);
    }
}
