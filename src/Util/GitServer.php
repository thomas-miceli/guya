<?php

use App\Entity\User;
use App\Util\GitHelper;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class GitServer
{

    private $root;

    private $headers = [];

    private $body = '';

    private $statusCode = 200;

    private $errorMessage = '';

    private $passwordEncoder;

    private $users;

    private $private;

    /**
     * GitServer constructor.
     * @param UserPasswordEncoderInterface $passwordEncoder Symfony password encoder
     * @param User $users Users (only the owner atm) who has access to the repo
     * @param bool $private If everybody can clone the repo
     */
    public function __construct(UserPasswordEncoderInterface $passwordEncoder, $users, $private)
    {
        $this->root = realpath(GitHelper::GIT_FOLDERS);
        $this->passwordEncoder = $passwordEncoder;
        $this->users = $users;
        $this->private = $private;
    }

    public function run($pathInfo)
    {
        $env = [
            "PATH" => getenv("PATH"),
            "REQUEST_METHOD" => $_SERVER["REQUEST_METHOD"],
            "QUERY_STRING" => $_SERVER["QUERY_STRING"] ?? null,
            "REMOTE_ADDR" => $_SERVER["REMOTE_ADDR"],
            "REMOTE_USER" => $_SERVER["USER"] ?? null,
            "CONTENT_TYPE" => $_SERVER["CONTENT_TYPE"] ?? $_SERVER["HTTP_CONTENT_TYPE"] ?? null,
            "GIT_HTTP_EXPORT_ALL" => "",
            "GIT_PROJECT_ROOT" => $this->root,
            "PATH_INFO" => $pathInfo,
        ];

        $process = proc_open('git http-backend', [
            ["pipe", "r"],
            ["pipe", "w"],
        ], $pipes, null, $env);

        if (!is_resource($process)) {
            $this->error(510, 'create process error');
            return;
        }

        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            fwrite($pipes[0], file_get_contents("php://input"));
            fclose($pipes[0]);
        }

        $content = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $return_value = proc_close($process);
        if ($return_value) {
            $err = stream_get_contents($pipes[2]);
            $this->error(510, $err);
            return;
        }

        list($header, $body) = explode("\r\n\r\n", $content);
        $this->headers = explode("\n", $header);
        $this->headers = array_map("trim", $this->headers);

        $this->body = $body;
    }

    private function error($code, $message = '')
    {
        $this->statusCode = $code;
        $this->errorMessage = $message;
    }

    public function runAndSend($pathInfo, Response $response)
    {
        $user = $_SERVER["PHP_AUTH_USER"] ?? null;
        $password = $_SERVER["PHP_AUTH_PW"] ?? null;

        if ($this->private == true || ($_GET['service'] ?? null) == 'git-receive-pack') {
            if (!(
                $user &&
                $password &&
                $this->users->getUsername() == $user &&
                $this->users->getPassword() == $this->passwordEncoder->isPasswordValid($this->users, $password)
            )
            ) {
                $response->setStatusCode(Response::HTTP_UNAUTHORIZED);
                $response->headers->set('WWW-Authenticate', 'Basic realm="git"');
                return;
            }
        }

        $this->run($pathInfo);

        if ($this->statusCode != 200) {
            $response->setStatusCode($this->statusCode);
            $response->setContent($this->errorMessage);
            return;
        }

        foreach ($this->headers as $header) {
            $key = strtok($header, ':');
            $val = strtok(null);
            $response->headers->set($key, $val);
        }

        $response->setContent($this->body);
    }
}
