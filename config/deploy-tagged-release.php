<?php

return [
    'repo'  => env('DEPLOY_REPO', 'https://github.com/your/repo.git'),
    'path'  => env('DEPLOY_PATH', base_path()),
    'db'    => env('DEPLOY_DB', 'my_database'),
    'token' => env('DEPLOY_GIT_TOKEN', null),
];
