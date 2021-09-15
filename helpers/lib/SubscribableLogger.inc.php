<?php

class SubscribableLogger
{

    /**
     * @var LoggerSubscriber[]
     */
    private $subscibers;

    public function __construct()
    {
        $this->subscibers = [];
    }

    /**
     * add the logger to the subscriber-list (duplicates are ignored)
     * @param LoggerSubscriber $logger the new subscriber
     */
    public function addLogger(LoggerSubscriber $logger){
        if(!in_array($logger, $this->subscibers))
            $this->subscibers = [...$this->subscibers, $logger];
    }

    /**
     * removes the logger from the subscriber-list
     * @param LoggerSubscriber $logger the logger to remove
     */
    public function removeLogger(LoggerSubscriber $logger){
        array_splice($this->subscibers, array_search($logger, $this->subscibers), 1);
    }

    /**
     * notifies each logger to log the message
     * @param string $type the type of the log (e.g. warning, info, debug, ...)
     * @param string $message the log-message
     */
    public function log(string $type, string $message){
        foreach($this->subscibers as $subscriber)
            $subscriber->log($type, $message);
    }
}

interface LoggerSubscriber{
    public function log(string $type, string $message);
}