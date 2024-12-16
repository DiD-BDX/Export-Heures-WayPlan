<?php
class DebugManager {
    // Singleton instance
    private static $instance = null;
    
    private $debug_messages = [];

    // Private constructor to prevent direct instantiation
    private function __construct() {
        // This constructor is intentionally left empty to prevent direct instantiation.
    }

    // Method to get the singleton instance
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