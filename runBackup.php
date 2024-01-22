<?php
require_once('BackupJob.class.php');

$options = getopt(null, [
    'force::',
    'dryRun::',
    'printCommand::',
    'jobName::',
    'configDir:',
]);
$force = isset($options['force']);
$dryRun = isset($options['dryRun']);
$printCommand = isset($options['printCommand']);
$jobName = $options['jobName'] ?? null;
$logDir = BackupJob::$logDirectory;
$configDir = $options['configDir'] ?? null;

$exitCode = 0;
if (
    empty($configDir) || !is_dir($configDir)
) {
    echo("[ERROR] Configuration directory invalid. Usage: --configDir=\"./conf.d\".\n");
    $exitCode = 1;
} else {
    echo(sprintf(
        "[NOTICE] Config directory set to [%s]\n",
        realpath($configDir)
    ));
}

if (
    !is_writable($logDir)
) {
    echo("[ERROR] Log directory is not writable.\n");
    $exitCode = 1;
} else {
    echo(sprintf(
        "[NOTICE] Log directory set to [%s]\n",
        realpath($logDir)
    ));
}

if ($exitCode) {
    exit($exitCode);
}

$defaultOptions = [
    '--archive',
    '--compress',
    '--update',
    '--out-format=\'%o %n%L\''
];

$backupJobs = [ ];
foreach (glob(sprintf("%s/*.php", $configDir)) as $filename)
{
    include $filename;
}

echo(sprintf(
    "[NOTICE] %s jobs available.\n",
    count($backupJobs)
));

$arr = [ ];
shuffle($backupJobs);
foreach($backupJobs as $key => $backupJob) {
    $backupJob->setDryRun($dryRun);

    if ($jobName && $backupJob->getJobName() !== $jobName) {
        continue;
    }

    if ($printCommand) {
        $output = [$backupJob->buildCommand($dryRun)];
    } else {
        $output = $backupJob
            ->setForce($force)
            ->execute();
    }

    $arr[$key] = $output;
    unset(
        $arr[$key]['sends'],
        $arr[$key]['receives']
    );

    print_r($arr[$key]);

    file_put_contents(
        $backupJob->getJsonResponsePath(),
        json_encode($output, JSON_PRETTY_PRINT)
    );
}

$jsonSummaryPath = sprintf(
    '%s/%s-%s.summary.json',
    BackupJob::$logDirectory,
    date('Y-m-d.His')
);

file_put_contents(
    $jsonSummaryPath,
    json_encode($arr, JSON_PRETTY_PRINT)."\n",
    FILE_APPEND
);

exit('Finished: '.date('c'));
