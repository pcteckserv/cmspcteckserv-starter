<?php

return [
    'output_disk' => 'local',
    'output_directory' => 'deploy',
    'package_name' => 'cmspcteckserv-deploy.zip',
    'application_archive_name' => 'application.zip',
    'installer_name' => 'installer.php',
    'composer_phar' => env('DEPLOY_COMPOSER_PHAR'),
    'local_package_overlays' => [
        'pcteckserv/cms-core' => '../cmspcteckserv-core',
    ],
    'build_commands' => [
        'npm run build',
    ],
    'build_fallbacks' => [
        'npm run build' => 'public/build/manifest.json',
    ],
    'build_sources' => [
        'resources/css',
        'resources/js',
        'vite.config.js',
        'package.json',
        'package-lock.json',
        'vendor/pcteckserv/cms-core/resources',
    ],
    'build_attempts' => 3,
    'build_timeout' => 300,
    'exclude' => [
        '.env',
        '.git',
        '.phpunit.result.cache',
        'bootstrap/cache',
        'node_modules',
        'public/hot',
        'public/storage',
        'storage/app/deploy',
        'storage/app/private/deploy',
        'storage/framework/cache',
        'storage/framework/sessions',
        'storage/framework/testing',
        'storage/framework/views',
        'storage/logs',
        'tests',
    ],
];
