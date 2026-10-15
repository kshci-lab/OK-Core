# shared_nodesのKF参照列の改名（2026-10-15）

追記：discussionの共有も統一する変更を適用しました。以下は改名時点の記録です。現在の共有テーブルの使い方と追加SQLは[discussion共有への対応](shared-nodes-discussion.md)を参照してください。

## 変更内容

OK-CoreとForest-platformの両方で、`shared_nodes.experience_knowledge_id`を`shared_nodes.knowledge_fragment_id`へ改名します。参照している既存のKF ID、行ID、共有先、削除状態、カラムの型・NULL可否・デフォルト値を維持します。列を利用するインデックスはDBが新しい列名へ追従します。インデックス自体の既存の名前は保持します。

KF本体の`experience_knowledges.experience_knowledge_id`、`kf_source_mappings.experience_knowledge_id`、既存のAPIリクエスト／レスポンスのキーは、それぞれの役割のままです。今回の改名対象は共有テーブルの参照列です。

## 反映箇所

- OK-Core: 共同化マップ、連結化のKF一覧、KF詳細、組織知ツリーの参照照合、グループ内KFの権限確認、思考過程表出化マップの参照、KF受信APIの共有登録・更新・削除。
- Forest-platform: KF共有時の保存、同期payloadの共有先抽出、思考過程表出化マップ参照APIの権限確認と取得、旧組織知マップとforest-mrnのKF一覧。
- OK-Coreの現在用監査SQLとREADMEの共有テーブル向けSQL。

## DB変更SQL

phpMyAdminで実行するSQLは各リポジトリにあります。

- OK-Core: `sql/2026-10-15_rename_shared_nodes_knowledge_fragment_id.sql`（`USE ok_core`を明記）
- Forest-platform: `sql/2026-10-15_rename_shared_nodes_knowledge_fragment_id.sql`（`USE forest_platform`を明記）

MySQL 5.7とMariaDB 10.6で利用できる`CHANGE COLUMN`を使用します。元カラムの型・NULL可否・デフォルト値を実DBから取得するため、OK-CoreとForest側でNULL可否が違っていても維持されます。すでに改名済みの場合は再実行しても変更しません。両方の列が存在する、または両方とも存在しない場合はエラー状態のメッセージを返すので、そこで構造を確認してください。

AWSでは対象テーブルをバックアップし、共有・同期処理の実行を止めたうえで、SQLと変更したPHPファイルを同じメンテナンス作業で反映してください。旧コードと新カラム、新コードと旧カラムの組み合わせでは動きません。ForestのDB名が`forest_platform`以外なら、そちらのSQLの`USE`だけを対象DB名へ変更してください。

ローカルMAMPのMySQL（8889番）にある`ok_core`と`forest_platform`には適用済みです。改名前のバックアップはOK-Coreの`backups/shared_nodes_rename_20261015_052611/`にあります。OK-Coreの32行、Forestの37行について、改名前後の全値の一致と、移行SQLの再実行を確認しました。なお、確認時のForestの接続設定は3306番で、そちらには接続できませんでした。今回は接続設定を変えず、MAMP内のDBを変更しています。別のDBサーバーでForestを動かす場合は、そのサーバーのDBにも移行SQLを適用してください。

`sql/2026-10-07_phase34_schema.sql`とForestの`php/DB`内の構造ダンプは、過去時点の移行・記録として旧カラム名を残しています。新規環境へ順に構築する場合は、過去のスキーマSQLの後に今回の改名SQLを適用してください。

## KF全体への拡張で必要な点

現状のexperience KFとdiscussion KFは別テーブルに保存され、同じ数値IDを持つ場合があります。したがって、改名後の列へ両方のIDを入れるだけでは区別できません。今回の既存の共有処理はこれまでどおりexperience KFを参照し、discussion KFは`externalized_contents.group_id`で共有先を判定します。

全KFの共有先をこのテーブルに統合する段階では、`fragment_source_type`などの種別列を併用するか、全KF共通のIDを発行するテーブルを導入する必要があります。その際には共有の一意キー・JOIN・権限チェックも種別を含めた形に変更します。今回の改名だけで、別種別の同じ数値IDを共有テーブルへ登録しないでください。
