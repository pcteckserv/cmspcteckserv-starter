<?php

return [
    'user_model' => env('CMS_CORE_USER_MODEL'),
    'super_admin_role' => env('CMS_CORE_SUPER_ADMIN_ROLE', 'core.super_admin'),
    'user_states' => ['active', 'inactive'],
    'users_per_page' => env('CMS_CORE_USERS_PER_PAGE', 15),

    'auth' => [
        'logout_redirect' => env('CMS_CORE_LOGOUT_REDIRECT', '/'),
    ],

    'admin_user' => [
        'name' => env('ADMIN_USER_NAME', 'Administrador'),
        'email' => env('ADMIN_USER_EMAIL'),
        'password' => env('ADMIN_USER_PASSWORD'),
    ],

    'site_options' => [
        'site_title' => env('CMS_SITE_TITLE', 'CMS PCTECK'),
        'site_description' => env('CMS_SITE_DESCRIPTION', 'Site institucional gerido pelo CMS PCTECK.'),
        'site_icon_url' => env('CMS_SITE_ICON_URL', '/vendor/cms-core/images/favicon.png'),
        'site_url' => env('CMS_SITE_URL', env('APP_URL', 'https://cliente.exemplo.pt')),
        'admin_email' => env('CMS_ADMIN_EMAIL', 'admin@exemplo.pt'),
        'locale' => env('CMS_SITE_LOCALE', 'pt_PT'),
        'smtp_enabled' => env('CMS_SMTP_ENABLED', false),
        'smtp_host' => env('MAIL_HOST', '127.0.0.1'),
        'smtp_port' => env('MAIL_PORT', 2525),
        'smtp_username' => env('MAIL_USERNAME'),
        'smtp_password' => env('MAIL_PASSWORD'),
        'smtp_encryption' => env('CMS_SMTP_ENCRYPTION', env('MAIL_ENCRYPTION')),
        'smtp_from_address' => env('MAIL_FROM_ADDRESS', env('CMS_ADMIN_EMAIL', 'admin@exemplo.pt')),
        'smtp_from_name' => env('MAIL_FROM_NAME', env('APP_NAME', 'CMS PCTECK')),
    ],

    'updates' => [
        'enabled' => env('CMS_UPDATER_ENABLED', true),
        'channel' => env('CMS_UPDATE_CHANNEL', 'stable'),
        'packages' => [
            'pcteckserv/cms-core',
        ],
        'repositories' => [
            'pcteckserv/cms-core' => env('CMS_CORE_REPOSITORY_URL', 'https://github.com/pcteckserv/cmspcteckserv-core.git'),
        ],
        'github_token' => env('CMS_GITHUB_TOKEN'),
    ],
];
