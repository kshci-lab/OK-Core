# discussion KFの共有先をshared_nodesへ統一

共同化マップ・連結化のKF一覧・KF詳細・組織知ツリーの参照照合・文字起こしの閲覧権限は、すべて`shared_nodes`から共有先を判定します。`externalized_contents.group_id`は既存の受信データとして保持し、表示・権限判定には使いません。

## 共有行の形式

| カラム | experience KF | externalized_contentsのKF |
| --- | --- | --- |
| `fragment_source_type` | `experience` | `discussion` |
| `knowledge_fragment_id` | `experience_knowledges.experience_knowledge_id` | `externalized_contents.externalized_contents_id` |
| `knowledge_group_id` | 共有先グループID | 共有先グループID |
| `deleted` | 0: 有効、1: 共有解除 | 0: 有効、1: 共有解除 |

一意キーは`(fragment_source_type, knowledge_fragment_id, knowledge_group_id)`です。同じ数値IDの別種別KFや、同じKFの複数グループ共有を区別できます。種別の既定値は既存のForest受信処理との互換のため`experience`です。phpMyAdminでexternalized_contentsの共有行を作成する場合には、**fragment_source_typeをdiscussionと指定してください**。

新規受信・再送の`php/import_kf_stub.php`は、送信payloadに有効なgroup_idがある場合、discussionの共有行を登録または再有効化します。重複した共有行は作成しません。送信先の指定が空なら新しい共有先を推測せず、既存の共有も解除しません。KFの保存／再送更新と共有登録を同じトランザクションで行います。

## 必要なSQL

AWSのOK-Coreに`sql/2026-10-15_shared_nodes_discussion.sql`を適用してください。先に`shared_nodes.knowledge_fragment_id`への改名が必要です。改名用SQLは`sql/2026-10-15_rename_shared_nodes_knowledge_fragment_id.sql`です。

1. `fragment_source_type`を追加します。
2. 種別を含む一意キーへ変更します。
3. 既存の共有行はexperienceとして維持します。ただし、参照IDがexternalized_contentsにだけ実在する共有行はdiscussionと特定して引き継ぎます。両テーブルに存在するIDの旧共有行はexperienceとして維持します。
4. externalized_contentsに記録済みの有効なグループをdiscussionの共有行へコピーします。group_id=0からグループを推測しません。すでに共有解除された行は、SQLの再実行で復活させません。

列追加時点より後に処理が中断した場合、共有行の種別分類が完了しているかを確認してから再実行してください。旧共有行の種別を分類するUPDATEは、列を初めて追加した実行でのみ行います。

コードとSQLは同じメンテナンス作業で反映してください。旧コードでは種別を判別できないため、新しい共有行を誤ってexperience KFとして解釈する可能性があります。Forest-platform自身のDBはexperience共有だけを保持するので、今回の種別列追加SQLはOK-Coreにだけ適用します。ForestからOK-CoreへのAPI形式は従来のままです。

ローカルMAMPのok_coreには適用済みです。改名前から存在する32行のID・共有先・削除状態・日時は維持し、9件の既存共有行をdiscussionと特定しました。加えて旧group_idから9件をコピーしました。KF ID 9の共有先はshared_nodesのグループ100となり、externalized_contents.group_id=0でも参照できます。バックアップは`backups/shared_nodes_discussion_20261015_054304/ok_core_shared_nodes.sql`です。
