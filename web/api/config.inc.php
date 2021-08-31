<?php
require_once __DIR__ . '/func.inc.php';
return [
    'driver' => 'mysql',
    'address' => '127.0.0.1',
    'port' => '3306',
    'username' => 'schlager',
    'password' => 'zorofzoftumev',
    'database' => 'schlager',
    'middlewares' => 'authorization,cors,customization',
    'authorization.tableHandler' => 'preventMutationOperations',
    'customization.afterHandler' => 'interceptSearchRequest',
];
