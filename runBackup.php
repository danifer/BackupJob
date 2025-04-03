<?php
require_once('BackupJob.class.php');

$options = getopt('h', [
    'help::',
    'force::',
    'dryRun::',
    'printCommand::',
    'jobName::',
    'configDir:',
    'logDir:',
]);
$configDir = $options['configDir'] ?? null;
$jobName = $options['jobName'] ?? null;
$logDir = $options['logDir'] ?? null;
$dryRun = isset($options['dryRun']);
$force = isset($options['force']);
$printCommand = isset($options['printCommand']);

$exitCode = 0;

if (isset($options['h']) || isset($options['help'])) {
    displayHelp();
    exit(0);
}

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
    empty($logDir) || !is_dir($logDir) || !is_writable($logDir)
) {
    echo("[ERROR] Log directory invalid. Usage: --logDir=\"./logs\".\n");
    $exitCode = 1;
} else {
    echo(sprintf(
        "[NOTICE] Log directory set to [%s]\n",
        realpath($logDir)
    ));
}

if ($dryRun) {
    echo ("[NOTICE] --dryRun option set [%s]\n");
}

if (!empty($jobName)) {
    echo(sprintf(
        "[NOTICE] Processing jobName [%s]\n",
        $jobName
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
foreach($backupJobs as $key => $backupJob) {
    $backupJob
        ->setDryRun($dryRun)
        ->setLogDirectory($logDir);

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
    '%s/%s.summary.json',
    $logDir,
    date('c')
);

file_put_contents(
    $jsonSummaryPath,
    json_encode($arr, JSON_PRETTY_PRINT)."\n",
    FILE_APPEND
);

exit('Finished: '.date('c'));

function displayHelp() {
    echo "Usage: php " . basename(__FILE__) . " [OPTIONS]\n";
    echo "\n";
    echo "Options:\n";
    echo "  -h, --help           Display this help message and exit\n";
    echo "  --force              Force execution and allow overrides\n";
    echo "  --dryRun             Simulate execution without making changes\n";
    echo "  --printCommand       Print commands instead of executing them\n";
    echo "  --jobName=NAME       Specify a single job to execute\n";
    echo "  --configDir=DIR      Set configuration directory (required)\n";
    echo "  --logDir=DIR         Set log directory (required)\n";
    echo "\n";
    echo "Example:\n";
    echo "  php " . basename(__FILE__) . " --configDir=/etc/myapp --logDir=/var/log/myapp\n";
}
