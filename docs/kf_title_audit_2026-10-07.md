# KFタイトル保存確認（2026-10-07）

対象はローカルMAMPの `hci_knowledge_db`（localhost:8889）とローカル配置コード。AWSへの変更・配置は行っていない。IDは送信元の `knowledge_fragments.knowledge_fragment_id`。

## 入力から保存まで

`client/src/pages/KnowledgeHome.jsx` の「産出する知のタイトル」入力欄は `reflectionData.title` に反映される。`handleSaveExternalization` は空欄を拒否し、`POST /api/knowledge_mysql/externalize` のJSONに `title: reflectionData.title` を含める。現在の保存SQLは `knowledge_fragments.title` に `toDbString(title, 255)` をバインドする。前後空白は除去される。

Gitコミット `5d7146b`（2026-07-29 11:28:59 JST）で外部化APIのtitle保存が追加されていた。その直前のコードではtitleを受け取ってもINSERTの列・値にtitleがなかった。ID 3〜9の作成日時はすべてこの変更前。旧コードの保存漏れは確認できたが、各レコード作成時の実HTTPリクエストそのものは残っておらず、当時の入力タイトルを断定できない。

今回の修正：

- `POST /api/knowledge_mysql/fragments` が受け取ったtitleを捨てていたため、INSERTに追加。互換性のためtitle省略時は従来どおりNULL。
- `/externalize` のtitle省略時の「無題」補完を削除。画面と同様に省略・空欄を400で拒否する。
- `server/test/kf_title_persistence.test.js` を追加。
- 作業開始前から存在した `buildOkCorePayload` のtitle送信修正と `session_xml_mapping.test.js` の変更は保持した。

## 検証

`KF_TITLE_DB_TEST=1 node --test test/kf_title_persistence.test.js test/session_xml_mapping.test.js`：7件成功。

保存ハンドラーの実コードを呼び出し、実DBのスキーマをコピーしたMySQL接続専用TEMPORARY TABLE上で以下を確認：新規作成のタイトル保存と読み戻し、編集後の保存と読み戻し、別作成APIのタイトル保存、title省略・空欄の拒否、OK-Core送信JSONへの引き継ぎ。DB初期化・マイグレーションと認証ミドルウェアは検証対象から除外。既存テーブルへの書き込みは行わず、テスト接続終了時に一時テーブルは破棄される。

入力欄と保存JSONの対応はソースと既存テストで確認。実ブラウザ入力からHTTP経由のE2E検証、AWS動作確認は実施していない。

## 既存データの調査範囲

- `knowledge_fragments` の本文・title・stage1〜3・旧保存先ID：対象7件のtitleはNULL。
- `externalized_knowledge`：対象IDへの直接対応なし。同じ本文の旧レコードに「テストする」があるが、KF ID 1 / remarked ID 1に紐づき、ID 3とは理由・振り返り・作成日時も異なるため転用しない。
- `kf_exports`：対象IDの39件（ID順に1, 8, 2, 3, 23, 1, 1件）。送信JSONと応答のreceivedPayloadにtitleなし。
- `ok_core.externalized_contents.raw_payload`：該当する送信元IDのtitleなし。
- 関連 `remarked_utterances`、`discussion_sessions`：入力タイトルの保存なし。session_titleは議論セッション名でありKFタイトルではない。
- `discussion_knowledge_suggestions`：0件。
- ローカルSQLite `knowledge_entries`：無関係の1件のみ。
- `server/db_dump_raw.txt`：ユーザー・ルーム・発言等のログで、KFタイトルの保存テーブルなし。確認したローカル配布バックアップにもDBデータダンプなし。
- MySQL general_log / log_bin は現在OFF。過去のすべての外部バックアップまで調査したことは意味しない。

## 元のKF本文と候補

以下は復元できた元タイトルではなく、本文・振り返りに基づいて決めた補完タイトル。情報が乏しいものも含むが、依頼に基づき2026-10-07に `knowledge_fragments.title` へ登録した。元の本文・stage項目は変更していない。

| KF ID | 元のKF本文（knowledge_fragment_content） | タイトル候補 | 根拠・留意点 |
|---:|---|---|---|
| 3 | まちょっと今日、 | 今日の話題の切り出し | 本文が断片、振り返りが数字のみ。元タイトルの復元ではない。 |
| 4 | あの、二年目だったかな、一年目だったかなの時に、こうやって売ったんですよ。 | 過去の販売経験を振り返る | 本文に基づく補完タイトル。振り返りは「あ」のみ。 |
| 5 | stub確認用のKF候補です | stub APIで外部連携を早期確認する | stage3の外部連携の早期確認・stub API疎通確認に基づく。 |
| 6 | end-to-end確認用のKF候補です | 実システム間の接続でKF送信経路を検証する | stage3の送信経路・実システム間接続確認に基づく。 |
| 7 | でこっちはローカルホストで開いてるからおかしいってこと? | localhostでの表示差を確認する | 問いの内容に基づく補完タイトル。原因がlocalhostと確定したわけではない。 |
| 8 | [13:05] 江川虎太郎: え、こうだってさ、俺だけしか寝てない、お菓子から。<br>[13:05] 江川虎太郎: なんで? | 発言の意味と理由を確認する | 文字起こしの意味が不明瞭で、振り返りもaのみ。元タイトルの復元ではない。 |
| 9 | [11:04] 江川虎太郎: あ、あ、あ。 | 短い発声の記録 | 発声のみで知の内容を特定できない。元タイトルの復元ではない。 |
