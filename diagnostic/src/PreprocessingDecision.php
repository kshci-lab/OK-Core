<?php
declare(strict_types=1);

final class PreprocessingDecision
{
    public static function shouldRegenerate(?string $previousHash, KnowledgeSnapshot $current): bool
    {
        return $previousHash === null || !hash_equals($previousHash, $current->sourceHash);
    }
}
