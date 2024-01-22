<?php
$backupJobs[] = (new BackupJob(
    'backup:job:example',
    '/usr/bin/rsync',
    '/data_source/',
    '/data_destination/',
    array_merge(
        $defaultOptions,
        [
            "--rsync-path 'sudo -u root /usr/bin/rsync'",
        ]
    )
))
    ->setDeleteThreshold(100);
