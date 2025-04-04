<?php
class BackupJob {
    protected $jobName;
    private $source;
    private $rsyncBin;
    private $options;
    private $jsonResponsePath;
    private $force = false;
    private $dryRun = false;
    private $destination;
    private $deleteThreshold = 100;
    private $logDirectory;
    private $rsyncLogFile;
    private $rsyncDryRunLogFile;

    public function __construct(
        string $jobName,
        string $rsyncBin,
        string $source,
        string $destination,
        array $options,
        int $deleteThreshold = 0
    ) {
        $this->jobName = $jobName;
        $this->rsyncBin = $rsyncBin;
        $this->source = $source;
        $this->destination = $destination;
        $this->options = $options;
        $this->deleteThreshold = $deleteThreshold;
    }
    public function setDeleteThreshold(int $int) : self
    {
        $this->deleteThreshold = $int;

        return $this;
    }
    public function getDeleteThreshold() : int
    {
        return $this->deleteThreshold;
    }
    public function setDryRun(bool $bool) : self
    {
        $this->dryRun = $bool;

        return $this;
    }
    public function setForce(bool $bool) : self
    {
        $this->force = $bool;

        return $this;
    }
    private function getRsyncLogFile() : string
    {
        return $this->rsyncLogFile;
    }
    private function getRsyncDryRunLogFile() : string
    {
        return $this->rsyncDryRunLogFile;
    }
    public function getJsonResponsePath() : string
    {
        return $this->jsonResponsePath;
    }
    public function getJobName() : string
    {
        return $this->jobName;
    }
    public function getLogDirectory() :? string
    {
        return $this->logDirectory;
    }
    public function setLogDirectory(string $logDir) : self
    {
        $this->logDirectory = $logDir;

        $logName = preg_replace( '/\W/', '_', $this->jobName);
        $this->rsyncLogFile = sprintf(
            '%s/%s-%s.rsync.log',
            $this->getLogDirectory(),
            date('c'),
            $logName
        );
        $this->rsyncDryRunLogFile = sprintf(
            '%s/%s-%s.rsync.dry_run.log',
            $this->getLogDirectory(),
            date('c'),
            $logName
        );
        $this->jsonResponsePath = sprintf(
            '%s/%s-%s.json',
            $this->getLogDirectory(),
            date('c'),
            $logName
        );

        return $this;
    }
    public function execute() : array
    {
        $startDate = date('c');
        $startTime = time();

        $this->createLogFiles();

        $deletes =
        $errors =
        $messages =
        $receives =
        $sends = [ ];

        //Dry run first
        $resultsArr = $this->executeCommand(
            $this->buildCommand(true)
        );
        file_put_contents($this->getRsyncDryRunLogFile(), implode("\n", $resultsArr));
        foreach (array_filter($resultsArr) as $result) {
            switch ($result) {
                case (strpos($result, 'del. ') === 0):
                    $deletes[] = $result;
                    break;
                case (strpos($result, 'recv ') === 0):
                    $receives[] = $result;
                    break;
                case (strpos($result, 'send ') === 0):
                    $sends[] = $result;
                    break;
                case (strpos($result, 'rsync error') === 0):
                    $errors[] = $result;
                    break;
                default:
                    $messages[] = $result;
            }
        }

        $countDeletes = count($deletes);
        try {
            if (
                $this->deleteThreshold !== 0 &&
                $countDeletes > $this->deleteThreshold &&
                $this->force === false
            ) {
                if ($deleteOption = array_search('--delete', $this->options, true)) {
                    unset($this->options[$deleteOption]);
                }

                throw new BackupJobException(sprintf(
                    'Skipping delete for %s files. More than %s deletes requires a manual force.',
                    $countDeletes,
                    $this->deleteThreshold
                ));
            }
        } catch(BackupJobException $e) {
            $errors[] = $e->getMessage();
            //Send alert email.
        }

        $command = $this->buildCommand($this->dryRun);
        $output = $this->executeCommand($command);
        $response = implode("\n", $output);
        file_put_contents($this->getRsyncLogFile(), $response);

        $endDate = date('c');
        $endTime = time();

        $result = [
            'jobName' => $this->getJobName(),
            'command' => $command,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'duration' => ($endTime - $startTime),
            'hasError' => !empty($errors),
            'countErrors' => count($errors),
            'errors' => $errors,
            'countMessages' => count($messages),
            'messages' => $messages,
            'countSends' => count($sends),
            'sends' => $sends,
            'countDeletes' => $countDeletes,
            'deletes' => $deletes,
            'countReceives' => count($receives),
            'receives' => $receives,
        ];

        return $result;
    }

    public function buildCommand(bool $dryRun) : string
    {
        $options = $this->options;
        if ($dryRun) {
            $options[] = '--dry-run';
        }

        return sprintf('sudo %s %s %s %s',
            $this->rsyncBin,
            implode(' ', array_unique($options)),
            $this->source,
            $this->destination
        );
    }
    private function createLogFiles() : void
    {
        if (
            !is_dir($this->getLogDirectory()) &&
            !mkdir($this->getLogDirectory(), 0777, true)
        ) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $this->getLogDirectory()));
        }

        $logFiles = [
            $this->getRsyncLogFile(),
            $this->getRsyncDryRunLogFile(),
            $this->getJsonResponsePath(),
        ];
        foreach($logFiles as $logFile) {
            $directory = dirname($logFile);
            if (
                !touch($logFile) &&
                !mkdir($directory, 0777, true) &&
                !is_dir($directory)
            ) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $directory));
            }
        }
    }
    private function executeCommand(string $command) : array
    {
        exec(
            sprintf(
                '%s 2>&1',
                $command
            ), $output
        );

        return $output;
    }
}

class BackupJobException extends Exception{}
