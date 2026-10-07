# OK-Core KF受信API

Forest-platform の `php/ok_core_api_client.php` が呼ぶ `/api/v1` の受信口。

## 設定

1. `sql/2026-10-07_phase34_schema.sql` を対象DBへ一度適用する。配置表の旧行・索引を事前に確認し、バックアップを取る。
2. OK-Coreで `OK_CORE_API_TOKEN` を設定するか、Git管理外の `api/v1/config.local.php` を `config.local.example.php` を参考に作る。
3. Forest-platformの `OK_CORE_API_TOKEN` に同じ値を設定する。`OK_CORE_API_BASE_URL` はOK-Coreの `/api/v1` を指す。
4. `X-Acting-User-Sub` に対応する有効なOK-Core利用者と、共有先グループの所属を準備する。

`health` と `capabilities` 以外はBearerトークンとSSO subを要求する。APIは `experience_knowledges` / `shared_nodes` に表示用データを保存し、`kf_source_mappings` に外部ID・版・payloadスナップショットを保持する。

ローカルの設定ファイルとDBバックアップはGit管理外。運用環境ではHTTPSと長いランダムトークンを使う。
