<?php

require_once __DIR__ . "/lib/index_db.inc.php";

class DbIndexerExecutor{

    private $logger;
    private $indexer;

    function init($conf){
        $this->logger = new SubscribableLogger();
        $this->logger->addLogger(new class implements LoggerSubscriber{
            public function log(string $type, string $message)
            {
                echo "$type : $message \n";
            }
        });

        $dbConn = new PDO(
            sprintf(
                "mysql:host=%s;port=%d;dbname=%s;charset=%s",
                $conf['address'],
                $conf['port'],
                $conf['database'],
                'utf8mb4'
            ),
            $conf['username'],
            $conf['password']
        );
        $this->indexer = new DbIndexer($dbConn, $this->logger);
    }

    function execute(){
        $this->logger->log('info', 're-creating index');

        $this->indexer->clearIndex();

        $tablesToIndex = $this->indexer->listTables();

        $indexData = [];
        foreach($tablesToIndex as $table) {
            $this->logger->log('info', "indexing table $table");

            $tableIndexData = $this->indexer->indexTable($table);
            if($tableIndexData !== false)
                $this->indexer->mapDeepMerge($indexData, $tableIndexData);
        }

        $this->logger->log('info', 'writing index');
        $this->indexer->writeIndex($indexData);

        $this->logger->log('info', 'finished');
    }
}

function run(): void {
    $config = include __DIR__ . '/../web/config.inc.php';

    $exec = new DbIndexerExecutor();
    $exec->init($config);
    $exec->execute();
}
run();