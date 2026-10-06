# OK-Core / forest-platform 分離状況（2026-10-06）

## 対象と確認方法

この文書はローカルの `forest-platform` と `OK-Core` の現行コード、および MySQL の `forest_platform` / `ok_core` DBを読み取り専用で確認した結果である。DBへの更新、送信、組織知の試験登録は行っていない。件数は実測値であり、READMEに残る旧方式の説明より現行コードとDBを優先する。

## 現在の経路

```text
forest-platform の「学びを出力」
  -> experience_knowledges、形成文脈、shared_nodes、kf_sync_outbox を同一トランザクションで保存
  -> OK-Core /api/v1/source-systems/{system_code}/knowledge-fragments/{external_kf_id} に PUT
  -> 成功時に Outbox を SENT、失敗時に FAILED
  -> OK-Core の既存 experience_knowledges / shared_nodes を画面で表示
  -> knowledge_explorer / knowledge_explorer_fragment_links に組織知を登録
```

送信側の新規・更新・削除と再送処理は `forest-platform/php/kf_sync_service.php` にある。共有先グループ取得は `php/ok_core_bridge.php` から OK-Core API を呼ぶ。ForestからOK-Core DBへの旧直接接続方式は、現行の `ok_core_bridge.php` の経路では使われていない。

## 実測状況

| 項目 | 現状 |
| --- | --- |
| Forestの有効な経験KF | 22件（全23件） |
| OK-Coreの有効な経験KF | 12件 |
| Forestの有効KFでOK-Coreに同じIDがないもの | 14件 |
| 両DBに同じIDで存在する有効KF | 8件。そのうち3件は概要またはstageが異なる |
| グループ100でForestから共有された有効KF | 11件。OK-Coreに同じIDがあるのは3件 |
| Forestの形成文脈 / Outbox | 文脈パッケージ1件、項目1件、Outbox 1件 |
| Outboxの状態 | ID 1、KF 11162、UPSERT、FAILED、1回試行。`API_TOKEN_NOT_CONFIGURED`、再送予定なし |
| OK-Coreの組織知 | 有効ノード24件、KFリンク26件。リンク先ノード・KFの欠損は検出されなかった |
| OK-Coreの既存グループ | 100、101。Forestの共有先には1、100がある |

ForestのAPI接続設定ファイル `php/ok_core_api_local.php` は存在しない。OK-Coreの `api/v1` ディレクトリには受信処理が存在せず、`GET /OK-Core/api/v1/health` は404だった。従って現時点で「Forestで作成したKFが新API経由でOK-Coreに届く」とは言えない。OK-Coreの既存KFは旧連携・既存データとして表示可能な構造だが、新APIの疎通試験は未達である。

## できていることと未確認のこと

| 工程 | 評価 | 根拠・制限 |
| --- | --- | --- |
| ForestでKF・形成文脈・Outboxを保存 | DB実績あり | KF 11162に対応する文脈とFAILED Outboxがある |
| 共有先グループをOK-Coreから取得 | 送信側コードあり、動作未確認 | OK-Core側のグループAPIが未実装 |
| KFをOK-CoreへAPI送信 | 送信側コードあり、未成立 | トークン未設定、受信API不在 |
| OK-Coreで既存KFをグループ別に表示 | コードと既存データあり、画面操作未確認 | `get_knowledge_fragments_by_group.php` は `shared_nodes` と `experience_knowledges` を読む |
| 既存KFから組織知を登録・表示 | コードと既存データあり、今回の登録操作は未試験 | `insert_knowledge_node.php`、`get_knowledge_tree.php`、リンク表が存在 |
| 外部KFの更新・削除・再送をOK-Coreへ反映 | 送信側コードのみ | 受信APIと版管理・外部IDの保存先が未整備 |

`php/import_kf_stub.php` は `externalized_contents` に議論系KFを受ける別経路であり、Forestの `experience_knowledges` 用APIの代わりにはならない。READMEの「直接DBブリッジで動作確認済み」は過去の状態を示す。

## 次の実装順

1. **現行DB向けのOK-Core APIを実装する。** `health`、`capabilities`、ユーザー所属グループ取得、KFのPUT/DELETEを実装し、Forestが現在送る `OK_CORE_KF` v1 JSONとレスポンスを一致させる。APIトークンとSSO `sub` による操作者・共有先権限を検証する。KF本体は既存の `experience_knowledges`、共有は `shared_nodes` に保存し、既存UIで見えることを確認する。
2. **再送に耐える最小限の受信管理を決める。** 既存 `experience_knowledges` には `source_system`、外部KF ID、`source_revision` がない。他システムとのID衝突、重複送信、古い版による上書きを防ぐには、現行DBに小さな対応・版管理テーブルを追加するか、同等の保存先が必要。これは新ERへの全面切替とは分けて扱う。既存の同一IDデータは対応を確定してから紐付ける。
3. **Forestの接続設定と再送を動かす。** OK-Coreで発行するトークンをForestに設定し、グループ取得、KF 1件の作成・更新・削除、Outboxの `SENT` への遷移を確認する。失敗レコードID 1は設定修正だけでは自動再送されないため、確認後に管理操作で再送する。定期再送ジョブも設定する。
4. **組織知登録の整合性を固める。** 選択グループへの所属、参照KFの存在と共有、親ノードの所属を保存時に検証する。`knowledge_explorer` とリンク表を同一トランザクションで保存し、リンク失敗を成功扱いにしない。現状の登録処理はリンク保存失敗を無視し、要求リンク数を返す。ノードIDの `MAX+1` 手動採番も競合対策が必要。
5. **既存データを照合してから反映する。** グループ100の未反映分、同一IDだが内容が異なる3件、Forestのグループ1とOK-Coreのグループ100/101の対応を確定する。バックフィルは件数・ID・版・共有先を照合できる手順にする。その後、現行DBで「送信→一覧→組織知登録→再表示」を通しで確認する。
6. **新DB設計を適用する。** 上記の現行DBでの動作と既存データの対応を基準に、提案ER図の新テーブルへ移行する。移行前後でKF、共有、組織知、リンク、議論履歴の件数と参照整合性を比較する。

## 判定条件

同一KFの再送で重複しないこと、古いrevisionが新しい内容を戻さないこと、権限のないグループへ共有できないこと、送信失敗後の再送で `SENT` になること、OK-Coreのグループ別一覧に届いたKFが現れ、そのKFを根拠に組織知を登録して再表示できることを、現行DBで確認できれば次のDB移行へ進める。
