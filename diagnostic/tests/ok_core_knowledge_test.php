<?php
declare(strict_types=1);

require __DIR__ . '/../src/OkCoreKnowledgeSource.php';
require __DIR__ . '/../src/PreprocessingDecision.php';

$source = new OkCoreKnowledgeSource(static fn(): string => file_get_contents(__DIR__ . '/../sample_response.json'));
$snapshot = $source->fetchSnapshot();

if ($snapshot->groupId !== 100) throw new RuntimeException('group_id');
if (count($snapshot->nodes) !== 2) throw new RuntimeException('flattened node count');
if (!$snapshot->nodes[413]['is_category_candidate']) throw new RuntimeException('category classification');
if ($snapshot->nodes[417]['is_category_candidate']) throw new RuntimeException('knowledge classification');
if ($snapshot->nodes[417]['when'] === null || $snapshot->nodes[417]['what'] === null || $snapshot->nodes[417]['why'] === null) throw new RuntimeException('structured fields');
$refs = array_map(static fn(array $f): string => $f['source_type'] . ':' . $f['source_id'], $snapshot->nodes[417]['fragments']);
if ($refs !== ['experience:11161', 'experience:11165']) throw new RuntimeException('fragment refs');
if (!PreprocessingDecision::shouldRegenerate(null, $snapshot)) throw new RuntimeException('initial preprocessing');
if (PreprocessingDecision::shouldRegenerate($snapshot->sourceHash, $snapshot)) throw new RuntimeException('unchanged preprocessing');
echo "PASS: OK-Core snapshot normalization\n";
