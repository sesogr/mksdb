<?php declare(strict_types=1);
require_once __DIR__ . '/lib/functions.inc.php';
return [
    'driver' => 'mysql',
    'address' => 'mksdb-mariadb',
    'port' => '3306',
    'username' => 'schlager',
    'password' => 'zorofzoftumev',
    'database' => 'schlager',
    'middlewares' => 'authorization,cors,customization',
    'authorization.tableHandler' => 'preventMutationOperations',
    'customization.beforeHandler' => 'handleCustomRequest',
    'customization.afterHandler' => 'handleCustomResponse',
];
