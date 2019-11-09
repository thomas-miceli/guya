<?php

namespace App\Util;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class GitSf {

    public const GIT_FOLDERS = '/home/thomas/gitlist/';

    private $pathFolder;

    public function __construct(string $user, string $folderName)
    {
        $this->pathFolder = self::GIT_FOLDERS . $user . '/' . $folderName . '.git';
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

    public function createUser() {
    }

    public function init() {
        $this->sfProcess('mkdir ' . $this->pathFolder, self::GIT_FOLDERS);
        $this->sfProcess('git --bare init');
    }

    public function getNbCommits($commit = '') {
        if (empty($commit)) {
            $commit = '--all';
        }
        return $this->sfProcess('git rev-list --count ' . $commit);
    }

    public function getLog() {
        if ($this->getNbCommits() == 0) {
            return null;
        }

        $output = $this->sfProcess('git log');

        $history = array();
        $commit = array();
        foreach(explode("\n", $output) as $line){
            if(strpos($line, 'commit')===0){
                if(!empty($commit)){
                    array_push($history, $commit);
                    unset($commit);
                }
                $commit['hash']   = substr($line, strlen('commit '));
            }
            else if(strpos($line, 'Author')===0){
                $commit['author'] = substr($line, strlen('Author: '));
            }
            else if(strpos($line, 'Date')===0){
                $commit['date']   = substr($line, strlen('Date: '));
            }
            else{
                if(isset($commit['message']))
                    $commit['message'] .= $line;
                else
                    $commit['message'] = $line;
            }
        }
        if(!empty($commit)) {
            array_push($history, $commit);
        }
        return $history;
    }

    public function getFile($file, $commit = 'master') {
        return $this->sfProcess('git show ' . $commit . ':' . $file);
    }

    public function getFiles($commit = 'master') {
        $files = $this->sfProcess('git ls-tree --name-only -r ' . $commit);
        $files = explode("\n", $files);
        array_pop($files);
        return $files;
    }

}
