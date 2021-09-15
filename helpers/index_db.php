<?php

require_once __DIR__ . "/lib/index_db.inc.php";

function myLog(string $level, string $msg): void {
    echo "$level : $msg \n";
}

function run(): void {
    myLog('info', 're-creating index');

    $conn = createConnection();

    clearIndex($conn);

    $tablesToIndex = listTables($conn);

    $indexData = [];
    foreach($tablesToIndex as $table) {
        myLog('info', "indexing table $table");

        $tableIndexData = indexTable($table, $conn);
        if($tableIndexData !== false)
            mergeMap($indexData, $tableIndexData);
    }

    myLog('info', 'writing index');
    writeIndex($indexData, $conn);

    myLog('info', 'finished');
}
run();