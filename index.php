<?php
session_start();
require_once __DIR__ . '/php/connect_db.php';

if (!isset($_SESSION['USERID'])) {
    header('Location: login.php');
    exit;
}

if (isset($_POST['logout'])) {
    header('Location: logout.php');
    exit;
}

$username = isset($_SESSION['USERNAME']) ? (string)$_SESSION['USERNAME'] : 'User';
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OK-Core</title>
    <link rel="stylesheet" href="css/organizational-network.css">
    <link rel="stylesheet" href="css/organizational-map.css">
    <link rel="stylesheet" href="css/ok-core.css">
    <script src="js/jquery-1.8.2.min.js"></script>
    <script src="js/jquery-ui.min.js"></script>
    <script src="js/vis-network.min.js"></script>
</head>
<body class="ok-core-app">
    <header class="app-header">
        <div class="brand-block">
            <div class="app-title">OK-Core</div>
            <div class="app-subtitle">Knowledge Fragmentを組織知へ持ち上げるための整理・議論・連結化環境</div>
        </div>
        <div class="session-block">
            <span class="session-user"><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></span>
            <form method="post">
                <button type="submit" name="logout" class="secondary-button">ログアウト</button>
            </form>
        </div>
    </header>

    <main id="organizational_container" class="ok-main">
        <section class="toolbar-band">
            <div class="org-subtabs" role="tablist" aria-label="組織知マップ">
                <button type="button" id="org-tab-cooperation" class="org-subtab is-active" role="tab" aria-selected="true" aria-controls="org-tabpanel-cooperation">共同化</button>
                <button type="button" id="org-tab-combination" class="org-subtab" role="tab" aria-selected="false" aria-controls="org-tabpanel-combination">連結化</button>
            </div>
            <form id="group_select_form" class="group_select_form" action="javascript:void(0);">
                <label for="group_select" id="group_select_label">組織</label>
                <select id="group_select" name="group_select"></select>
            </form>
        </section>

        <section id="org-tabpanel-cooperation" class="org-subtab-panel is-active ok-panel" role="tabpanel" aria-labelledby="org-tab-cooperation">
            <div id="myOrganizationalnetwork_area" class="network-area">
                <div id="buttoncluster" class="network-toolbar">
                    <button type="button" class="Organizational_network_button" id="organizational_ZoomIn">拡大</button>
                    <button type="button" class="Organizational_network_button" id="organizational_ZoomOut">縮小</button>
                    <div class="filter-toolbar">
                        <div class="source-filter" data-filter-scope="cooperation" aria-label="KFの産出元フィルター">
                            <span class="filter-heading">産出元</span>
                            <button type="button" class="source-filter-btn is-active" data-source-filter="experience">experience</button>
                            <button type="button" class="source-filter-btn is-active" data-source-filter="discussion">discussion</button>
                        </div>
                        <div class="display-filter" data-filter-scope="cooperation" aria-label="組織知表示フィルター">
                            <span class="filter-heading">表示</span>
                            <button type="button" class="display-filter-btn" data-display-filter="hide-organizational-knowledge">組織知</button>
                        </div>
                    </div>
                </div>

                <div id="t_Organizational_conmenu">
                    <ul>
                        <li><a href="javascript:void(0);" id="organizational_conmenu6" style="display:none">編集する</a></li>
                        <li><a href="javascript:void(0);" id="organizational_conmenu5" style="display:none">削除する</a></li>
                        <li><a href="javascript:void(0);" id="organizational_conmenu4">キャンセル</a></li>
                    </ul>
                </div>

                <div id="myOrganizationalnetwork"></div>
            </div>

            <div id="process_others_network_container" class="related-process-pane" oncontextmenu="return false;">
                <div id="othersProcessnetwork_area">
                    <div id="buttoncluster" class="network-toolbar">
                        <button type="button" class="area_close" onclick="closeOthersThinkingProcessMap()" id="area_close">x</button>
                        <button type="button" class="thinkingProcess_network_button" id="process_ZoomIn">拡大</button>
                        <button type="button" class="thinkingProcess_network_button" id="process_ZoomOut">縮小</button>
                        <div id="others_conceptdisplay"></div>
                    </div>
                    <div id="othersProcessnetwork"></div>
                </div>
            </div>
        </section>

        <section id="org-tabpanel-combination" class="org-subtab-panel ok-panel" role="tabpanel" aria-labelledby="org-tab-combination" hidden>
            <div id="shared_combination_overlay">
                <div class="comb-row">
                    <div class="comb-left">
                        <div class="comb-left-top">
                            <div id="knowledge_fragments_workspace" class="knowledge_fragments_workspace">
                                <div class="overlay-title">
                                    KF一覧
                                    <div class="source-filter" data-filter-scope="fragments" aria-label="KF一覧フィルター">
                                        <span class="filter-heading">産出元</span>
                                        <button type="button" class="source-filter-btn is-active" data-source-filter="experience">experience</button>
                                        <button type="button" class="source-filter-btn is-active" data-source-filter="discussion">discussion</button>
                                    </div>
                                    <div class="kfrag-action-group">
                                        <button type="button" id="kfrag-reset" class="kfrag-action-btn">Reset</button>
                                        <button type="button" id="kfrag-undo" class="kfrag-action-btn" disabled>Undo</button>
                                        <button type="button" id="kfrag-redo" class="kfrag-action-btn" disabled>Redo</button>
                                    </div>
                                </div>
                                <?php include __DIR__ . '/php/get_knowledge_fragments.php'; ?>
                            </div>
                        </div>

                        <div class="overlay-input-area">
                            <div id="discussion_history_area" class="discussion_history_area">
                                <div class="overlay-title">
                                    <span class="title-text">議論履歴</span>
                                    <button type="button" id="fragment-discussed-toggle" class="fragment-discussed-btn">議論開始</button>
                                    <button type="button" id="fragment-add-select-toggle" class="fragment-add-select-btn" aria-label="追加のKFを選択">
                                        <span class="plus-icon" aria-hidden="true">+</span>
                                        <span class="tooltip">追加のKFを選択</span>
                                    </button>
                                </div>
                                <div id="fragment-selected-area" class="fragment-selected-area">
                                    <span class="selected-label">選択中のKF</span>
                                    <div id="fragment-selected-list" class="fragment-selected-list" aria-live="polite"></div>
                                </div>
                                <div class="discussion-board" id="discussion_board" data-user-name="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="message-list" id="discussion_message_list" aria-live="polite">
                                        <div class="discussion-placeholder">議論対象のKFを選択してください。</div>
                                    </div>
                                    <form id="discussion_post_form" class="post-form" action="javascript:void(0)" style="display:none;">
                                        <textarea id="discussion_input" class="post-input" rows="2" maxlength="255" placeholder="コメントを入力"></textarea>
                                        <button type="submit" class="post-button">投稿</button>
                                    </form>
                                </div>
                            </div>

                            <div id="knowledge_register_area" class="knowledge_register_area">
                                <div class="overlay-title">組織知登録</div>
                                <form id="knowledge_register_form" class="knowledge_register_form" onsubmit="return false;">
                                    <div class="kra-row kra-top">
                                        <label for="kra_area_select" class="kra-label">追加する領域</label>
                                        <select id="kra_area_select" class="kra-control">
                                            <option value="知識関連" selected>知識関連</option>
                                            <option value="研究方略関連">研究方略関連</option>
                                            <option value="その他">その他</option>
                                        </select>
                                    </div>
                                    <div class="kra-row kra-body">
                                        <div class="kra-section-title">知の内容</div>
                                        <label for="kra_knowledge_content" class="kra-label">要約</label>
                                        <textarea id="kra_knowledge_content" class="kra-control" rows="2" maxlength="255" placeholder="組織知として残したい知を一文で入力"></textarea>
                                    </div>
                                    <div class="kra-row">
                                        <label for="kra_tacto_when" class="kra-label">When</label>
                                        <textarea id="kra_tacto_when" class="kra-control kra-structured-control" rows="2"></textarea>
                                    </div>
                                    <div class="kra-row">
                                        <label for="kra_tacto_what" class="kra-label">What</label>
                                        <textarea id="kra_tacto_what" class="kra-control kra-structured-control" rows="2"></textarea>
                                    </div>
                                    <div class="kra-row">
                                        <label for="kra_tacto_why" class="kra-label">Why</label>
                                        <textarea id="kra_tacto_why" class="kra-control kra-structured-control" rows="2"></textarea>
                                    </div>
                                    <div class="kra-row">
                                        <label for="kra_organizational_basis" class="kra-label">組織知化の根拠</label>
                                        <textarea id="kra_organizational_basis" class="kra-control kra-structured-control" rows="3"></textarea>
                                    </div>
                                    <div class="kra-row kra-comment">
                                        <label for="kra_comment_input" class="kra-label">補足コメント</label>
                                        <textarea id="kra_comment_input" class="kra-control" rows="2"></textarea>
                                    </div>
                                    <div class="kra-actions">
                                        <button type="submit" class="button4-seci kra-submit" id="kra-submit">登録</button>
                                    </div>
                                </form>
                                <div id="knowledge_register_feedback" class="knowledge_register_feedback"></div>
                            </div>
                        </div>
                    </div>

                    <div class="comb-right">
                        <div class="overlay-knowledge-tree">
                            <div class="overlay-title overlay-title-with-action">
                                組織知ツリー
                                <div class="filter-toolbar">
                                    <div class="source-filter" data-filter-scope="knowledge-tree" aria-label="組織知ツリーフィルター">
                                        <span class="filter-heading">産出元</span>
                                        <button type="button" class="source-filter-btn is-active" data-source-filter="experience">experience</button>
                                        <button type="button" class="source-filter-btn is-active" data-source-filter="discussion">discussion</button>
                                    </div>
                                </div>
                                <button type="button" id="kt-add-node" class="kt-add-node-btn" aria-label="項目を追加">+</button>
                            </div>
                            <div id="overlay_knowledge_tree" class="overlay_knowledge_tree"></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <div id="trigger_display" class="compat-hidden"></div>
    <div id="lesson_display" class="compat-hidden"></div>
    <div id="ontology_feedback" class="compat-hidden"></div>
    <div id="labelselect" class="compat-hidden"></div>
    <div id="accordion_discussion" class="compat-hidden"></div>

    <script src="js/organizational-map.js"></script>
    <script src="js/organizational-combination-tab.js"></script>
</body>
</html>
