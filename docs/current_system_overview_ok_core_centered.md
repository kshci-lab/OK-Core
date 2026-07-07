# Forest-Core / OK-Core 迴ｾ陦後す繧ｹ繝・Β讒矩縺ｾ縺ｨ繧・
縺薙・譁・嶌縺ｯ縲・026-07-07 譎らせ縺ｮ `forest-platform` 縺ｨ `OK-Core` 縺ｮ讒矩繧偵＾K-Core 繧剃ｸｻ霆ｸ縺ｫ謨ｴ逅・＠縺溘ｂ縺ｮ縺ｧ縺吶・
譛ｬ遐皮ｩｶ縺ｫ蛻昴ａ縺ｦ隗ｦ繧後ｋ莠ｺ縺後∽ｻ･荳九ｒ逅・ｧ｣縺ｧ縺阪ｋ縺薙→繧堤岼讓吶↓縺励※縺・∪縺吶・
- OK-Core 縺御ｽ輔ｒ諡・ｽ薙☆繧九す繧ｹ繝・Β縺ｪ縺ｮ縺・- 縺ｩ縺ｮ繝輔ぃ繧､繝ｫ縺後←縺ｮ蜃ｦ逅・ｒ縺励※縺・ｋ縺ｮ縺・- 縺ｩ縺ｮ DB / 繝・・繝悶Ν縺ｫ縺､縺ｪ縺後▲縺ｦ縺・ｋ縺ｮ縺・- Forest-Core 縺九ｉ OK-Core 縺ｫ KF 縺後←縺・ｸ｡縺｣縺ｦ縺・ｋ縺ｮ縺・- 蟆・擂縲：orest-Core 莉･螟悶・繧ｷ繧ｹ繝・Β縺九ｉ KF 繧呈兜蜈･縺吶ｋ蝣ｴ蜷医↓菴輔ｒ螳医ｋ縺ｹ縺阪°

## 1. 蜈ｨ菴灘ワ

迴ｾ蝨ｨ縺ｮ讒区・縺ｯ縲∫皮ｩｶ豢ｻ蜍墓髪謠ｴ繧ｷ繧ｹ繝・Β縺ｧ縺ゅｋ Forest-Core 縺ｨ縲∫ｵ・ｹ皮衍蛹悶す繧ｹ繝・Β縺ｧ縺ゅｋ OK-Core 繧貞・髮｢縺励◆蠖｢縺ｫ縺ｪ縺｣縺ｦ縺・∪縺吶・
```text
Forest-Core
  蛟倶ｺｺ縺ｮ遐皮ｩｶ豢ｻ蜍輔ｒ謾ｯ謠ｴ縺吶ｋ
  蟄ｦ縺ｳ繧貞・蜉帙＠縲∫ｵ碁ｨ鍋罰譚･縺ｮ Knowledge Fragment・・F・峨ｒ逕｣蜃ｺ縺吶ｋ
  逕｣蜃ｺ縺励◆ KF 繧・OK-Core 縺ｫ貂｡縺・
OK-Core
  莉悶す繧ｹ繝・Β縺九ｉ貂｡縺輔ｌ縺・KF 繧定塘遨阪☆繧・  KF 繧堤ｵ・ｹ泌腰菴阪〒謨ｴ逅・・隴ｰ隲悶・騾｣邨舌☆繧・  KF 鄒､縺九ｉ邨・ｹ皮衍繧堤匳骭ｲ繝ｻ闢・ｩ阪☆繧・```

繝ｭ繝ｼ繧ｫ繝ｫ迺ｰ蠅・〒縺ｯ莉･荳九・ URL 縺ｧ蜍輔″縺ｾ縺吶・
```text
Forest-Core:
http://localhost:8888/forest-platform

OK-Core:
http://localhost:8888/OK-Core
```

SSO 縺ｮ redirect URI 縺ｯ縺昴ｌ縺槭ｌ蛻･縺ｧ縺吶・
```text
Forest-Core:
http://localhost:8888/forest-platform/auth/callback

OK-Core:
http://localhost:8888/OK-Core/auth/callback
```

荳｡閠・・蜷後§ SSO 蝓ｺ逶､繧剃ｽｿ縺・％縺ｨ縺ｧ縲∝酔荳繝ｦ繝ｼ繧ｶ繝ｼ縺ｧ縺ゅｋ縺薙→繧呈球菫昴＠縺ｾ縺吶ゅ◆縺縺励．B 縺ｨ URL 縺ｯ蛻・°繧後※縺・∪縺吶・
## 2. OK-Core 縺ｮ蠖ｹ蜑ｲ

OK-Core 縺ｯ縲∫ｵ・ｹ皮衍蛹悶・縺溘ａ縺ｮ迢ｬ遶・Web 繧｢繝励Μ繧ｱ繝ｼ繧ｷ繝ｧ繝ｳ縺ｧ縺吶・
OK-Core 縺梧桶縺・ｸｻ縺ｪ讎ょｿｵ縺ｯ谺｡縺ｮ騾壹ｊ縺ｧ縺吶・
| 讎ょｿｵ | 隱ｬ譏・|
| --- | --- |
| User | SSO 縺ｧ繝ｭ繧ｰ繧､繝ｳ縺吶ｋ蛻ｩ逕ｨ閠・Ａusers` 縺ｫ菫晏ｭ倥＆繧後ｋ縲・|
| Knowledge Group | 邨・ｹ斐・遐皮ｩｶ繧ｰ繝ｫ繝ｼ繝励Ａknowledge_groups` 縺ｫ菫晏ｭ倥＆繧後ｋ縲・|
| Group Membership | 繝ｦ繝ｼ繧ｶ繝ｼ縺後←縺ｮ邨・ｹ斐↓螻槭☆繧九°縲Ａkgroup_user_link` 縺ｫ菫晏ｭ倥＆繧後ｋ縲・|
| KF | 莉悶す繧ｹ繝・Β繧・ｭｰ隲悶°繧臥肇蜃ｺ縺輔ｌ縺溽衍隴俶妙迚・ゆｸｻ縺ｫ `experience_knowledges` 縺ｫ菫晏ｭ倥＆繧後ｋ縲・|
| Shared Node | KF 縺後←縺ｮ邨・ｹ斐↓蜈ｱ譛峨＆繧後◆縺九Ａshared_nodes` 縺ｫ菫晏ｭ倥＆繧後ｋ縲・|
| Discussion | KF 繧偵ｂ縺ｨ縺ｫ縺励◆隴ｰ隲門ｱ･豁ｴ縲Ａdiscussion_history` 縺ｫ菫晏ｭ倥＆繧後ｋ縲・|
| Organizational Knowledge | KF 鄒､縺九ｉ邨・ｹ皮衍縺ｨ縺励※逋ｻ骭ｲ縺励◆遏･縲Ａknowledge_explorer` 縺ｫ菫晏ｭ倥＆繧後ｋ縲・|
| KF Link | 邨・ｹ皮衍繝弱・繝峨→譬ｹ諡 KF 縺ｮ蟇ｾ蠢懊Ａknowledge_explorer_fragment_links` 縺ｫ菫晏ｭ倥＆繧後ｋ縲・|

## 3. OK-Core 縺ｮ荳ｻ隕√ヵ繧｡繧､繝ｫ讒矩

OK-Core 縺ｯ `C:\MAMP\htdocs\OK-Core` 縺ｫ鄂ｮ縺九ｌ縺ｦ縺・∪縺吶・
### 3.1 蜈･蜿｣繝ｻ逕ｻ髱｢

| 繝輔ぃ繧､繝ｫ | 蠖ｹ蜑ｲ |
| --- | --- |
| `index.php` | OK-Core 縺ｮ繝｡繧､繝ｳ逕ｻ髱｢縲ょ・蜷悟喧繧ｿ繝悶・｣邨仙喧繧ｿ繝悶゜F 荳隕ｧ縲∬ｭｰ隲門ｱ･豁ｴ縲∫ｵ・ｹ皮衍逋ｻ骭ｲ繝輔か繝ｼ繝繧呈ｧ区・縺吶ｋ縲・|
| `login.php` | 譛ｪ繝ｭ繧ｰ繧､繝ｳ譎ゅ・蜈･蜿｣縲４SO 繝ｭ繧ｰ繧､繝ｳ縺ｸ隱伜ｰ弱☆繧九・|
| `logout.php` | 繧ｻ繝・す繝ｧ繝ｳ繧堤ｵゆｺ・☆繧九・|

`index.php` 縺ｯ繝ｭ繧ｰ繧､繝ｳ貂医∩繧ｻ繝・す繝ｧ繝ｳ繧堤｢ｺ隱阪＠縲∵悴繝ｭ繧ｰ繧､繝ｳ縺ｪ繧・`login.php` 縺ｫ謌ｻ縺励∪縺吶ゅΟ繧ｰ繧､繝ｳ貂医∩縺ｮ蝣ｴ蜷医＾K-Core 縺ｮ邨・ｹ皮衍蛹也判髱｢繧定｡ｨ遉ｺ縺励∪縺吶・
荳ｻ縺ｪ逕ｻ髱｢鬆伜沺縺ｯ谺｡縺ｮ騾壹ｊ縺ｧ縺吶・
```text
index.php
  toolbar-band
    邨・ｹ秘∈謚・    蜈ｱ蜷悟喧 / 騾｣邨仙喧繧ｿ繝・
  org-tabpanel-cooperation
    邨・ｹ皮衍繝槭ャ繝・
  org-tabpanel-combination
    KF荳隕ｧ
    隴ｰ隲門ｱ･豁ｴ
    邨・ｹ皮衍逋ｻ骭ｲ繝輔か繝ｼ繝
    邨・ｹ皮衍繝・Μ繝ｼ
```

### 3.2 隱崎ｨｼ

| 繝輔ぃ繧､繝ｫ | 蠖ｹ蜑ｲ |
| --- | --- |
| `auth/login.php` | HCIMLab SSO 縺ｮ隱榊庄繧ｨ繝ｳ繝峨・繧､繝ｳ繝医∈繝ｪ繝繧､繝ｬ繧ｯ繝医☆繧九・|
| `auth/callback.php` | SSO 縺九ｉ謌ｻ縺｣縺ｦ縺阪◆隱榊庄繧ｳ繝ｼ繝峨ｒ蜃ｦ逅・＠縲√Θ繝ｼ繧ｶ繝ｼ繧・OK-Core DB 縺ｫ upsert 縺吶ｋ縲・|
| `auth/callback/index.php` | `/auth/callback/` 蠖｢蠑上・ URL 莠呈鋤逕ｨ蜈･蜿｣縲・|
| `php/hcimlab_sso.php` | OIDC / OAuth2 蜃ｦ逅・！D Token 讀懆ｨｼ縲√Θ繝ｼ繧ｶ繝ｼ upsert 縺ｮ蜈ｱ騾壼・逅・・|
| `php/sso_config.php` | SSO 險ｭ螳壹ｒ迺ｰ蠅・､画焚繝ｻlocal 險ｭ螳壹°繧臥ｵ・∩遶九※繧九・|
| `php/sso_local.php` | 繝ｭ繝ｼ繧ｫ繝ｫ遘伜ｯ・ｨｭ螳壹・itHub 蜈ｱ譛牙ｯｾ雎｡螟悶・|
| `php/sso_local.example.php` | 蜈ｱ譛臥畑縺ｮ險ｭ螳壻ｾ九らｧ伜ｯ・､縺ｯ蜈･繧後↑縺・・|

OK-Core 縺ｮ `php/sso_local.php` 縺ｯ OK-Core 迢ｬ閾ｪ縺ｮ Client ID / Secret 繧呈戟縺｡縺ｾ縺吶・orest-Core 縺ｮ險ｭ螳壹ヵ繧｡繧､繝ｫ縺ｯ蜿ら・縺励∪縺帙ｓ縲・
SSO 繝ｭ繧ｰ繧､繝ｳ謌仙粥譎ゅ・豬√ｌ縺ｯ谺｡縺ｮ騾壹ｊ縺ｧ縺吶・
```text
login.php
  -> auth/login.php
    -> HCIMLab SSO
      -> auth/callback.php
        -> hcimlab_sso_verify_id_token()
        -> hcimlab_sso_upsert_user()
        -> users 縺ｫ菫晏ｭ・/ 譖ｴ譁ｰ
        -> index.php 縺ｸ驕ｷ遘ｻ
```

### 3.3 DB 謗･邯・
| 繝輔ぃ繧､繝ｫ | 蠖ｹ蜑ｲ |
| --- | --- |
| `php/connect_db.php` | OK-Core DB 縺ｫ謗･邯壹＠縲～$mysqli` 繧呈署萓帙☆繧九・|
| `docs/ok_core_schema.sql` | OK-Core 蛻晄悄 DB 繧ｹ繧ｭ繝ｼ繝槭・|

OK-Core 縺ｮ譌｢螳・DB 縺ｯ `ok_core` 縺ｧ縺吶・
```text
host: localhost
port: 8889
user: root
password: root
database: ok_core
```

迺ｰ蠅・､画焚縺ｧ荳頑嶌縺阪〒縺阪∪縺吶・
```text
OK_CORE_DB_HOST
OK_CORE_DB_PORT
OK_CORE_DB_USER
OK_CORE_DB_PASSWORD
OK_CORE_DB_NAME
```

## 4. OK-Core 縺ｮ逕ｻ髱｢讖溯・縺ｨ繝輔ぃ繧､繝ｫ蟇ｾ蠢・
### 4.1 蜈ｱ蜷悟喧繧ｿ繝・
蜈ｱ蜷悟喧繧ｿ繝悶・縲∫ｵ・ｹ泌・縺ｮ繝ｦ繝ｼ繧ｶ繝ｼ繧・KF 繧偵ロ繝・ヨ繝ｯ繝ｼ繧ｯ縺ｨ縺励※陦ｨ遉ｺ縺吶ｋ鬆伜沺縺ｧ縺吶・
| 繝輔ぃ繧､繝ｫ | 蠖ｹ蜑ｲ |
| --- | --- |
| `js/organizational-map.js` | 邨・ｹ皮衍繝槭ャ繝励・陦ｨ遉ｺ縲√ヮ繝ｼ繝画緒逕ｻ縲∫ｷｨ髮・∝炎髯､縲√ヵ繧｣繝ｫ繧ｿ謫堺ｽ懊ｒ諡・ｽ薙☆繧九・|
| `php/organizational_map_manager.php` | 邨・ｹ斐∵園螻槭Θ繝ｼ繧ｶ繝ｼ縲∝・譛画ｸ医∩ KF縲∫ｵ・ｹ皮衍繝弱・繝峨ｒ DB 縺九ｉ蜿門ｾ励☆繧九・|
| `php/organizational_edit_map_maneger.php` | 邨・ｹ皮衍繝槭ャ繝嶺ｸ翫・繝弱・繝峨・繧ｨ繝・ず謫堺ｽ懊ｒ菫晏ｭ倥☆繧九・|
| `php/delete_experience_knowledge.php` | KF 繧定ｫ也炊蜑企勁縺吶ｋ縲・|
| `php/update_experience_knowledge.php` | KF 縺ｮ蜀・ｮｹ繧呈峩譁ｰ縺吶ｋ縲・|

荳ｻ縺ｫ蜿ら・縺吶ｋ DB 繝・・繝悶Ν:

```text
users
knowledge_groups
kgroup_user_link
experience_knowledges
shared_nodes
externalized_contents
knowledge_explorer
```

### 4.2 騾｣邨仙喧繧ｿ繝・
騾｣邨仙喧繧ｿ繝悶・縲゜F 繧剃ｸｦ縺ｹ縲∬ｭｰ隲悶＠縲∫ｵ・ｹ皮衍縺ｨ縺励※逋ｻ骭ｲ縺吶ｋ縺溘ａ縺ｮ菴懈･ｭ鬆伜沺縺ｧ縺吶・
| 繝輔ぃ繧､繝ｫ | 蠖ｹ蜑ｲ |
| --- | --- |
| `js/organizational-combination-tab.js` | KF 荳隕ｧ縲∬ｭｰ隲悶∫ｵ・ｹ皮衍逋ｻ骭ｲ縲√ヤ繝ｪ繝ｼ謫堺ｽ懊・・鄂ｮ菫晏ｭ倥↑縺ｩ繧呈球蠖薙☆繧九・|
| `php/get_knowledge_fragments.php` | 蛻晄悄陦ｨ遉ｺ逕ｨ縺ｮ KF 荳隕ｧ HTML 繧堤函謌舌☆繧九・|
| `php/get_knowledge_fragments_by_group.php` | 驕ｸ謚樒ｵ・ｹ斐↓蠢懊§縺・KF 荳隕ｧ繧堤函謌舌☆繧九・|
| `php/get_knowledge_fragment_detail.php` | KF 縺ｾ縺溘・邨・ｹ皮衍繝弱・繝峨↓邏舌▼縺剰ｩｳ邏ｰ繧貞叙蠕励☆繧九・|
| `php/save_fragment_order.php` | KF 縺ｮ逕ｻ髱｢荳翫・荳ｦ縺ｳ繝ｻ驟咲ｽｮ繧剃ｿ晏ｭ倥☆繧九・|
| `php/get_underway_kfrag_relations.php` | 隴ｰ隲紋ｸｭ KF 縺ｮ髢｢菫ゅｒ蠕ｩ蜈・☆繧九・|
| `php/save_discussion_history.php` | 隴ｰ隲悶さ繝｡繝ｳ繝医ｒ菫晏ｭ倥☆繧九・|
| `php/get_discussion_history.php` | 隴ｰ隲門ｱ･豁ｴ繧貞叙蠕励☆繧九・|
| `php/get_discussion_targets.php` | 隴ｰ隲門ｯｾ雎｡ KF 縺ｮ邨・∩蜷医ｏ縺帙ｒ蠕ｩ蜈・☆繧九・|
| `php/update_discussed_status.php` | KF 縺ｮ隴ｰ隲也憾諷九ｒ `YET` / `UNDERWAY` / `DONE` 縺ｫ譖ｴ譁ｰ縺吶ｋ縲・|
| `php/get_knowledge_tree.php` | 邨・ｹ皮衍繝・Μ繝ｼ繧貞叙蠕励☆繧九・|
| `php/insert_knowledge_node.php` | KF 鄒､縺九ｉ邨・ｹ皮衍繝弱・繝峨ｒ逋ｻ骭ｲ縺吶ｋ縲・|
| `php/update_knowledge_node.php` | 邨・ｹ皮衍繝弱・繝峨ｒ譖ｴ譁ｰ縺吶ｋ縲・|
| `php/mark_delete_knowledge_node.php` | 邨・ｹ皮衍繝弱・繝峨ｒ隲也炊蜑企勁縺吶ｋ縲・|
| `php/reorder_root_nodes.php` | 邨・ｹ皮衍繝・Μ繝ｼ縺ｮ繝ｫ繝ｼ繝磯・ｒ菫晏ｭ倥☆繧九・|
| `php/update_node_title.php` | 邨・ｹ皮衍繝弱・繝峨・繧ｿ繧､繝医Ν繧呈峩譁ｰ縺吶ｋ縲・|

荳ｻ縺ｫ蜿ら・繝ｻ譖ｴ譁ｰ縺吶ｋ DB 繝・・繝悶Ν:

```text
experience_knowledges
externalized_contents
shared_nodes
discussion_history
knowledge_fragment_positions
knowledge_explorer
knowledge_explorer_fragment_links
users
knowledge_groups
kgroup_user_link
```

## 5. OK-Core 縺ｮ DB 繝・・繝悶Ν

OK-Core 縺ｮ荳ｭ蠢・DB 縺ｯ `ok_core` 縺ｧ縺吶・
| 繝・・繝悶Ν | 蠖ｹ蜑ｲ |
| --- | --- |
| `users` | SSO 繝ｭ繧ｰ繧､繝ｳ繝ｦ繝ｼ繧ｶ繝ｼ縲４SO 縺ｮ `sub` 縺ｨ local `user_id` 繧貞ｯｾ蠢懊＆縺帙ｋ縲・|
| `knowledge_groups` | 邨・ｹ斐・遐皮ｩｶ繧ｰ繝ｫ繝ｼ繝励・|
| `kgroup_user_link` | 繝ｦ繝ｼ繧ｶ繝ｼ縺ｨ邨・ｹ斐・謇螻樣未菫ゅ・|
| `experience_knowledges` | 邨碁ｨ鍋罰譚･ KF縲・orest-Core 繧・ｻ悶す繧ｹ繝・Β縺九ｉ謚募・縺輔ｌ繧倶ｸｻ蟇ｾ雎｡縲・|
| `externalized_contents` | 隴ｰ隲悶・螟門喧逕ｱ譚･ KF 逕ｨ縺ｮ莠呈鋤繝・・繝悶Ν縲・|
| `shared_nodes` | KF 縺ｨ邨・ｹ斐・蜈ｱ譛蛾未菫ゅ・|
| `knowledge_fragment_positions` | KF 荳隕ｧ荳翫・驟咲ｽｮ繝ｻ荳ｦ縺ｳ縲・|
| `discussion_history` | KF 縺ｫ蟇ｾ縺吶ｋ隴ｰ隲門ｱ･豁ｴ縲・|
| `knowledge_explorer` | 邨・ｹ皮衍繝・Μ繝ｼ縺ｮ繝弱・繝峨らｵ・ｹ皮衍縺昴・繧ゅ・繧剃ｿ晏ｭ倥☆繧九・|
| `knowledge_explorer_fragment_links` | 邨・ｹ皮衍繝弱・繝峨→譬ｹ諡 KF 縺ｮ蟇ｾ蠢憺未菫ゅ・|

## 6. Forest-Core 縺九ｉ OK-Core 縺ｸ縺ｮ迴ｾ蝨ｨ縺ｮ KF 騾｣謳ｺ

Forest-Core 蛛ｴ縺ｧ縺ｯ縲√Θ繝ｼ繧ｶ繝ｼ縺後悟ｭｦ縺ｳ繧貞・蜉帙阪＠縲∫ｵ・ｹ斐∈蜈ｱ譛峨☆繧九→ KF 縺・OK-Core 縺ｫ騾√ｉ繧後∪縺吶・
荳ｻ縺ｪ繝輔ぃ繧､繝ｫ縺ｯ Forest-Core 蛛ｴ縺ｫ縺ゅｊ縺ｾ縺吶・
| 繝輔ぃ繧､繝ｫ | 蠖ｹ蜑ｲ |
| --- | --- |
| `js/thinking-process-network.js` | 蟄ｦ縺ｳ蜈･蜉帙ヵ繧ｩ繝ｼ繝縺ｮ蛟､繧帝寔繧√∝・譛牙・邨・ｹ斐ｒ OK-Core 縺九ｉ蜿門ｾ励＠縲∝・譛・API 繧貞他縺ｶ縲・|
| `php/ok_core_groups.php` | OK-Core DB 縺九ｉ縲√Ο繧ｰ繧､繝ｳ繝ｦ繝ｼ繧ｶ繝ｼ縺梧園螻槭☆繧狗ｵ・ｹ比ｸ隕ｧ繧貞叙蠕励☆繧九・|
| `php/thinking_edit_processmap_maneger.php` | Forest-Core 蛛ｴ縺ｮ諤晁・℃遞玖ｨ倬鹸 API縲Ａpurpose=share_fragment` 縺ｧ KF 繧剃ｽ懈・縺励＾K-Core 騾｣謳ｺ繧貞他縺ｶ縲・|
| `php/ok_core_bridge.php` | OK-Core DB 縺ｫ謗･邯壹＠縲√Θ繝ｼ繧ｶ繝ｼ繝ｻ謇螻槭・KF繝ｻ蜈ｱ譛峨Μ繝ｳ繧ｯ繧・upsert / insert 縺吶ｋ證ｫ螳壹ヶ繝ｪ繝・ず縲・|

迴ｾ蝨ｨ縺ｮ蜃ｦ逅・・豬√ｌ:

```text
Forest-Core 逕ｻ髱｢
  -> 蟄ｦ縺ｳ繧貞・蜉・  -> 邨・ｹ斐∈蜈ｱ譛・  -> js/thinking-process-network.js
     -> php/ok_core_groups.php
        -> OK-Core DB 縺九ｉ謇螻樒ｵ・ｹ斐ｒ蜿門ｾ・     -> php/thinking_edit_processmap_maneger.php?purpose=share_fragment
        -> Forest-Core DB 縺ｮ experience_knowledges 縺ｫ荳蠎ｦ菫晏ｭ・        -> Forest-Core DB 縺ｮ shared_nodes 縺ｫ荳蠎ｦ菫晏ｭ・        -> php/ok_core_bridge.php
           -> OK-Core DB users 繧堤｢ｺ隱・/ 菴懈・
           -> OK-Core DB kgroup_user_link 繧堤｢ｺ隱・/ 菴懈・
           -> OK-Core DB experience_knowledges 縺ｫ KF 繧剃ｿ晏ｭ・           -> OK-Core DB shared_nodes 縺ｫ邨・ｹ泌・譛峨Μ繝ｳ繧ｯ繧剃ｿ晏ｭ・```

螳滄圀縺ｫ遒ｺ隱肴ｸ医∩縺ｮ謌仙粥繝ｬ繧ｹ繝昴Φ繧ｹ萓・

```json
{
  "status": "ok",
  "experience_knowledge_id": 11159,
  "ok_core": {
    "status": "ok",
    "ok_core_experience_knowledge_id": 11159
  }
}
```

縺薙・譎らせ縺ｧ縲＾K-Core 蛛ｴ縺ｮ `experience_knowledges` 縺ｫ KF 縺御ｿ晏ｭ倥＆繧後＾K-Core 縺ｮ KF 荳隕ｧ縺ｫ繧り｡ｨ遉ｺ縺輔ｌ縺ｾ縺吶・
## 7. 迴ｾ蝨ｨ縺ｮ騾｣謳ｺ譁ｹ蠑上・菴咲ｽｮ縺･縺・
迴ｾ蝨ｨ縺ｮ Forest-Core -> OK-Core 騾｣謳ｺ縺ｯ縲∵圻螳夂噪縺ｪ逶ｴ謗･ DB 繝悶Μ繝・ず縺ｧ縺吶・
```text
Forest-Core PHP
  -> OK-Core DB 縺ｫ逶ｴ謗･謗･邯・```

縺薙ｌ縺ｯ繝ｭ繝ｼ繧ｫ繝ｫ蛻・屬繝ｻ蛻晄悄讀懆ｨｼ縺ｨ縺励※縺ｯ譛牙柑縺ｧ縺吶′縲∝ｰ・擂縺ｮ迢ｬ遶九す繧ｹ繝・Β騾｣謳ｺ縺ｧ縺ｯ莉･荳九・逅・罰縺ｧ API 蛹悶′譛帙∪縺励＞縺ｧ縺吶・
- 莉悶す繧ｹ繝・Β縺・OK-Core DB 縺ｮ蜀・Κ讒矩繧堤峩謗･遏･繧峨↑縺上※繧医＞
- OK-Core 蛛ｴ縺ｧ蜈･蜉帶､懆ｨｼ繝ｻ讓ｩ髯千｢ｺ隱阪・逶｣譟ｻ繝ｭ繧ｰ繧剃ｸ蜈・喧縺ｧ縺阪ｋ
- DB 繧ｹ繧ｭ繝ｼ繝槫､画峩譎ゅ↓螟夜Κ繧ｷ繧ｹ繝・Β縺ｸ縺ｮ蠖ｱ髻ｿ繧貞ｰ上＆縺上〒縺阪ｋ
- 蟆・擂縲∝挨繧ｵ繝ｼ繝舌・蛻･繝峨Γ繧､繝ｳ縺ｫ縺ｪ縺｣縺ｦ繧る｣謳ｺ縺ｧ縺阪ｋ

縺励◆縺後▲縺ｦ縲∽ｻ雁ｾ後・譁ｹ驥昴・谺｡縺ｮ騾壹ｊ縺ｧ縺吶・
```text
遏ｭ譛・
  Forest-Core 縺ｮ逶ｴ謗･ DB 繝悶Μ繝・ず縺ｧ驕狗畑繝ｻ讀懆ｨｼ

荳ｭ譛・
  OK-Core 縺ｫ KF import API 繧剃ｽ懊ｋ

髟ｷ譛・
  Forest-Core / 蛻･繧ｷ繧ｹ繝・Β / 莉悶い繝励Μ
    -> OK-Core import API
      -> OK-Core DB
      -> OK-Core UI 縺ｧ謨ｴ逅・・隴ｰ隲悶・邨・ｹ皮衍蛹・```

## 8. 莉悶す繧ｹ繝・Β縺九ｉ KF 繧呈兜蜈･縺吶ｋ蝣ｴ蜷医・閠・∴譁ｹ

莉悶す繧ｹ繝・Β縺九ｉ OK-Core 縺ｫ KF 繧呈兜蜈･縺吶ｋ蝣ｴ蜷医＾K-Core 蛛ｴ縺ｧ縺ｯ蟆代↑縺上→繧ゆｻ･荳九・諠・ｱ縺悟ｿ・ｦ√〒縺吶・
```json
{
  "source_system": "Forest-Core",
  "source_type": "experience",
  "source_id": "source-local-id",
  "user_sso_sub": "stable-sso-subject",
  "user_id": 123,
  "group_id": 100,
  "selected_contents": "KF縺ｮ譬ｹ諡縺ｨ縺ｪ縺｣縺溷・蜀・ｮｹ",
  "knowledge_fragment_content": "KF譛ｬ譁・,
  "stage1": "邨碁ｨ薙・迥ｶ豕√・隕ｳ蟇・,
  "stage2": "隗｣驥医・豌励▼縺・,
  "stage3": "莉雁ｾ後・陦悟虚繝ｻ譁ｹ驥・
}
```

縺溘□縺励∝ｰ・擂 API 蛹悶☆繧句ｴ蜷医・縲～user_id` 繧医ｊ繧・`user_sso_sub` 繧剃ｸｻ繧ｭ繝ｼ逧・↓謇ｱ縺・婿縺悟ｮ牙・縺ｧ縺吶ょ推繧ｷ繧ｹ繝・Β蜀・・ local user id 縺ｯ荳閾ｴ縺励↑縺・庄閭ｽ諤ｧ縺後≠繧九◆繧√〒縺吶・
謗ｨ螂ｨ縺輔ｌ繧句､夜Κ謚募・ API 縺ｮ雋ｬ蜍・

```text
POST /api/kf/import

OK-Core 蛛ｴ縺ｧ陦後≧縺薙→:
  1. 隱崎ｨｼ繝医・繧ｯ繝ｳ繧呈､懆ｨｼ縺吶ｋ
  2. user_sso_sub 縺九ｉ OK-Core users 繧定ｧ｣豎ｺ縺吶ｋ
  3. group_id 縺ｸ縺ｮ謇螻槭・蜈ｱ譛画ｨｩ髯舌ｒ遒ｺ隱阪☆繧・  4. source_system + source_type + source_id 縺ｮ驥崎､・ｒ遒ｺ隱阪☆繧・  5. experience_knowledges 縺ｫ KF 繧剃ｿ晏ｭ倥☆繧・  6. shared_nodes 縺ｫ group 縺ｨ縺ｮ蜈ｱ譛蛾未菫ゅｒ菫晏ｭ倥☆繧・  7. import audit log 縺ｫ謌仙粥繝ｻ螟ｱ謨励ｒ險倬鹸縺吶ｋ
```

## 9. 螟夜Κ繧ｷ繧ｹ繝・Β髢狗匱譎ゅ↓螳医ｋ縺ｹ縺阪％縺ｨ

蛻･繧ｷ繧ｹ繝・Β縺ｧ KF 逕｣蜃ｺ讖溯・繧剃ｽ懊ｋ蝣ｴ蜷医・縲∵ｬ｡繧貞ｮ医ｋ縺ｨ OK-Core 縺ｨ謗･邯壹＠繧・☆縺上↑繧翫∪縺吶・
1. KF 縺ｮ譛ｬ譁・ｒ `knowledge_fragment_content` 縺ｨ縺励※貂｡縺帙ｋ繧医≧縺ｫ縺吶ｋ縲・2. KF 縺ｮ蜈・↓縺ｪ縺｣縺溷・螳ｹ繧・`selected_contents` 縺ｨ縺励※谿九☆縲・3. 蜿ｯ閭ｽ縺ｪ繧・`stage1` / `stage2` / `stage3` 縺ｮ繧医≧縺ｪ蜀・怐讒矩繧呈戟縺溘○繧九・4. 繝ｦ繝ｼ繧ｶ繝ｼ蜷御ｸ諤ｧ縺ｯ local id 縺ｧ縺ｯ縺ｪ縺・SSO subject 繧貞渕貅悶↓縺吶ｋ縲・5. 縺ｩ縺ｮ邨・ｹ斐↓蜈ｱ譛峨☆繧九°繧・`group_id` 縺ｨ縺励※譏守､ｺ縺吶ｋ縲・6. 蜷後§ KF 繧剃ｺ碁㍾謚募・縺励↑縺・◆繧√↓縲～source_system` + `source_type` + `source_id` 繧呈戟縺､縲・7. OK-Core DB 繧堤峩謗･譖ｴ譁ｰ縺帙★縲∝ｰ・擂逧・↓縺ｯ OK-Core import API 繧貞他縺ｶ縲・
## 10. 迴ｾ譎らせ縺ｧ謌舌ｊ遶九▲縺ｦ縺・ｋ讖溯・

迴ｾ蝨ｨ縲∽ｻ･荳九・蜍穂ｽ懃｢ｺ隱肴ｸ医∩縺ｧ縺吶・
- Forest-Core 縺ｫ SSO 繝ｭ繧ｰ繧､繝ｳ縺ｧ縺阪ｋ縲・- OK-Core 縺ｫ SSO 繝ｭ繧ｰ繧､繝ｳ縺ｧ縺阪ｋ縲・- Forest-Core 縺ｮ縲悟ｭｦ縺ｳ繧貞・蜉帙阪°繧・KF 繧剃ｽ懊ｌ繧九・- Forest-Core 縺九ｉ邨・ｹ斐ｒ驕ｸ謚槭＠縺ｦ KF 繧貞・譛峨〒縺阪ｋ縲・- 蜈ｱ譛峨＆繧後◆ KF 縺・OK-Core DB 縺ｮ `experience_knowledges` 縺ｫ菫晏ｭ倥＆繧後ｋ縲・- OK-Core 縺ｮ KF 荳隕ｧ縺ｫ蜈ｱ譛画ｸ医∩ KF 縺瑚｡ｨ遉ｺ縺輔ｌ繧九・- OK-Core 荳翫〒 KF 繧偵ｂ縺ｨ縺ｫ隴ｰ隲悶・騾｣邨仙喧繝ｻ邨・ｹ皮衍逋ｻ骭ｲ繧定｡後≧蝓ｺ逶､縺後≠繧九・
## 11. 迴ｾ譎らせ縺ｮ豕ｨ諢冗せ

迴ｾ譎らせ縺ｧ縺ｯ縲：orest-Core 縺ｮ蜈ｱ譛牙・逅・・縺ｾ縺 Forest-Core DB 縺ｮ `experience_knowledges` 縺ｨ `shared_nodes` 縺ｫ繧よ嶌縺崎ｾｼ縺ｿ縺ｾ縺吶・
縺昴・縺溘ａ縲：orest-Core DB 縺九ｉ縺薙・2繝・・繝悶Ν繧貞炎髯､縺吶ｋ縺ｨ縲∫樟蝨ｨ縺ｮ縲悟ｭｦ縺ｳ繧貞・蜉・-> 蜈ｱ譛峨榊・逅・・螢翫ｌ縺ｾ縺吶・
```text
Forest-Core 蛛ｴ縺ｧ莉翫☆縺仙炎髯､縺励↑縺・
  experience_knowledges
  shared_nodes
```

荳譁ｹ縲∵立 OK-Core UI 蟆ら畑縺ｮ莉･荳九・ Forest-Core 蛛ｴ繝・・繝悶Ν縺ｯ縲√Μ繝阪・繝縺ｫ繧医ｋ騾驕ｿ蠕後↓蜑企勁蛟呵｣懊〒縺吶・
```text
discussion_history
externalized_contents
knowledge_explorer
knowledge_explorer_fragment_links
knowledge_fragment_positions
```

隧ｳ邏ｰ縺ｯ `docs/forest_db_table_cleanup.md` 繧貞盾辣ｧ縺励※縺上□縺輔＞縲・
## 12. 莉雁ｾ後・謗ｨ螂ｨ髢狗匱

谺｡縺ｮ髢狗匱繧ｹ繝・ャ繝励・縲＾K-Core 繧貞､夜Κ繧ｷ繧ｹ繝・Β騾｣謳ｺ縺ｮ蜿励￠蜿｣縺ｨ縺励※蝗ｺ繧√ｋ縺薙→縺ｧ縺吶・
蜆ｪ蜈亥ｺｦ鬆・

1. OK-Core 縺ｫ `source_system`, `source_type`, `source_id`, `source_user_ref` 繧剃ｿ晏ｭ倥〒縺阪ｋ import 邂｡逅・ユ繝ｼ繝悶Ν繧定ｿｽ蜉縺吶ｋ縲・2. OK-Core 縺ｫ `POST /api/kf/import` 逶ｸ蠖薙・ API 繧定ｿｽ蜉縺吶ｋ縲・3. Forest-Core 縺ｮ `ok_core_bridge.php` 繧偵∫峩謗･ DB 譖ｸ縺崎ｾｼ縺ｿ縺九ｉ API 蜻ｼ縺ｳ蜃ｺ縺励↓鄂ｮ縺肴鋤縺医ｋ縲・4. import 縺ｮ謌仙粥繝ｻ螟ｱ謨励・蜀埼∫憾諷九ｒ逶｣譟ｻ縺ｧ縺阪ｋ繧医≧縺ｫ縺吶ｋ縲・5. OK-Core 蛛ｴ縺ｧ縲∫ｵ・ｹ斐＃縺ｨ縺ｮ KF 蜿励￠蜈･繧梧ｨｩ髯舌ｒ譏守､ｺ縺吶ｋ縲・
縺薙・蠖｢縺ｫ縺吶ｋ縺ｨ縲：orest-Core 莉･螟悶・繧ｷ繧ｹ繝・Β繧ゅ＾K-Core 縺ｮ DB 讒矩繧堤峩謗･遏･繧峨★縺ｫ KF 繧呈兜蜈･縺ｧ縺阪ｋ繧医≧縺ｫ縺ｪ繧翫∪縺吶・
