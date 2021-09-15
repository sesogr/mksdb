<?php

require_once __DIR__ . "/lib/index_db.inc.php";

$Loggers->addLogger(new class implements LoggerSubscriber{
    public function log(string $type, string $message){
        echo "$type : $message \n";
    }
});

function run(): void {
    global $Loggers;

    $Loggers->log('info', 're-creating index');

    $conn = createConnection();

    clearIndex($conn);

    $tablesToIndex = listTables($conn);

    $indexData = [];
    foreach($tablesToIndex as $table) {
        $Loggers->log('info', "indexing table $table");

        $tableIndexData = indexTable($table, $conn);
        if($tableIndexData !== false)
            mergeMap($indexData, $tableIndexData);
    }

    $Loggers->log('info', 'writing index');
    writeIndex($indexData, $conn);

    $Loggers->log('info', 'finished');
}
run();