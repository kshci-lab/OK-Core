<?php
declare(strict_types=1);

final class OkCoreKnowledgeSource
{
    /** @var callable(): string */
    private $responseLoader;

    public function __construct(callable $responseLoader)
    {
        $this->responseLoader = $responseLoader;
    }

    /** Authentication is intentionally outside this class. */
    public function fetchSnapshot(): array
    {
        $raw = ($this->responseLoader)();
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (($data['status'] ?? null) !== 'ok' || !isset($data['group_id'], $data['nodes']) || !is_array($data['nodes'])) {
            throw new RuntimeException('Invalid OK-Core organizational knowledge response');
        }
        return KnowledgeSnapshot::fromApiResponse($data);
    }
}

final class KnowledgeSnapshot
{
    public function __construct(
        public readonly int $groupId,
        public readonly array $nodes,
        public readonly string $sourceHash
    ) {}

    public static function fromApiResponse(array $response): self
    {
        $nodes = [];
        foreach ($response['nodes'] as $node) {
            self::flatten($node, null, $nodes);
        }
        return new self(
            (int)$response['group_id'],
            $nodes,
            hash('sha256', json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))
        );
    }

    private static function flatten(array $node, ?int $parentId, array &$out): void
    {
        $id = (int)($node['node_id'] ?? 0);
        if ($id <= 0) throw new RuntimeException('Invalid organizational knowledge node_id');
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        $fragments = [];
        foreach (is_array($node['fragments'] ?? null) ? $node['fragments'] : [] as $fragment) {
            if (!isset($fragment['source_type'], $fragment['source_id'])) continue;
            $fragments[] = [
                'source_type' => (string)$fragment['source_type'],
                'source_id' => (int)$fragment['source_id'],
                'content' => $fragment['content'] ?? null,
                'selected_contents' => $fragment['selected_contents'] ?? null,
                'stage1' => $fragment['stage1'] ?? null,
                'stage2' => $fragment['stage2'] ?? null,
                'stage3' => $fragment['stage3'] ?? null,
                'deleted' => $fragment['deleted'] ?? null,
            ];
        }
        $out[$id] = [
            'node_id' => $id,
            'parent_id' => array_key_exists('parent_id', $node) ? $node['parent_id'] : $parentId,
            'title' => $node['title'] ?? null,
            'comment' => $node['content'] ?? $node['comment'] ?? null,
            'when' => $node['when'] ?? null,
            'what' => $node['what'] ?? null,
            'why' => $node['why'] ?? null,
            'organizational_basis' => $node['organizational_basis'] ?? null,
            'category' => $node['category'] ?? null,
            'created_at' => $node['created_at'] ?? null,
            'updated_at' => $node['updated_at'] ?? null,
            'fragments' => $fragments,
            'unresolved_fragment_links' => is_array($node['unresolved_fragment_links'] ?? null) ? $node['unresolved_fragment_links'] : [],
            'is_category_candidate' => self::isCategoryCandidate($node),
        ];
        foreach ($children as $child) self::flatten($child, $id, $out);
    }

    private static function isCategoryCandidate(array $node): bool
    {
        return (($node['category'] ?? null) === 'category') || (($node['parent_id'] ?? null) === null && empty($node['fragments']));
    }
}
