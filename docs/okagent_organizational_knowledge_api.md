# OKAgent向け組織知読み取りAPI

## URLと認可

`GET /OK-Core/php/get_organizational_knowledge.php?group_id={group_id}`

`group_id` は正の整数で必須です。OK-Coreのログイン済みセッションCookieを送信した呼び出しだけを受け付けます。セッションの `USERID` が、`kgroup_user_link.deleted = 0` かつ `knowledge_groups.deleted = 0` の同じグループに所属していない場合は `403` を返します。DB資格情報は応答に含めません。

OKAgentが別オリジンのブラウザアプリである場合、このAPIは現状CORSを許可していないため、OK-Coreと同一オリジンで呼ぶか、ログイン済みセッションを扱えるOKAgent側サーバーの同一オリジンプロキシを用意してください。

## 応答

成功時は `200` と次の形式を返します。`nodes` は親子関係を `children` に保持した配列です。親が削除済み・別グループの場合、その子は `parent_id` を保持したままルートとして返します。

```json
{
  "status": "ok",
  "group_id": 42,
  "nodes": [
    {
      "node_id": 120,
      "parent_id": null,
      "title": "振り返りを共有する",
      "content": "毎週の振り返りを組織で共有する。",
      "category": "研究方略",
      "when": "週次の活動を終えたとき",
      "what": "振り返りの要点",
      "why": "学びを再利用できるようにするため",
      "organizational_basis": "活動記録から抽出",
      "sort_order": 1,
      "created_at": "2026-10-08 10:00:00",
      "updated_at": "2026-10-08 10:00:00",
      "fragments": [
        {
          "source_type": "discussion",
          "source_id": "9",
          "title": "知のタイトル",
          "content": "KF本文",
          "selected_contents": "元の発言",
          "stage1": "振り返り",
          "stage2": "活動文脈",
          "stage3": "研究文脈",
          "user_id": "123",
          "discussed": "DONE"
        }
      ],
      "unresolved_fragment_links": [],
      "children": []
    }
  ]
}
```

`fragments` は `experience_knowledges` または `externalized_contents` の `deleted = 0` の参照先だけを含みます。議論KFの `title` は `raw_payload.title` から返します。`SRL` は現在参照先テーブルがないため、`unresolved_fragment_links` に `source_not_supported` として返します。experience/discussionの参照先が存在しないか削除済みの場合も、同配列に `source_not_found_or_deleted` として返します。

エラーは、未認証時 `401`、無効なgroup_idは `400`、非所属または削除済みグループは `403` です。

## ローカルSSOログイン導線（2026-10-08）

OKAgentは `http://localhost:8888/OKAgent/?group_id=100` を入口とする。同一ブラウザ・同一オリジンのOK-CoreセッションをAPIへ送る。活動システムのiframeは外部のHCI/OK-Coreを表示する既存設定であり、そのログインはローカルOK-Coreのログインを代替しない。

- 正規の開始URL: `/OK-Core/auth/login.php?mode=sso&return_to=` に、URLエンコードしたOKAgentの現在URLを指定する。
- ローカル設定での認可先: `https://kshci-lab.net/software/hcimlab_auth/oauth/authorize`。
- 登録済みコールバック: `http://localhost:8888/OK-Core/auth/callback`（`auth/callback/index.php` → `auth/callback.php`）。
- HCIMLab認証とOK-CoreのPHPセッションは別。ログイン開始時の `OKCORESESSID` は未認証セッションであり、Cookieの存在だけでAPIが200になるわけではない。
- コールバックはstate、有効時間、PKCE、署名・issuer・audience・nonce、UserInfoのsubを検証し、利用者をDBへ対応づけ、セッションIDを更新して `USERID` を設定する。このセッションでグループ所属を検証してAPIを返す。
- 401時、パネルに「OK-CoreにSSOログイン」と「再取得」を表示する。ログインはトップ画面で実施し、成功後は元の `group_id` と `activity` 付きURLへ戻る。ページ再読み込みによりAPIを再取得し、活動選択とパネルを復元する。403は所属権限エラーとして表示する。
- `return_to` は同一scheme/host/portの `/OKAgent/`、`/OKAgent/index.php`、`/OKAgent/dist/`、`/OKAgent/dist/index.php` のみ許可する。サーバーセッションへ保持し、成功時に再検証して一度だけ消費する。SSOの `redirect_uri` は変更しない。
- localhostと127.0.0.1、ポート違いを混在させない。開発用ログイン、Cookieのコピー、認証無効化をこの導線に使用しない。
- HCIM_AUTHのトークン認証方式は `client_secret_basic` と `client_secret_post` の両方をサポートする（OIDC discoveryで確認）。従ってトークン応答 `invalid_client` / `client authentication failed` はログインCookieやgroup権限ではなく、登録済みクライアントのID/secretまたは状態がIdP側設定と一致しないことを示す。`php/sso_local.php` またはローカル環境変数のクライアント登録情報を、HCIM_AUTH管理側のOK-Core登録と照合する。secretはチャットやログへ貼らない。secretを再発行した場合はローカル設定だけ更新し、再度SSOを開始する。

### 検証状況

- ローカルHTTP: 未認証APIは401。SSO開始は302で上記認可先へ遷移し、OKCORESESSID発行、登録callback、state、nonce、PKCE S256を確認。
- `tests/sso_return_test.php`: 戻り先検証、不正URL拒否、group/activity保持、一度だけの消費を確認。
- OKAgent: 20件のNodeテスト成功。401/403の区別、SSOリンク、活動選択後の戻り先更新、再取得、復帰時の活動・パネル表示を確認。ローカルdist更新済み。
- 認証済みブラウザのAPI 200と5ノード・KF表示は、ブラウザ接続・利用者の正規SSOログイン後に確認する必要がある。未確認のまま完了とは扱わない。
- AWSへの反映なし。
