<?php

namespace App\Util;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class GitHelper {

    public const GIT_FOLDERS = '../repos/';
    public const GIT_FOLDERS_CMD = './repos/';

    private $userFolder;
    private $pathFolder;

    public function __construct(string $user, string $folderName) {
        $this->userFolder = self::GIT_FOLDERS . $user . '/';
        $this->pathFolder = self::GIT_FOLDERS . $user . '/' . $folderName . '.git';
    }

    public function init() {
        $this->sfProcess('mkdir ' . $this->pathFolder, self::GIT_FOLDERS);
        $this->sfProcess('git init --bare --template=../../__bare/');
    }

    public function rename(string $name) {
        $this->sfProcess('mv ' . $this->pathFolder . ' ' . $this->userFolder . $name . '.git', self::GIT_FOLDERS);
    }

    public function delete() {
        $this->sfProcess('rm -rf ' . $this->pathFolder, self::GIT_FOLDERS);
    }

    private function sfProcess(string $cmd, string $wd = '') {
        $process = new Process(explode(' ', $cmd));
        if (empty($wd)) {
            $process->setWorkingDirectory($this->pathFolder);
        } else {
            $process->setWorkingDirectory($wd);
        }
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return rtrim($process->getOutput());
    }

    public function getLog($object = 'master') {
        if ($this->getNbCommits() == 0) {
            return null;
        }

        $output = $this->sfProcess('git log ' . $object);

        if (empty($output)) return null;

        $history = array();
        $commit = array();
        foreach (explode("\n", $output) as $line) {
            if (strpos($line, 'commit') === 0) {
                if (!empty($commit)) {
                    array_push($history, $commit);
                    unset($commit);
                }
                $commit['hash'] = substr($line, strlen('commit '));
            } else if (strpos($line, 'Author') === 0) {
                $commit['author'] = substr($line, strlen('Author: '));
            } else if (strpos($line, 'Date') === 0) {
                $commit['date'] = substr($line, strlen('Date: '));
            } else {
                if (isset($commit['message']))
                    $commit['message'] .= $line;
                else
                    $commit['message'] = $line;
            }
        }
        if (!empty($commit)) {
            array_push($history, $commit);
        }

        return $history;
    }

    public function getNbCommits($object = 'master') {
        if ($this->sfProcess('git rev-list --count --all') == 0) return 0; // If le projet est complètement vide

        return $this->sfProcess('git rev-list --count ' . $object);
    }

    public function getFile($file, $object = 'master') {
        return $this->sfProcess('git show ' . $object . ':' . $file);
    }

    public function getFilesOrFolder($object = 'master', $fileOrFolder = '') {
        $files = $this->sfProcess('git ls-tree --full-name ' . $object . ':' . $fileOrFolder);
        $files = explode("\n", $files);

        foreach ($files as $key => $value) {
            $files[$key] = preg_split("/[\s,]+/", $value);
        }

        uasort($files, function ($a, $b) {
            return strcmp($b[1], $a[1]);
        });

        return $files;
    }

    public function getBranches() {
        $branches = $this->sfProcess('git branch --format=\'%(refname:short)\'');

        return explode("\n", str_replace('\'', '', $branches));
    }

}
