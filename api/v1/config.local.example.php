<?php
// Copy to config.local.php (ignored by Git). These are examples, not real tokens.
// Each enabled producer needs a distinct token; no Apache SetEnv is required.
return [
    'source_systems' => [
        'forest-platform' => [
            'enabled' => true,
            'token' => 'replace-with-a-long-random-forest-token',
            'scopes' => ['groups:read', 'fragments:write', 'fragments:delete'],
            // Optional: without these keys, legacy php/forest_process_map_local.php is used.
            // 'context_base_url' => 'https://forest.example/software/forest-platform/api/v1',
            // 'context_token' => 'replace-with-forest-read-token',
            // 'context_timeout_seconds' => 10,
            'share_policy' => 'replace', // Preserve the existing Forest v1 behavior.
            'legacy_adopt_numeric_ids' => false, // Enable only for reconciled legacy imports.
        ],
        'discussion-kf-system' => [
            'enabled' => false, // Configure its own token before enabling.
            'token' => '',
            'scopes' => ['groups:read', 'fragments:write', 'fragments:delete'],
            'share_policy' => 'merge', // Add requested shares; retain OK-Core's other shares.
        ],
    ],
];
