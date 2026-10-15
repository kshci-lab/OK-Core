# Forest思考過程マップの閲覧

OK-Coreの共同化マップまたは連結化タブのexperience KFを右クリックし、「思考過程表出化マップを表示」を選ぶと、Forestが保持する現在のマップを読み取り専用のオーバーレイで表示します。現在のタブは切り替わりません。拡大・縮小・全体表示ができ、閉じるボタン・Escape・背景クリックで閉じられます。

## データと認可

- OK-Coreはログイン中のSSOユーザーが選択グループに所属し、KFがそのグループに共有されていることを検査します。
- `kf_source_mappings`からForestの`experience_knowledge_id`を特定します。手動移行した旧KFでは元の`thought_experience_node_id`と`experience_type`も署名して渡し、その元ノードから取得します。両DBでKFの数値IDが異なる場合や、ForestのKF行がなく元ノードだけがある場合にも対応します。返された元ノードIDはOK-Core側で再照合します。
- OK-CoreのサーバーからForestの`GET /api/v1/knowledge-fragments/{id}/thinking-process-map`を呼びます。OK-Coreでの閲覧権限確認後、既存の`OK_CORE_API_TOKEN`で対象KF・グループ・SSOユーザー・時刻を署名します。Forestは署名を検証します。ブラウザにトークンは渡しません。
- OK-Coreはログイン中のユーザーのグループ所属と、種別付き`shared_nodes`の有効なKF共有を確認します。Forestはサーバー間BearerトークンとOK-Coreの署名を検証し、`process_nodes`、`node_versions`、`triggers`、`process_edges`を読み取ります。閲覧権限の基準はOK-Coreで、Forest側のグループ所属や共有先の一致は要求しません。削除済み元ノードや存在しないマップは返しません。
- triggerの`icon_id`と、そのマップで使用する`trigger_icons`（PNGのdata URI）はForestがAPI応答に含めます。OK-Coreは受け取った画像を描画し、画像がない旧API応答では従来の丸ノードを表示します。画像ファイルや活動種別と画像の対応表をOK-Coreへ配置する必要はありません。

## デプロイ設定

Forest側で既存の`FOREST_CONTEXT_API_TOKEN`を設定します。OK-Core側にも同じトークンとForest API v1の基点URLを設定します。

```text
FOREST_CONTEXT_API_BASE_URL=https://<Forestの公開ホスト>/software/forest-platform/api/v1
FOREST_CONTEXT_API_TOKEN=<Forest側と同じ読み取り用トークン>
```

環境変数が使えない場合は`php/forest_process_map_local.example.php`を`php/forest_process_map_local.php`へコピーして値を設定します。実ファイルはGit管理対象外です。URLやトークンが未設定の場合、OK-Coreは画面に設定エラーを表示します。

## DB変更

**今回の機能のためのSQL変更はありません。** Forest側は既存の思考過程テーブルとKF共有テーブルを使用します。OK-Core側は既存の`experience_knowledges`、`shared_nodes`、`kgroup_user_link`、`kf_source_mappings`を使用します。`kf_source_mappings`はこれまでのKF受信機能で使用している既存テーブルです。

## 確認

1. Forest側とOK-Core側にコードと設定を一緒に配置します。参照用`FOREST_CONTEXT_API_TOKEN`に加え、KF送受信用`OK_CORE_API_TOKEN`が両側で一致している必要があります。
2. 同一グループに所属するユーザーでOK-Coreへログインし、共有済みexperience KFの右クリックメニューからマップを開きます。
3. 同じOK-Coreグループの別ユーザーは、Forestに所属登録がなくても表示できることを確認します。別グループのKF、共有解除したKF、未ログイン状態では取得できないことも確認します。
4. 手動移行したKFはForest側の元ノード参照がOK-Coreに残っている場合だけ表示できます。参照のないKFには、その理由を示すメッセージが出ます。

取得に失敗した場合は、OK-Coreの`php/get_thinking_process_map.php`の応答コードを確認します。`FOREST_NOT_CONFIGURED`はOK-Core側設定不足、`forbidden_group`はOK-Core側で非所属、`fragment_not_shared`はOK-Core側でKF未共有、`VIEWER_PROOF_INVALID`は署名・時刻・両システムの設定の不一致、`PROCESS_MAP_NOT_FOUND`はForest側で元KF／元ノードを解決できない状態です。旧版のForestで`PROCESS_MAP_FORBIDDEN`になる場合はForest側コードも更新してください。

2026-10-15の権限修正ではDB変更はありません。`php/get_thinking_process_map.php`、`php/forest_process_map_client.php`、`js/thinking-process-viewer.js`と、Forest側の`api/v1/index.php`、`api/v1/process_map_read_service.php`を反映します。旧移行KFの元ノード参照も署名に含めるので、両側のコードを同時に更新してください。

ローカルで確認したKF11170はOK-Coreのグループ100へ共有されていますが、現在の`forest_platform`には対応する元KF行も元ノード`1787846387489343526`もありません。`forest_platform_0906.process_nodes`には元ノードが残っています。権限の修正だけでは元データは復元されないため、適切な保存DBから対象マップを復元する必要があります。今回の権限修正ではDBを復元・変更していません。
