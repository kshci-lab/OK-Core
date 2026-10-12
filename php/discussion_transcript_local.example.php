<?php

// Copy to discussion_transcript_local.php (Git ignored) on the OK-Core server.
// Use a DB account with SELECT permission on hci_knowledge_db only.
// Environment variables DISCUSSION_TRANSCRIPT_DB_* override these settings.
return [
    'host' => 'localhost',
    'port' => 3306, // MAMP: 8889
    'database' => 'hci_knowledge_db',
    'user' => 'ok_core_transcript_reader',
    'password' => 'set-the-database-password-here',
    'source_system' => 'discussion-kf-system',
];
