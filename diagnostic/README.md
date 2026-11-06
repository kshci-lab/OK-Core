# 組織知診断システム用 OK-Core 取得モジュール

このディレクトリは、新しい診断システム側に置く最小の取得・保持境界です。
認証方式が確定するまでは `sample_response.json` を使って検証します。

## 方針

- OK-Coreのレスポンス取得とAI解釈結果を別データとして扱う
- ノードは `node_id` を主キーとして保持する
- KFは `(source_type, source_id)` を複合識別子として保持する
- `children` の有無やKFの有無だけで実組織知を除外しない
- `When` / `What` / `Why` の欠損値を許容する
- 参照切れはエラー終了せず、`unresolved_fragment_links` として保持する
- 更新時は取得スナップショットのハッシュを比較し、AI事前処理の再実行要否を判定する

## 実行

```sh
php diagnostic/tests/ok_core_knowledge_test.php
```

本番APIへのHTTP通信・認証処理は未実装です。認証方式確定後、`OkCoreKnowledgeSource` に認証済みHTTPトランスポートを注入します。
