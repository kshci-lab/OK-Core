<?php

// Copy to discussion_transcript_local.php (Git ignored) on the OK-Core server.
// Use a DB account with SELECT permission on hci_knowledge_db only.
// Environment variables DISCUSSION_TRANSCRIPT_DB_* override these settings.
return [
    'host' => 'localhost',
    'database' => 'hci_knowledge_db',
    'user' => 'root',
    'port' => 3306, 
    'password' => 'kslabkslab',
    // 'port' => 8889,
    // 'password' => 'root',
    'source_system' => 'discussion-kf-system',
];
