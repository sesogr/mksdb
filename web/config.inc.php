<?php declare(strict_types=1);
require_once __DIR__ . '/lib/config-functions.inc.php';
return [
    'driver' => 'mysql',
    'address' => '127.0.0.1',
    'port' => '3306',
    'username' => 'schlager',
    'password' => 'zorofzoftumev',
    'database' => 'schlager',
    'middlewares' => 'authorization,cors,customization',
    'authorization.tableHandler' => 'preventMutationOperations',
    'customization.beforeHandler' => 'handleCustomRequest',
    'customization.afterHandler' => 'handleCustomResponse',
];
