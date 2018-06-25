<?php

Git::$repositories['emergence-statickit'] = [
    'remote' => 'https://github.com/EmergencePlatform/statickit.git',
    'originBranch' => 'master',
    'workingBranch' => 'master',
    'trees' => [
        'event-handlers/Emergence/GitHub/push/update-content.php',
        'php-classes/Emergence/StaticKit',
        'php-config/Emergence/StaticKit',
        'php-config/Git.config.d/emergence-github.php',
        'php-config/Git.config.d/emergence-slack.php',
        'php-config/Git.config.d/emergence-statickit.php',
        'php-config/Git.config.d/content.php',
        'site-root/_notfound.php',
        'site-root/home.php'
    ]
];