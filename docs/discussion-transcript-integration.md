# discussion KFから議論の文字起こしを表示する

## 操作

共同化マップのdiscussion KF、または連結化タブのdiscussion KFカードを右クリックし、**議論の文字起こしを表示**を選びます。

- KFに紐づく発言と同じセッションの発言を時系列で表示します。
- 関連する発言を色と「KFに紐づく発言」の表示で強調し、初期表示ではその発言のあるページへ移動します。
- 複数セッションに紐づくKFはセッションを切り替えられます。
- 200発言ずつページを切り替えられます。「KFに紐づく発言へ」で関連発言のあるページへ戻れます。
- 閉じるボタン・Escape・背景のクリックで閉じられます。

## 今回の構成

ブラウザー → OK-Core内の`php/get_discussion_transcript.php` → 産出元の`hci_knowledge_db`へ直接接続します。産出元のAPIやプログラムは呼び出しません。

OK-Coreのログインと有効なグループ所属を確認し、`shared_nodes`で`fragment_source_type='discussion'`としてそのグループへ共有されたKFだけを参照できます。`externalized_contents.group_id`は閲覧権限に使用しません。ブラウザーから任意の産出元KF IDや任意のセッションだけを指定して取得することはできません。

参照先KFはOK-Coreに保存された`source_system`と`source_id`で特定します。既存の`discussion-kf-system` / `knowledge_fragment_4`形式と数値IDに対応します。OK-Core側の`externalized_contents_id`を産出元KFのIDとみなす処理はありません。

さらに産出元の`kf_exports`で、成功した送信先の`ok_core_externalized_contents_id`が今回のOK-Core側KF IDと一致することを確認します。送信先グループが記録されている履歴では、そのグループも一致する必要があります。過去の送信履歴でグループが未記録（NULLまたは空文字）の場合は、OK-Core側の共有先と所属の確認に従います。送信履歴を持たない手動コピーのKFは、照合不可として表示します。

産出元では次の関係をたどります。複数の経路の発言IDは重複を除いて統合します。

1. `knowledge_fragments` → `knowledge_fragment_utterance_links` → `discussion_utterances`
2. `knowledge_fragments.remarked_utterance_id` → `remarked_utterances` → `discussion_utterances`
3. `knowledge_fragments.externalized_knowledge_id`（または`externalized_knowledge.knowledge_fragment_id`） → `externalized_knowledge` → 直接の発言IDまたは`remarked_utterances` → `discussion_utterances`
4. 発言の`session_id` → `discussion_sessions`と同じ`session_id`を持つ全発言

削除済みのKF・発言・セッションは除外します。session_idが空の発言からはセッションを推測せず、画面に不足を示します。セッション情報の行がまだなくても、発言にsession_idがあれば同じIDの発言を表示します。話者名の不足時には`discussion_participants`、`shimaoka_user_mappings`の表示名を参照します。

## 接続設定（Git対象外）

`php/discussion_transcript_local.example.php`を`php/discussion_transcript_local.php`へコピーして、**OK-Coreサーバーから接続できる**DB情報を設定してください。AWSで両DBが同一DBサーバー上にある場合、hostは`localhost`、portは`3306`、databaseは`hci_knowledge_db`です。phpMyAdminのURL・HTTPポートはDB接続先に使用しません。

接続用ユーザーは、次の既存テーブルにSELECT権限を持つアカウントを利用してください。

- `knowledge_fragments`
- `kf_exports`
- `knowledge_fragment_utterance_links`
- `remarked_utterances`
- `externalized_knowledge`
- `discussion_utterances`
- `discussion_sessions`
- `discussion_participants`
- `shimaoka_user_mappings`

環境変数でも設定できます。環境変数はローカル設定より優先します。

| 環境変数 | 設定 |
| --- | --- |
| `DISCUSSION_TRANSCRIPT_DB_HOST` | DBホスト |
| `DISCUSSION_TRANSCRIPT_DB_PORT` | DB接続ポート |
| `DISCUSSION_TRANSCRIPT_DB_NAME` | DB名 |
| `DISCUSSION_TRANSCRIPT_DB_USER` | DBユーザー |
| `DISCUSSION_TRANSCRIPT_DB_PASSWORD` | DBパスワード |
| `DISCUSSION_TRANSCRIPT_DB_SOURCE_SYSTEM` | OK-Coreに記録されている産出元名（既定: `discussion-kf-system`） |

ブラウザーにはDB接続設定を返しません。文字起こしのHTTPレスポンスは`Cache-Control: no-store`とし、発言テキストはHTMLとして解釈せず表示します。

## DBへの影響とデプロイ

**DB構造の変更、データ移行SQLの適用は不要です。** 添付の2026-10-12版DB構造をそのまま参照します。産出元DBへの照会はREAD ONLYトランザクション内のSELECTだけで、OK-Core側にも文字起こしを複製・保存しません。

デプロイ対象は`index.php`、`css/ok-core.css`、`js/organizational-map.js`、`js/thinking-process-viewer.js`、`js/discussion-transcript-viewer.js`、`php/get_discussion_transcript.php`、`php/discussion_transcript_reader.php`です。加えてサーバー固有の`php/discussion_transcript_local.php`を用意します。ローカル開発用の接続設定をAWSへそのまま配置しないでください。

discussion共有の判定には[種別付きshared_nodesへの移行](shared-nodes-discussion.md)が必要です。共有先は産出元の送信設定またはshared_nodesで指定します。外部化テーブルのgroup_idが0でも、有効なdiscussion共有行があれば参照できます。また、2026-10-12に確認した産出元KF 1〜3の関連発言はsession_id未登録でした。これらはセッション特定不可として表示します。

今回の実装は産出元DBのテーブル構造に依存する暫定アダプターです。将来APIへ移行する場合は、`discussion_transcript_reader.php`の取得処理を差し替え、現在の閲覧権限の確認とUIのレスポンス形式を引き継げます。

## 読み取り専用の動作確認

PHP CLI（mysqli拡張が必要）で`php tests/discussion_transcript_read_test.php 9`を実行します。末尾の9は、セッションと紐づく既存の産出元KF IDです。接続設定を読み、発言のページ取得、重複、並び順、無関係なセッションの拒否を検証します。DBへの書き込みは行いません。
