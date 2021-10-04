<?php declare(strict_types=1);
require_once __DIR__ . '/lib/functions.inc.php';
return [
    'driver' => 'mysql',
    'address' => 'ckuc5lb9n000fp386qpeklpft',       // e. g. localhost
    'port' => '3306',                               // mysql default port: 3306
    'username' => 'ckuc5lezg000gp386q6hexfoe',
    'password' => 'ckuc5lhp9000hp3863bw5x2p5',
    'database' => 'ckuc5lkoe000ip3868obtf185',      // aka schema name
    'basePath' => 'ckuc5lndh000jp386qpffulqo/api/', // target URL
    'middlewares' => 'authorization,cors,customization',
    'authorization.tableHandler' => 'preventMutationOperations',
    'customization.beforeHandler' => 'handleCustomRequest',
    'customization.afterHandler' => 'handleCustomResponse',
];
