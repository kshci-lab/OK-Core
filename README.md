# OK-Core

OK-Core is the organizational knowledge application separated from Forest-Core.

## Local setup

1. Create the database from `docs/ok_core_schema.sql` in phpMyAdmin.
2. Run the local setup script. It restores Git-ignored local files and dependencies:
   - `vendor/` through Composer
   - `certs/cacert.pem`
   - `php/sso_local.php`

   Windows PowerShell:
   ```powershell
   .\scripts\setup-local.ps1 -Force -ClientId '019f3aed-16c3-70c1-a360-e32d65705906' -ClientSecret '<OK-Core client secret>'
   ```

   macOS/Linux:
   ```sh
   HCIMLAB_SSO_CLIENT_ID='019f3aed-16c3-70c1-a360-e32d65705906' \
   HCIMLAB_SSO_CLIENT_SECRET='<OK-Core client secret>' \
   FORCE=1 sh scripts/setup-local.sh
   ```

   You can also set `PHP_BIN` or `COMPOSER_BIN` when PHP/Composer is not on PATH.
3. Confirm `php/connect_db.php` points to the OK-Core database. By default it uses:
   - host: `localhost`
   - port: `8889`
   - user: `root`
   - password: `root`
   - database: `ok_core`
4. Open `/OK-Core/login.php`.

The DB connection can also be overridden with:

- `OK_CORE_DB_HOST`
- `OK_CORE_DB_PORT`
- `OK_CORE_DB_USER`
- `OK_CORE_DB_PASSWORD`
- `OK_CORE_DB_NAME`

## Current scope

This first separation step provides the OK-Core web application shell, SSO login, organizational map, KF discussion workspace, and organizational knowledge registration tree.

The current API keeps compatibility with the existing table names (`experience_knowledges`, `externalized_contents`, `knowledge_explorer`) so the UI can be split before the full Forest-Core to OK-Core KF export pipeline is redesigned.

## Forest-Core KF import bridge

Forest-Core now reads shareable organization groups from OK-Core and imports finalized experience KFs into the OK-Core DB when the user shares from "蟄ｦ縺ｳ繧貞・蜉・.

The temporary bridge is implemented in:

- `Forest-Core/php/ok_core_bridge.php`
- `Forest-Core/php/ok_core_groups.php`
- `Forest-Core/php/thinking_edit_processmap_maneger.php`
- `Forest-Core/js/thinking-process-network.js`

It uses the same `OK_CORE_DB_*` environment variables as OK-Core. If those variables are not set, it connects to `localhost:8889`, user `root`, password `root`, database `ok_core`.

## Next DB/data step

After the schema is created, migrate or import:

- users matched by SSO (`users`)
- organizations (`knowledge_groups`, `kgroup_user_link`)
- pooled KFs (`experience_knowledges`, `externalized_contents`)
- group sharing links for experience KFs (`shared_nodes`)

Forest-Core should eventually write produced KFs to OK-Core through a small import API instead of sharing one database.

If `ok_core.shared_nodes` was already created before the unique key was added to the schema, run this once in phpMyAdmin:

```sql
SELECT experience_knowledge_id, knowledge_group_id, COUNT(*) AS duplicate_count
FROM ok_core.shared_nodes
WHERE experience_knowledge_id IS NOT NULL
GROUP BY experience_knowledge_id, knowledge_group_id
HAVING COUNT(*) > 1;

DELETE sn
FROM ok_core.shared_nodes sn
JOIN ok_core.shared_nodes keep
  ON keep.experience_knowledge_id = sn.experience_knowledge_id
 AND keep.knowledge_group_id = sn.knowledge_group_id
 AND keep.id < sn.id
WHERE sn.experience_knowledge_id IS NOT NULL;

ALTER TABLE ok_core.shared_nodes
  ADD UNIQUE KEY ux_shared_nodes_experience_group (experience_knowledge_id, knowledge_group_id);
```

The `DELETE` keeps the lowest `id` for each duplicate `(experience_knowledge_id, knowledge_group_id)` pair and removes the later duplicates.

## SSO redirect URIs

For local development, register these redirect URIs in HCIMLab SSO:

- Forest-Core: `http://localhost:8888/forest-platform/auth/callback`
- OK-Core: `http://localhost:8888/OK-Core/auth/callback`

Forest-Core and OK-Core are separate projects. Each project must register its own redirect URI and keep its own `php/sso_local.php`.

For deployment, set the following environment variables instead of relying on the request Host header:

- `APP_ENV=production`
- `HCIMLAB_SSO_BASE_URL=https://ok.example.com`
- `HCIMLAB_SSO_REDIRECT_URI=https://ok.example.com/auth/callback`
- `HCIMLAB_SSO_CLIENT_ID=...`
- `HCIMLAB_SSO_CLIENT_SECRET=...`
- `HCIMLAB_SSO_DEV_AUTH=false`

The development login and `/php/sso_debug.php` are disabled when `APP_ENV=production`.
## Verified separation status

The current local separation path has been verified:

- Forest-Core imports a shared experience KF into `ok_core.experience_knowledges`.
- OK-Core displays the imported KF in the KF list.
- Forest-Core redirect URI: `http://localhost:8888/forest-platform/auth/callback`
- OK-Core redirect URI: `http://localhost:8888/OK-Core/auth/callback`

Remaining hardening:

- Replace the temporary direct DB bridge with an authenticated OK-Core import API before deploying Forest-Core and OK-Core to separate servers.
- Add import audit/retry records for Forest-Core to OK-Core KF exports.
- Keep each project's `php/sso_local.php`, `php/connect_db.php`, URL, and database independent.

