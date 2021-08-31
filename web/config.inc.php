<?php declare(strict_types=1);
require_once __DIR__ . '/func.inc.php';

const DBHOST = '127.0.0.1';
const DBPORT = 3306;
const DBUSER = 'schlager';
const DBPASSWORD = 'zorofzoftumev';
const DBSCHEMA = 'schlager';
const DBCHARSET = 'utf8mb4';

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
