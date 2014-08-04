<?php

namespace Idephix\SSH;

use Symfony\Component\Process\Process;

class CLISshProxy extends BaseProxy
{
    protected $executable = 'ssh';
    protected $host;
    protected $port = 22;
    protected $user = '';
    protected $password = '';
    protected $privateKeyFile = null;
    protected $timeout = 600;

    private function canConnect()
    {
        if ($this->exec('echo "connected"') &&
            false !== strpos($this->lastOutput, 'connected')) {
            return true;
        }

        return false;
    }

    private function assertConnected()
    {
        if (empty($this->host)) {
            throw new \Exception("You first need to connect");
        }
    }

    public function setExecutable($executable)
    {
        $this->executable = $executable;
    }

    public function connect($host, $port = 22)
    {
        $this->host = $host;
        $this->port = $port;

        return true;
    }

    public function authByPassword($user, $pwd)
    {
        throw new \Exception("Not implemented");
    }

    public function authByAgent($user)
    {
        $this->assertConnected();
        $this->user = $user;

        return $this->canConnect();
    }

    public function authByPublicKey($user, $public_key_file, $privateKeyFile, $pwd)
    {
        $this->assertConnected();
        $this->user = $user;
        $this->privateKeyFile = $privateKeyFile;

        return $this->canConnect();
    }

    public function exec($cmd)
    {
        $preparedCmd = $this->prepareCommand($cmd);

        return $this->doExec($preparedCmd);
    }

    private function doExec($preparedCmd)
    {
        $process = new Process($preparedCmd, null, null, null, $this->timeout);
        $process->run();
        $this->lastOutput = $process->getOutput();
        $this->lastError = $process->getErrorOutput();
        $this->exitCode = $process->getExitCode();

        return $process->isSuccessful();
    }

    /**
     * @inheritdoc
     */
    public function put($localPath, $remotePath)
    {
        $user = $this->user ? $this->user.'@' : '';
        $keyFile = $this->privateKeyFile ? '-i '.$this->privateKeyFile : '';

        $preparedCmd = sprintf(
            "scp -pP %s %s %s %s%s:%s",
            $this->port,
            $keyFile,
            escapeshellarg($localPath),
            $user,
            $this->host,
            strtr(escapeshellarg($remotePath), array(' ' => '\\ '))
        );

        return $this->doExec($preparedCmd);
    }

    /**
     * @inheritdoc
     */
    public function get($remotePath, $localPath)
    {
        $user = $this->user ? $this->user.'@' : '';
        $keyFile = $this->privateKeyFile ? '-i '.$this->privateKeyFile : '';

        $preparedCmd = sprintf(
            "scp -pP %s %s %s%s:%s %s",
            $this->port,
            $keyFile,
            $user,
            $this->host,
            strtr(escapeshellarg($remotePath), array(' ' => '\\ ')),
            escapeshellarg($localPath)
        );

        return $this->doExec($preparedCmd);
    }

    private function prepareCommand($cmd)
    {
        $user = $this->user ? '-l '.$this->user : '';
        $keyFile = $this->privateKeyFile ? '-i '.$this->privateKeyFile : '';

        return sprintf(
            "%s -p %s %s %s %s %s",
            $this->executable,
            $this->port,
            $keyFile,
            $user,
            $this->host,
            escapeshellarg($cmd)
        );
    }
}