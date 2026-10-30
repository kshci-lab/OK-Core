<?php

declare(strict_types=1);

final class ForestProcessMapException extends RuntimeException
{
    public function __construct(string $message, public int $httpStatus = 503, public string $errorCode = 'FOREST_UNAVAILABLE')
    {
        parent::__construct($message);
    }
}

function forest_process_map_config(): array
{
    $config = ['base_url' => '', 'token' => '', 'timeout_seconds' => 10];
    $localPath = __DIR__ . '/forest_process_map_local.php';
    if (is_file($localPath)) {
        $local = require $localPath;
        if (is_array($local)) {
            $config = array_replace($config, $local);
        }
    }
    foreach ([
        'base_url' => 'FOREST_CONTEXT_API_BASE_URL',
        'token' => 'FOREST_CONTEXT_API_TOKEN',
        'timeout_seconds' => 'FOREST_CONTEXT_API_TIMEOUT_SECONDS',
    ] as $key => $environmentName) {
        $value = getenv($environmentName);
        if ($value !== false && $value !== '') {
            $config[$key] = $value;
        }
    }
    // Match the KF sender: application-owned configuration wins over stale SetEnv values.
    if (isset($local) && is_array($local)) $config = array_replace($config, $local);
    $config['base_url'] = rtrim((string)$config['base_url'], '/');
    $config['token'] = (string)$config['token'];
    $config['timeout_seconds'] = max(1, (int)$config['timeout_seconds']);
    return $config;
}

function forest_process_map_fetch(int $externalKfId, string $actingSub, int $groupId,
    string $sourceReference = '', string $sourceType = ''): array
{
    require_once __DIR__ . '/source_context_client.php';
    try {
        $context = source_context_fetch('forest-platform', (string)$externalKfId, $actingSub, $groupId,
            'forest.thinking-process-map.v1', '', $sourceReference, $sourceType);
        return $context['map'];
    } catch (SourceContextException $error) {
        $code = $error->errorCode === 'SOURCE_NOT_CONFIGURED' ? 'FOREST_NOT_CONFIGURED' : $error->errorCode;
        throw new ForestProcessMapException($error->getMessage(), $error->httpStatus, $code);
    }
}
