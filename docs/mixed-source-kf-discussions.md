# 異なるsourceのKFを議論・組織知登録に使用する

## 操作

1. 連結化タブで主対象のKFを選択します。
2. 「追加選択」をオンにし、experience／discussionのKFを追加します。
3. 選択中のKFを対象として議論を投稿し、議論状態を更新できます。
4. 組織知を登録すると、選択した全KFがそれぞれのsourceとIDで紐づきます。両sourceを含む組織知は、experienceのみ・discussionのみのどちらのフィルターでも表示されます。

同じ数値IDでも別sourceなら別KFとして選択できます。組織知の詳細から編集するときも混在したリンクを維持します。「現在選択中のKFを反映」で現在の選択を接続KFへ取り込めます。接続KF欄を直接編集する場合は`experience:11147,discussion:9`の形式を使います。

## 保存方式と互換性

既存のDBを使用し、今回の機能のためのカラム追加・テーブル追加は行いません。

- リクエストの`fragment_refs`は`["experience:11147","discussion:9"]`のJSON配列です。
- 単一sourceの投稿は従来どおり、discussion_historyの`knowledge_fragment_id`へ数値CSV、`fragment_source_type`へそのsourceを保存します。
- 混在した投稿は、同じカラムへ`experience:11147,discussion:9`の種別付きCSV、discussion_historyの`fragment_source_type`へ`mixed`を保存します。**mixedは議論履歴の対象形式の印であり、shared_nodesのKF種別には使いません。**
- 従来の単一sourceの履歴も読み込めます。種別がNULL／空文字の旧履歴はexperienceとして解釈します。
- 投稿の取得・議論対象の復元・KF間の関連線は、数値IDとsourceの両方で照合します。投稿の全対象KFがそのグループに共有されている場合だけ、その投稿を返します。
- 組織知の接続KFは既存の`knowledge_explorer_fragment_links`に1件ずつ保存します。既存のexperience用CSVにはexperienceのIDだけを保存します。

前段の種別付きshared_nodesへの移行は必要です（`sql/2026-10-15_shared_nodes_discussion.sql`）。今回確認したDBは`discussion_history.knowledge_fragment_id VARCHAR(255)`、`fragment_source_type VARCHAR(32)`で、混在形式をそのまま保存できます。対象KFの保存文字列が255文字を超える場合には、切り捨てず選択を減らすよう案内します。

フロントエンドとPHPを同時に反映してください。変更対象は`js/organizational-combination-tab.js`、議論の保存・取得・対象復元・関連取得・状態更新の各PHP、`knowledge_node_create.php`、`update_knowledge_node.php`、および追加した`fragment_references.php`、`discussion_history_store.php`、`discussion_endpoint_bootstrap.php`です。Forest側への変更はありません。

## 検証

`php tests/mixed_kf_flow_test.php store`で混在・従来履歴・同一IDの種別分離・対象権限を確認できます。引数を`save`、`get`、`targets`、`relations`、`create`、`update`、`tree`へ変更すると各取得／登録処理を検証します。実DB上に同じIDの両種別KFと共有先があることが前提です。書き込み先は接続内の一時テーブルで、実KF・議論・組織知は変更しません。

`node tests/mixed_kf_selection_test.cjs`で、追加選択・同一IDの区別・両sourceのフィルター表示を確認できます。
