<?php declare(strict_types=1);
require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/lib/functions.inc.php';
require_once __DIR__ . '/lib/types.inc.php';
handleRequest(
    $_GET,
    $_POST,
    $_SERVER,
    new PDO(
        sprintf("mysql:host=%s;port=%d;dbname=%s;charset=%s", DBHOST, DBPORT, DBSCHEMA, DBCHARSET),
        DBUSER,
        DBPASSWORD
    )
);
