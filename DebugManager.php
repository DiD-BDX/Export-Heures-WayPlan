<?php
class DebugManager {
    private static $instance = null;
    private $debug_messages = [];

    private function __construct() {}

    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new DebugManager();
        }
        return self::$instance;
    }

    public function addMessage($message) {
        $this->debug_messages[] = $message;
    }

    public function getMessages() {
        return $this->debug_messages;
    }

    public function clearMessages() {
        $this->debug_messages = [];
    }
}