<?php
require_once __DIR__ . '/discussion_endpoint_bootstrap.php';
try {
    $group = (int)$access['group_id'];
    $sql = "SELECT 'experience' AS source_type, ek.experience_knowledge_id AS id FROM shared_nodes sn
        JOIN experience_knowledges ek ON ek.experience_knowledge_id = sn.knowledge_fragment_id
        WHERE sn.fragment_source_type = 'experience' AND sn.knowledge_group_id = $group AND sn.deleted = 0
          AND ek.deleted = 0 AND ek.discussed = 'UNDERWAY'
        UNION SELECT 'discussion', ec.externalized_contents_id FROM shared_nodes sn
        JOIN externalized_contents ec ON ec.externalized_contents_id = sn.knowledge_fragment_id
        WHERE sn.fragment_source_type = 'discussion' AND sn.knowledge_group_id = $group AND sn.deleted = 0
          AND ec.deleted = 0 AND ec.discussed = 'UNDERWAY'";
    $underway = $mysqli->query($sql)->fetch_all(MYSQLI_ASSOC);
    $pairs = [];
    foreach ($underway as $item) {
        $rows = ok_core_discussion_rows($mysqli, [$item['source_type'].':'.$item['id']], $access['group_id'], 1);
        $refs = $rows ? $rows[0]['fragment_refs'] : [];
        for ($i=0; $i<count($refs); $i++) {
            for ($j=$i+1; $j<count($refs); $j++) {
                $pair = [$refs[$i], $refs[$j]];
                sort($pair, SORT_STRING);
                $pairs[implode('|', $pair)] = ['from'=>$pair[0],'to'=>$pair[1]];
            }
        }
    }
    discussion_reply(200, ['status'=>'ok','relations'=>array_values($pairs)]);
} catch (Throwable $error) {
    error_log('Discussion relations read failed: ' . $error->getMessage());
    discussion_reply(503, ['status'=>'error','message'=>'議論対象の関連を読み込めません。']);
}
