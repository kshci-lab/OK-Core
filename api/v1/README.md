# OK-Core KF API 接続ガイド（段階1～2）

更新：2026-10-30。APIのURLは`/api/v1`、KFプロトコルは`OK_CORE_KF 1.0`を維持する。

初めて読む方は、[初学者向け：共通APIの読み方と現在の仕組み](../../docs/common-api-beginners-guide.md)を参照してください。

## 1. 提供するもの・変更範囲

- 機械向け仕様：[`openapi.yaml`](openapi.yaml)（OpenAPI 3.1、JSON記法のYAML 1.2）
- 送信JSONの検証仕様：[`schemas/kf-put.schema.json`](schemas/kf-put.schema.json)（JSON Schema 2020-12）
- 送信例：[`examples/kf-put.json`](examples/kf-put.json)
- 共通契約・検証：`contract.php`、受信・既存DB保存：`service.php`

**段階1ではテーブル追加・カラム追加/改名・型変更・データ移行はない。適用SQLは不要。指定ER図の変更もない。** 保存先は現行の`experience_knowledges`、種別付き`shared_nodes`、`kf_source_mappings`である。ER図の共通KF8表への移行は段階3とする。

段階1の受信口は複数産出元を識別できるが、保存アダプターはまだ`experience`扱いである。discussion専用UI・産出元の文字起こしAPIへの切替は段階4で行う。旧`php/import_kf_stub.php`はこの共通契約の対象外であり、段階4で互換処理を集約する。

## 2. OK-Coreの設定

`config.local.example.php`を参考に、Git対象外の`api/v1/config.local.php`へシステムごとの設定を置く。実トークンは本書やAPI応答に含めない。

```php
<?php
return [
    'source_systems' => [
        'forest-platform' => [
            'token' => '<Forestと一致させる送信用トークン>',
            'scopes' => ['groups:read', 'fragments:write', 'fragments:delete'],
            'share_policy' => 'replace',
            'legacy_adopt_numeric_ids' => false,
        ],
        'another-system' => [
            'token' => '<別のシステム用トークン>',
            'scopes' => ['groups:read', 'fragments:write', 'fragments:delete'],
            'share_policy' => 'merge',
        ],
    ],
];
```

- トークンは有効なシステム間で別々にする。同じトークンの重複は設定エラー（503）。
- `enabled: false`のシステムは認証不可。無効にするシステムは空トークンでもよい。
- `source_systems`を指定した場合、従来のトップレベル`token`を認証の抜け道として利用しない。
- 従来の`['token' => '...', 'system_code' => 'forest-platform']`も引き続き動作する。この旧形式は`replace`と旧数値ID採用を維持する。
- `legacy_adopt_numeric_ids`は過去の移行データを照合済みの場合だけ使う。新しい接続ではfalseにする。
- 利用者はSSOの`sub`で対応づける。OK-Core側に有効なユーザーとして存在し、共有先グループの有効なメンバーであることが必要。未登録ならまずOK-Coreにログインする。

## 3. 各支援システムの設定と責務

各システムは自分のDBと非公開設定だけを扱う。OK-CoreのDB接続情報は持たない。サーバー側で共通JSONを作り、HTTP送信する。ブラウザへシステム用トークンを渡さない。

ForestではGit対象外の`php/ok_core_api_local.php`を使用する。項目は`base_url`、`token`、`system_code`、`timeout_seconds`、`context_api_token`。URLは`https://.../OK-Core/api/v1`までを指定する。

**優先順位はアプリ設定ファイル ＞ 環境変数 ＞ 既定値。** ForestのKF送信・文脈API用設定、OK-Coreの受信・Forestマップ参照設定をこの方針に揃えた。環境変数だけの旧運用もフォールバックとして維持する。空文字をファイルに明示した場合、古い環境変数で埋め直さない。

Apacheの`kf-to-ok-core-env.conf`を必須にしない。移行は次の順序で行う。

1. 現在の稼働URL・トークン等を各アプリの非公開設定へ移す。既存ローカル用URLが残っていないか確認する。
2. PHP実行ユーザーが設定を読めることを確認する。
3. API疎通を確認する。
4. Apache側の該当環境変数を外し、再度確認する。Apache側の作業はサーバー管理者が行う。

実値を含む既存の非公開設定は今回自動変更していない。例示ファイルは既存の設定へ上書き配備しない。

## 4. 共通APIと権限

| API | 認証・権限 | 処理 |
| --- | --- | --- |
| `GET /health` | 公開 | DB接続確認。スキーマ適用完了の保証ではない |
| `GET /capabilities` | 公開 | 制限・stage/slot・認証・再送・共有方式・保存機能を取得。登録システムや秘密値は返さない |
| `GET /users/me/knowledge-groups` | Bearer、SSO sub、`groups:read` | 有効な所属グループを取得 |
| `PUT /source-systems/{system_code}/knowledge-fragments/{external_kf_id}` | Bearer、SSO sub、`fragments:write` | 登録・更新・共有 |
| 同URLの`DELETE` | Bearer、SSO sub、`fragments:delete` | 元KFの論理削除通知、全共有の無効化 |

認証用ヘッダーは`Authorization: Bearer <token>`と`X-Acting-User-Sub: <sub>`。トークンに対応するsystem_codeとURLのsystem_codeが違う要求は403。PUTの表出者と操作主体は一致させる。既存KFは所有者のみ更新・削除できる。

各応答は`meta.request_id`と`X-Request-Id`を持つ。要求に有効な`X-Request-Id`があれば引き継ぎ、なければ生成する。エラー形式は次のとおり。

```json
{
  "error": {
    "code": "INVALID_CONTEXT",
    "message": "Invalid context item.",
    "details": [{"field": "context_package.items[0]", "reason": "INVALID_CONTEXT"}]
  },
  "meta": {"request_id": "ok-core-example"}
}
```

主な区分：400入力形式、401認証情報不足/不正、403操作権限/所属/所有者、409版競合、413サイズ、415Content-Type、422プロトコル/文字数、500内部障害、503設定/DB利用不可。`details`は項目検証時に付ける。内部DBエラーやトークンは公開応答へ返さない。

## 5. KF共通モデル・入力制限

| 共通属性 | JSON・URL | 段階1の制限 |
| --- | --- | --- |
| KFの識別 | URLのsystem_code＋external_kf_id | system_codeは英小文字・数字・`_`・`-`、最大64文字。外部IDは文字列1～191文字。空白・制御文字・`/`・`\\`不可。URLエンコードして送る |
| KFの内容 | `summary`、`stages` | summary 1～255文字。stage本文0～255文字 |
| 表出者 | `expresser_sso_sub` | 1～191文字、操作主体と一致 |
| 産出活動 | `activity.type_code` / `external_activity_id` | それぞれ最大64/191文字 |
| 形成文脈 | `context_package` | 文脈ID・items必須。各項目の種類・ID・順序・snapshotを保持 |
| 更新版 | `source_revision` | 1～9223372036854775807。JavaScriptの安全な整数範囲を超える値は文字列で送る |
| 更新日時 | `source_updated_at` | タイムゾーン付きISO 8601。小数秒は6桁まで |
| 共有先 | `share_group_ids` | OK-CoreグループIDの配列。空配列も受理。最大2147483647 |

`stages`配列は必須だが、1.0では空配列・一部stageも互換として受理する。stage_codeはstage1/2/3。stageの重複、同じstage内のslot_code重複は拒否する。slotは任意で、産出元が定義したコード（1～64文字）と文字列値（0～65535文字）を送る。

slotと文脈は`kf_source_mappings.snapshot_json`内に保存する。stage本文は既存列にも保存する。**slotの専用テーブル保存や255文字を超えるstage本文は、段階1で対応済みと表示しない。** capabilitiesの`storage`にもこの制限を返す。

文脈snapshotはオブジェクトを推奨し、既存Forestの空配列も受理する。各itemのdisplay_orderは0以上のINT。活動固有のテーブル名をOK-Coreが解釈して相手DBを検索することはない。

既存DBの照合順序によっては外部IDの大文字小文字・アクセント差を区別できない。その場合、別IDを上書きせず409 `EXTERNAL_ID_COLLISION`を返す。完全な区別が必要な照合順序の変更は段階3でSQLとER差分を提示する。

## 6. 更新・再送・共有の規則

- 同じsystem_code＋外部KF IDを更新する。別システムの同じ数値IDは別KF。
- revisionが新しければ更新。同じ版・同じ内容は成功、古い版は409 `STALE_REVISION`、同版異内容は409 `REVISION_CONFLICT`。
- JSONオブジェクトのキー順は無視する。配列順序・文字列と数値の違い・項目省略と明示値の違いは維持する。
- `Idempotency-Key`は必須にしない。重複防止は外部ID＋revisionで行う。
- DELETEは`X-Source-Revision`と`X-Source-Deleted-At`を付ける。元システムでの削除を通知し、全共有を無効にする。

共有方式はOK-Core管理者がシステムごとに設定する。送信者がJSONで自由に切り替える項目にはしない。

| share_policy | 新しい版のPUTで行うこと | 空配列 |
| --- | --- | --- |
| `replace` | 従来どおり、そのKFの共有先を送信配列に置き換える | 全共有を外す |
| `merge`（新規接続の既定） | 指定先を追加・復活し、OK-Core内の他の共有を維持する | 共有を変更しない |

mergeで一部の共有を取り消す専用APIは段階1の範囲外。OK-Coreの共有管理側で行う。どちらの方式でも送信されたグループの所属は検証する。同版の再送は共有も再更新せず、重複成功として返す。

## 7. Forest側のデータ対応

| Forestの情報 | 共通JSON |
| --- | --- |
| experience_knowledge_id | URLのexternal_kf_id |
| users.sso_sub / SSOセッション | expresser_sso_sub、X-Acting-User-Sub |
| knowledge_fragment_content | summary |
| stage_payload_json（未設定ならstage1～3） | stages |
| source_revision | source_revision |
| 文脈パッケージの活動種別・活動ID | activity |
| 文脈パッケージID・項目・snapshot | context_package |
| OK-Coreから取得して選んだグループID | share_group_ids |

Forestクライアントは従来のint IDに加え、文字列IDを送れる。共有先UI、Outbox再送、Forest文脈APIの権限委任は段階2で整備した。AWSの定期実行は配備先で有効化する。

## 8. 配備・検証

### OK-Core側

同時に配置する：`api/v1/service.php`、`contract.php`、`.htaccess`、`openapi.yaml`、`schemas/kf-put.schema.json`、`examples/kf-put.json`、`php/forest_process_map_client.php`。`config.local.example.php`は設定を作る際の参考。`.htaccess`は公開仕様3ファイルだけをAPIルーターから除外する。

Forestマップ参照の署名は登録済みforest-platformの資格情報を使う。複数システム化した後も別システムのトークンで署名しない。

### Forest側

配置する：`php/ok_core_api_client.php`。`php/ok_core_api_local.example.php`を参考に、実環境の非公開設定を確認する。

### テスト

- OK-Core：`php tests/api_contract_test.php`（DB不要）
- OK-Core：`node tests/api_contract_http_test.cjs`（一時HTTPサーバー、実DB・本番設定不使用）
- OK-Core：PowerShell 7の`Test-Json`で送信例とJSON Schemaを検証
- Forest：`php tests/ok_core_api_config_test.php`（通信・DB不要）

本番のPUT/DELETE試験はテスト用データと適用手順を決めてから行う。段階1の実装テストを通過しても、AWSでの送信・Outbox再送・文脈取得まで検証済みとは扱わない。

## 9. 新規接続時の実装依頼プロンプト

```text
本システムを、この接続ガイドとOpenAPI/JSON Schemaに従ってOK-Coreへ接続してください。
入力：system_code、KF正本と主キー、SSO sub、活動IDと形成文脈の対応、使用プロトコル、
既存の送信管理機能、非公開設定の場所を確認してください。
既存の保存領域を活用し、KFと送信予定を同時に保存してからサーバー側でAPI送信してください。
再送・更新・削除・保存状態の通知と、対象を限定した形成文脈の読取APIを実装してください。
相手システムのDBへ直接接続しないでください。
DB変更は実行せず、変更側・対象DB・テーブル追加/カラム変更等を明記したSQLと手順を提示してください。
指定ER図との差分を図示し、共通契約の検証と未確認範囲を報告してください。
現段階のlegacy保存・文字数制限を越える要件は、段階3以降の変更として分けてください。
```

## 10. 段階2：Forestの参照実装

### ローカル・本番のURL自動切替（2026-11-06）

両システムの`php/deployment_urls.php`が、既知の標準URLを実行環境に合わせて選び直す。設定ファイルを配備のたびに編集する必要はない。

| 環境 | Forest基点 | OK-Core基点 |
| --- | --- | --- |
| localhost / 127.0.0.1 / IPv6ループバック | ローカルのオリジン＋`/forest-platform` | 同じオリジン＋`/OK-Core` |
| archive.kshci-lab.net | `https://archive.kshci-lab.net/software/forest-platform` | `https://archive.kshci-lab.net/software/OK-Core` |

切替対象はForest→OK-CoreのKF・グループAPI、OK-Core→Forestの文脈API、両側SSOのbase_url/redirect_uri、ForestのOK-Core外部リンク。APIでは基点に`/api/v1`、SSOでは`/auth/callback`を付ける。ローカルのポートと`/software/`等の設置先接頭辞もリクエストから引き継ぐ。本番URLをコピーしたローカル設定、ローカルURLをコピーした本番設定の両方を補正する。認証トークン・client_id・client_secretは変更しない。

明示された`APP_ENV=production`または`local`を優先し、未指定なら既知のアクセス先ホストから判定する。任意のHostをAPI接続先に変換しない。他ドメイン・独自パスの明示設定はそのまま使用する。標準URLを意図的に固定したい場合だけ、該当設定の`auto_detect_urls => false`を指定する。Forest送信設定、SSO設定、OK-CoreのForest登録設定または旧Forest参照設定で利用できる。

CLIにはアクセス元ホストがないため、cronには一度だけ実行環境を指定する。ApacheのSetEnvは不要で、URLの書き換えも不要。環境指定なしのCLIは従来の明示URLを保持する。例：

```bash
php scripts/check-ok-core-connection.php --environment=production
php scripts/retry-ok-core-outbox.php --environment=production --dry-run
php scripts/retry-ok-core-outbox.php --environment=production --limit=20
```

SSOの本番・ローカルのリダイレクトURIは、認証サーバー側にそれぞれ登録しておく。SSOクライアントやトークンが環境別の場合、その認証情報は各環境の非公開設定として管理する。今回それらの秘密値やAWSのcronは変更していない。

OpenAPIの`servers`も仕様ファイルからの相対URLとし、`/software/`の有無に対応した。ファイルを別のURL・ローカルディスクからAPIツールへ読み込む場合は、仕様ファイル本来のURLを基点として解決するか、そのツールでサーバーを指定する。

配備には既存の変更ファイルに加えて、**両側の`php/deployment_urls.php`**が必要。Forestの3つの外部リンク設置ページ、CLIスクリプト・cron例も同じ版で配備する。DB変更・SQL適用は不要。`php tests/deployment_urls_test.php`と既存の隔離フローテストで検証する。

### 変更範囲とDB

- **Forest**：共有先をOK-Core APIから取得する。保存済み／送信待ち／送信済みを区別し、Outboxの状態を本人だけが照会できる。
- **Forest**：KF・文脈・Outboxを原子的に保存し、確定したJSONを再送する。文字数はUTF-8の文字数で検証し、現在の255文字上限を越えた内容は保存前に通知する。元ノードの所有者も検査する。
- **Forest**：作成時の採番と再送処理をDBの名前付きロックで排他する。送信済み・旧版のOutboxを再送せず、新版によって無効化された行を古い通信結果で復活させない。
- **両側**：文脈パッケージ・思考過程マップを、対象と期限を限定した閲覧許可で取得する。Forest側の利用者・グループ所属登録や、両DBのグループIDの一致を要求しない。
- **OK-Core**：既存のマップ表示を維持し、KFの右クリックに「形成文脈を表示」を追加する。産出元APIから取得できない場合は、保存済みの文脈を表示し、その旨を明示する。

現行ローカルDBに対して**テーブル追加0・カラム変更0・データ移行0・指定ER図の差分0**。段階3の8表は追加していない。実装テストで使うSQLiteは使い捨ての隔離環境であり、アプリDBの構造変更ではない。

### 文脈取得の窓口

| 提供者 | 窓口 | 用途 |
| --- | --- | --- |
| OK-Core（ブラウザ向け） | `php/get_source_context.php?knowledge_fragment_id=...&group_id=...&mode=latest` | セッション・有効ユーザー・所属・共有を確認して産出元へ照会 |
| OK-Core（ブラウザ向け） | 上記の`mode=snapshot` | 産出元へ通信せず、OK-Coreに保存した送信時点の文脈を表示 |
| 各産出元（Forestで参照実装） | `GET /api/v1/kf-context-packages/{id}?format=...` | 指定KFに属する文脈を取得 |
| 各産出元（Forestで参照実装） | `GET /api/v1/knowledge-fragments/{id}/context?format=...` | KFから文脈を取得。旧移行データにも使用 |

形式は`context-package.v1`（活動＋文脈項目）と`forest.thinking-process-map.v1`（既存マップ）を実装した。APIから取得する文脈パッケージは産出側に保持した形成文脈であり、マップ形式は現在の活動履歴を描画する情報を返す。

OpenAPIには産出側の2つのGETも記載した。これらは各operationの`servers`が産出元を指しており、OK-Coreの基点URLへ送る窓口ではない。既存のForest専用`thinking-process-map`窓口は互換経路として残す。

最新取得に失敗した場合のスナップショット切替は、基本の文脈パッケージ表示で行う。保存した項目だけでマップ全体が復元できるとは限らないため、マップ表示に架空のノード・エッジを補完しない。

### 閲覧許可の署名

サーバー間Bearerには産出元の読み取り用トークンを使う。さらに、その産出元のKF送信用トークンを鍵にHMAC-SHA256の署名を付ける。ヘッダーはOpenAPIを参照。

`X-OK-Core-Proof-Version: context-v1`の署名対象は、次の値を改行で連結したUTF-8文字列。最後の2値が空でもフィールドを省略しない。

```text
OK_CORE_CONTEXT_V1
system_code
GET
resource_path（/api/v1より後。クエリ文字列は含めない）
format
external_kf_id
knowledge_group_id
acting_user_sub
Unix時刻（秒）
legacy_reference（通常は空）
legacy_type（通常は空）
```

時刻差は120秒以内。対象KF・パッケージのURL・形式・グループ・利用者・旧ノード参照を改変すると検証に失敗する。パッケージIDは、署名されたKFが持つcontext_package_idとも照合する。読み取り用Bearerだけで任意の文脈を閲覧することはできない。旧マップ用署名も、そのマップ窓口に限って検証する。

### 設定

新形式の登録では、OK-Coreの`api/v1/config.local.php`の各source_systems設定に、任意で次を追加できる。

```php
'context_base_url' => 'https://<産出元>/software/forest-platform/api/v1',
'context_token' => '<Forestのcontext_api_tokenと同じ値>',
'context_timeout_seconds' => 10,
```

Forestの`context_base_url`を登録していない場合は、既存の`php/forest_process_map_local.php`を読み、従来の設定を継続利用する。登録システムごとに接続先を選び、KFのpayloadに含まれる任意URLへは接続しない。秘密値をブラウザへ返さない。

### 接続確認・再送運用（Forest側）

```bash
# SELECTとAPIのGETだけ。KFの保存・再送・DB改変をしない。
php scripts/check-ok-core-connection.php
php scripts/check-ok-core-connection.php --user-id=<Forestの利用者ID>
php scripts/retry-ok-core-outbox.php --dry-run

# 通常運用：期限に達したPENDING/再送可能FAILEDだけを送る。
php scripts/retry-ok-core-outbox.php --limit=20

# 原因を解消した後、対象を指定して明示的に再試行する。
php scripts/retry-ok-core-outbox.php --outbox-id=7 --force
```

再送待ちは1分→5分→30分。以降や401/403/入力不正は自動再送を停止する。`--force`でもSENT/SUPERSEDEDを再送せず、payloadや版を書き換えない。受理結果のKF ID・版が一致しない応答はSENTとして扱わない。

Outboxの日時はアプリ側の日本時間を使い、期限判定も同じ時刻で行う。DBサーバーのNOW()とのタイムゾーン差で再送が遅れないようにした。

`scripts/ok-core-outbox.cron.example`に1分間隔の例を用意した。PHP・アプリ・ログの絶対パスを実環境に合わせ、PHP実行ユーザーで接続確認とdry-run後に有効化する。今回、AWSのcronは登録していない。再送コマンドはterminal_failures件数を返し、解消していない停止行があれば終了コード1を返すため、ログ・監視で確認する。

### 旧構造の環境だけに必要なSQL

接続確認で`shared_nodes.knowledge_fragment_id`不足が出た場合は、Forestの共有列の過去の改名が未適用の可能性がある。

- **変更側**：Forest-platformのDB。
- **変更種別**：`shared_nodes.experience_knowledge_id` → `knowledge_fragment_id`の**カラム改名**のみ。
- **SQL**：Forestの`sql/2026-10-15_rename_shared_nodes_knowledge_fragment_id.sql`。
- **適用**：shared_nodesをバックアップし、SQLの`USE forest_platform`を対象DB名と照合して、利用者が実行する。
- **確認**：新列だけが存在することを確認して接続確認を再実行する。両列がある場合は自動統合せず内容を照合する。

現行ローカルDBは改名済みで、このSQLの実行は不要。段階2として新たに実行するマイグレーションではなく、未適用環境向けに既存の互換SQLを同梱したもの。今回はどのDBにも実行していない。Outbox等そのものが不足する環境は、その時点の構造を確認して適用SQLを別途確定する。

### 配備するファイル群

- **OK-Core**：`php/source_context_client.php`、`source_context_service.php`、`get_source_context.php`、`forest_process_map_client.php`、`get_thinking_process_map.php`。`js/source-context-viewer.js`、`thinking-process-viewer.js`、`css/source-context-viewer.css`、`index.php`。`api/v1/contract.php`と仕様・設定例・本書。
- **Forest**：`api/v1/index.php`、`process_map_read_service.php`、`context_contract.php`、`context_read_service.php`。`php/kf_sync_service.php`、`ok_core_groups.php`、`ok_core_bridge.php`、`get_kf_sync_status.php`、`thinking_edit_processmap_maneger.php`。`js/ok-core-sharing.js`、`thinking-process-network.js`、`organizational-map.js`、`forest-mrn/index.php`。共有カラム修正を含む`php/organizational_map_manager.php`、`forest-mrn/php/get_knowledge_fragments_by_group.php`。`scripts/check-ok-core-connection.php`、`retry-ok-core-outbox.php`、cron例。

署名方式と文脈応答を両側で揃えるため、同じリリースとして配備する。実トークンを含む設定ファイルは上書きしない。

### 検証範囲

`node tests/ok_core_phase2_flow_test.cjs`（Forest側）は、使い捨てSQLiteとローカルPHPサーバー上で、実際の処理コードの保存・停止・自動/明示再送・重複・ロールバック・委任閲覧・所有者・更新・削除・snapshot切替を検証する。SQLアダプターはテスト用であり、MySQL固有のロック・照合順序等は実環境の確認と区別する。

`php tests/context_grant_test.php`は署名の改変・期限等、`node tests/ok_core_sharing_test.cjs`は組織選択と送信状態表示を確認する。既存の段階1テストも維持する。本番データを使ったPUT/DELETEや既存FAILED行の再送を、この検証のために自動実行しない。
