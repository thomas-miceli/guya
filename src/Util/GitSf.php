<?php

namespace App\Util;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class GitSf {

    public const GIT_FOLDERS = '/home/thomas/gitlist/';

    private $pathFolder;

    public function __construct(string $user, string $folderName) {
        $this->pathFolder = self::GIT_FOLDERS . $user . '/' . $folderName . '.git';
    }

    public function init() {
        $this->sfProcess('mkdir ' . $this->pathFolder, self::GIT_FOLDERS);
        $this->sfProcess('git --bare init');
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

        return $process->getOutput();
    }

    public function getLog($object = 'master') {
        if ($this->getNbCommits() == 0) {
            return null;
        }

        $output = $this->sfProcess('git log ' . $object);

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

    public function getNbCommits($commit = 'master') {
        return $this->sfProcess('git rev-list --count ' . $commit);
    }

    public function getFile($file, $commit = 'master') {
        return $this->sfProcess('git show ' . $commit . ':' . $file);
    }

    public function getFilesOrFolder($commit = 'master', $fileOrFolder = '') {
        $files = $this->sfProcess('git ls-tree --full-name ' . $commit . ':' . $fileOrFolder);
        $files = explode("\n", $files);
        array_pop($files);

        foreach ($files as $key => $value) $files[$key] = preg_split("/[\s,]+/", $value);

        return $files;
    }

}
