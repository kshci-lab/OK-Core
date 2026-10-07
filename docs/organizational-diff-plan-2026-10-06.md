# Organizational 機能の差分と適用順（2026-10-06）

## 比較対象と前提

- Forest-platform: `develop_organizational-system`。比較した画面は `forest-mrn/index.php`、`forest-mrn/js/organizational-combination-tab.js`、`js/organizational-map.js`、`css/organizational-map.css` と `forest-mrn/php/` の関連エンドポイント。送信側はルートの `php/kf_sync_service.php` / `php/ok_core_api_client.php`。
- OK-Core: `develop_kawa` の現在の作業ツリー。直前のグループ別KF表示修正（未コミット）も含む。既存の作業ツリー変更はこの調査では変更しない。
- Forestの分離文書は旧組織知画面を停止済みと説明する一方、現在の `forest-mrn/index.php` には組織知タブ、画面、JS読込が残る。動作を文書だけから判断せず、現在のコードを比較元とした。
- これはコード差分とDBの読み取り結果に基づく計画。各画面操作のブラウザ通し試験は、この調査では実施していない。

## 現行DBと連携の基準点

| 項目 | Forest DB | OK-Core DB / 実装 |
| --- | --- | --- |
| 組織知ノード | 有効30件 | 有効24件（グループ100が19件、グループ未割当5件）、リンク26件 |
| KF配置 | 型付きの `fragment_source_type` / `fragment_source_id` があり37件 | `externalized_contents_id` 中心の旧形式で0件 |
| 議論履歴 | 8件 | 0件 |
| KF送信 | Outbox作成・再送コードあり。現在2件とも `API_TOKEN_NOT_CONFIGURED` で FAILED、再送予定なし | `api/v1` に受信実装がなく、`health` は404（前回調査） |

現在のOK-Coreは既存DB内のKFをグループ別に取得・表示する経路を持つ。一方、Forestから新規送信されたKFをAPIで受け取る経路は未成立。Forest DBの組織知・配置・履歴は自動ではOK-Coreへ移っていない。

## 主な差分

| 領域 | Forest側の改善 | OK-Coreとの差、適用上の判断 |
| --- | --- | --- |
| KF一覧の更新 | 連結化タブを開くたびに一覧とツリーを再取得し、手動再読込ボタンと読み込み状態を表示。ズームの「100%」ボタンを再読込に置換し、スクロール領域も調整 | OK-Coreは初回・グループ変更時が中心。再読込とCSSは比較的独立して適用可能。現在のグループ変更の競合対策と権限確認は維持し、ズームリセット操作を残すかも画面設計で決める |
| 議論履歴 | 手動更新、表示中のみ5秒間隔の取得、キャッシュ回避、選択変更・古い応答の無視、内容が同じときの再描画抑制、スクロール維持 | OK-Coreは基本的に一度取得。UIとJS、`get_discussion_history.php` の no-cache を組で適用できる。ただし議論履歴0件の現DBで複数人更新の試験データが必要 |
| KF配置 | `source_type:source_id` をカードの識別子とし、experience / discussion の同一数値ID衝突を回避。両種の配置を保存し、縦方向の描画領域を拡張 | OK-Coreの配置表・保存APIは数値ID中心。型付き列、複合一意キー、既存配置の変換、取得・保存JS/PHPを一括で導入する。Forestの `save_fragment_order.php` はリクエスト中にALTERするため、そのまま採用しない |
| 組織知ツリー・詳細 | `include_unassigned=1`、グループの同名ルート統合、未割当ルートを親に持つノードの表示補正。`knowledge_fragment_id` と `node_type` を優先して古いリンクを除外 | OK-Coreには未割当ノード5件とリンク26件がある。まず参照整合性を調べ、未割当ノードの所属を決める。表示時の補正をそのまま恒久仕様にせず、グループ境界とリンクの正本を明確化する |
| 組織知マップ | When/What/Why、組織知化の根拠、コメント、更新日時をツールチップに表示。KFとのリンクを持つ親ノードも表示 | OK-Coreは一部の詳細のみ。現在のAPI/DOMにある構造化項目を安全に表示できる。HTMLの表示とグループ切替を確認する |
| 出典の思考過程マップ | KFカードの右クリックから元の思考ノードを開く。Forestの思考過程マップ関数と浮動パネルを利用 | 出典ID等のメタデータはOK-Coreにも渡せるが、Forest内の `showThinkingProcessMap` とDOMはOK-Coreにない。SSO付きのForest深いリンク、または文脈APIの利用方式を先に決める。ForestのUIコードを直貼りしない |
| 議論状態 | Forestでは議論ボタンを進行中・完了の二状態として扱い、送信中は操作を無効化 | OK-Coreでは未開始を含む三状態で循環する。状態遷移の業務仕様を決めてから揃える。単純移植すると既存の「未開始へ戻す」操作が消える |
| KF送信・同期 | Forestは作成・更新・削除をOutboxに記録してOK-Core APIへPUT/DELETE。再送関数あり | 送信処理はForestの責務。OK-Coreに必要なのは認証付き受信、外部IDと版の管理、共有先の検証、既存表示表への反映。旧Forest組織知PHPの直接DB処理をOK-Coreの同期機構として移さない |

組織知の登録処理 `insert_knowledge_node.php` と議論投稿処理 `save_discussion_history.php` は両リポジトリで同一内容だった。したがって登録の整合性改善はForest側から持ってくる差分ではなく、OK-Core側で新たに実施する課題である。

Forest側の `organizational-map.js` にはオブジェクトへ `JSON.parse` をかける旧不具合が残る。OK-Coreで直前に修正したレスポンス処理と、グループ別KF取得の厳密なグループ判定を維持する。Forest側の議論KF抽出はグループ所属ユーザーを条件にするため、OK-Coreで追加した `group_id` 条件へ戻してはならない。相対URL（Forestの `../php/`）と保存成功時の「Forestで保存・OK同期待ち」という文言もOK-Coreには適さない。

## 推奨する適用段階

### 1. 現行DBの表示を基準として固定

グループ100のKF・組織知、グループ未割当ノード、experience / discussion の同一数値ID、権限外グループの応答を記録する。OK-Coreの現在のJSON処理とグループ判定を回帰条件にする。組織知ノードとリンクの参照関係を調べ、未割当5件の所属方針を決める。

**実施結果:** 現行DBでの表示・権限確認と必要な取得修正を行った。件数、未割当ノード、旧リンク、検証結果は `docs/organizational-baseline-2026-10-06.md` に記録した。未割当ノードの所属先は確定できず、グループ100には混ぜていない。

**完了条件:** 選択したグループだけのKFと組織知が表示され、他グループのデータが混入せず、コンソール例外がない。

### 2. DB変更不要の画面改善を適用

KF再読込、議論履歴の手動更新・表示中の定期取得、競合応答の破棄、スクロール調整、組織知の詳細ツールチップをOK-Coreの画面構造に合わせて実装する。議論履歴の取得は選択KF・ソース種別に限定する。議論状態の二状態化は仕様が決まるまで保留する。

**実施結果:** KF一覧の手動再読込とタブ再表示時の更新、議論履歴の手動更新と表示中5秒間隔の取得、選択グループ・KF変更後の古い応答の破棄、同一内容の再描画抑制とスクロール位置の維持を実装した。マップの組織知ツールチップにWhen/What/Why等を追加した。議論APIはOK-Coreのセッション、グループ所属、KF所属を検証し、履歴取得をキャッシュしない。DBスキーマは変更していない。現行DBの議論履歴は0件のため、別端末投稿の画面反映と登録・編集の一連の操作は実データでの再確認が必要。

**完了条件:** グループ切替と再読込を連続しても古い応答が上書きせず、別端末の投稿が選択中のKFにだけ反映される。既存登録・編集操作が動く。

### 3. 現行DBでKF受信と組織知登録を成立させる

Forestの現行API契約に合わせ、OK-Coreへ `health` / `capabilities` / 所属グループ取得 / KF PUT・DELETEを実装する。APIトークン、SSOの利用者、共有先を検証し、外部IDと版を保存して重複・逆順配送を処理する。ForestのOutboxを再送し、OK-Coreの既存KF表示に反映する。Forest側でFAILEDになっている2件は原因を直した後に明示的に再試行する。

**実施結果（2026-10-07）:** 受信APIと対応表、トランザクションによる組織知登録・編集を実装した。ForestのOutbox 2件を再送し、双方SENTとなった。移行先グループの指定と検証結果は `docs/organizational-phase34-2026-10-07.md` に記録した。

登録時にグループ所属、親ノード所属、選択KFの存在・共有状態を検証し、ノードとリンクを同一トランザクションで保存する。手動の `MAX+1` 採番をやめる。受信の重複防止には現行DB上に外部ID・版の最小限の対応表が必要で、これは組織知DB全体の新設計への切替とは分ける。

**完了条件:** ForestのKF作成・更新・削除とグループ変更がOK-Coreへ届き、再送しても二重登録しない。受信したKFを選択して組織知に登録・再表示でき、失敗時はOutboxに追跡できる状態が残る。

### 4. 型付き配置とツリーの整合性を適用

配置表へ型付き参照と `(group_id, fragment_source_type, fragment_source_id)` の一意性を明示的な移行SQLで追加する。既存行がある環境ではexperienceとして変換し、衝突を検査する。表示・保存API・JSを同時に切替え、PHPリクエスト中のDDLを除去する。ツリーは未割当ノードと重複ルートを調査して所属を確定し、リンク表と旧CSV列の優先規則を統一する。

**実施結果（2026-10-07）:** 型付き配置スキーマと読込・保存を適用し、同一数値IDの2ソースを別座標で検証した。ツリーの自動DDL・ルート自動生成を停止し、リンク表優先を明確化した。旧ノード8件の帰属と旧リンク2件はDBから確定できないため変更せず、監査SQLで列挙した。詳細は `docs/organizational-phase34-2026-10-07.md` を参照。

**完了条件:** 同じ数値IDのexperienceとdiscussionを別々に配置・再読込できる。グループ100の既存組織知とリンク26件を失わない。

### 5. 出典参照と旧Forestデータの必要分を移す

Forest思考過程マップへの遷移は、SSOと出典ID・閲覧権限を通す設計を定めてから追加する。旧Forest DBの配置37件や議論履歴8件を移す場合は、対象KF・利用者・グループの対応を確認した範囲に限定する。

**完了条件:** OK-Coreから許可された出典を開ける。移行した履歴・配置が適切なグループとKFにだけ紐付く。

## コード上の主要な参照先

- ForestのUI: `forest-mrn/js/organizational-combination-tab.js`、`js/organizational-map.js`、`css/organizational-map.css`、`forest-mrn/index.php`
- Forestの送信: `php/kf_sync_service.php`、`php/ok_core_api_client.php`、`php/ok_core_bridge.php`
- Forestの旧組織知PHP: `forest-mrn/php/get_knowledge_tree.php`、`get_knowledge_fragment_detail.php`、`get_knowledge_fragments_by_group.php`、`save_fragment_order.php`
- OK-Coreの適用先: `index.php`、`js/organizational-combination-tab.js`、`js/organizational-map.js`、`php/get_knowledge_tree.php`、`php/get_knowledge_fragment_detail.php`、`php/get_knowledge_fragments_by_group.php`、`php/save_fragment_order.php`、`php/insert_knowledge_node.php`
