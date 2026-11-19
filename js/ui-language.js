(function () {
    'use strict';

    const english = {
        'Knowledge Fragmentを組織知へ持ち上げるための整理・議論・連結化環境': 'Organize, discuss, and combine knowledge fragments to build organizational knowledge.',
        'Knowledge Fragment を扱うための入口です。': 'Sign in to work with knowledge fragments.',
        'SSOログインに失敗しました。': 'Single sign-on failed.',
        'ひとまず開発用ログインに切り替えて続けられます。': 'You can continue using the development login.',
        'SSOログイン': 'Sign in with SSO',
        '開発用ログイン': 'Development login',
        '開発用ログインに切り替える': 'Switch to development login',
        '現在の戻り先:': 'Return URL:',
        'ログインセッションは確認できていますが、データベースへの接続に失敗しました。': 'You are signed in, but OK-Core could not connect to the database.',
        'ログアウト': 'Sign out',
        '言語': 'Language',
        '組織知マップ': 'Organizational knowledge map',
        '共同化': 'Socialization',
        '連結化': 'Combination',
        '組織': 'Organization',
        '拡大': 'Zoom in',
        '縮小': 'Zoom out',
        '全体表示': 'Fit to view',
        'KFの産出元フィルター': 'KF source filter',
        '組織知表示フィルター': 'Organizational knowledge visibility filter',
        '産出元': 'Source',
        '表示': 'Show',
        '組織知': 'Organizational knowledge',
        '思考過程表出化マップを表示': 'View thinking process map',
        '思考過程表出化マップ': 'Thinking process map',
        '思考過程マップを閉じる': 'Close thinking process map',
        '編集する': 'Edit',
        '削除する': 'Delete',
        'キャンセル': 'Cancel',
        'KF一覧': 'Knowledge fragments',
        'KF一覧フィルター': 'Knowledge fragment filters',
        'KFを読み込み中…': 'Loading knowledge fragments…',
        'KFを読み込めませんでした。': 'Could not load knowledge fragments.',
        'ー組織を選択ー': 'Select an organization',
        '表示できる組織がありません。': 'No organizations to display.',
        'KF {count}件': 'Knowledge fragments: {count}',
        '議論履歴': 'Discussion history',
        '議論開始': 'Start discussion',
        '議論中': 'In discussion',
        '議論完了': 'Discussion complete',
        '議論履歴を更新': 'Refresh discussion history',
        '追加のKFを選択': 'Select additional KFs',
        '選択中のKF': 'Selected KFs',
        '議論対象のKFを選択してください。': 'Select a KF to discuss.',
        'コメントを入力': 'Enter a comment',
        '投稿': 'Post',
        '組織知登録': 'Register organizational knowledge',
        '追加する領域': 'Category',
        '知識関連': 'Knowledge',
        '研究方略関連': 'Research strategies',
        'その他': 'Other',
        '知の内容': 'Knowledge content',
        '要約': 'Summary',
        '組織知として残したい知を一文で入力': 'Summarize the knowledge in one sentence',
        '組織知化の根拠': 'Rationale for retaining this knowledge',
        '補足コメント': 'Additional comments',
        '登録': 'Register',
        '組織知ツリー': 'Organizational knowledge tree',
        '組織知ツリーフィルター': 'Organizational knowledge tree filters',
        '項目を追加': 'Add an item',
        '議論の文字起こし': 'Discussion transcript',
        '議論の文字起こしを表示': 'View discussion transcript',
        '文字起こしを閉じる': 'Close transcript',
        'セッション': 'Session',
        'KFに紐づく発言へ': 'Jump to linked utterances',
        'セッションの文字起こし': 'Session transcript',
        '前の発言': 'Previous utterances',
        '次の発言': 'Next utterances',
        'KF一覧を再読み込み': 'Reload knowledge fragments',
        'KFリンク': 'KF links',
        '履歴を保存': 'Save history',
        'このノードは削除できません（ID不正）': 'This node cannot be deleted because its ID is invalid.',
        'トップレベルのノードは削除できません': 'Top-level nodes cannot be deleted.',
        '本当にこのノードを削除しますか？': 'Delete this node?',
        '削除処理が進行中です': 'Deletion is in progress.',
        '削除に失敗しました': 'Could not delete the node.',
        '通信エラーで削除できませんでした': 'A connection error prevented deletion.',
        '削除応答の処理でエラーが発生しました': 'Could not process the deletion response.',
        '履歴を保存できませんでした': 'Could not save history.',
        '追加する項目名を入力してください': 'Enter a name for the new item.',
        '項目の作成に失敗しました': 'Could not create the item.',
        'KF一覧を読み込めませんでした。': 'Could not load the knowledge fragment list.',
        '未選択': 'None selected',
        '選択解除': 'Remove from selection',
        '詳細▼': 'Details ▼',
        '▲閉じる': '▲ Close',
        '読み込み中...': 'Loading…',
        '読み込み中…': 'Loading…',
        '読み込みに失敗しました': 'Could not load the data.',
        'データ形式が不正です': 'Invalid data format.',
        '知の内容を表示': 'Show knowledge content',
        '知の内容を閉じる': 'Hide knowledge content',
        '詳細': 'Details',
        '算出した知の詳細を表示': 'Show organizational knowledge details',
        '上へ移動': 'Move up',
        '下へ移動': 'Move down',
        '更新日時': 'Updated',
        '更新ユーザー': 'Updated by',
        '算出した知の詳細': 'Organizational knowledge details',
        '閉じる': 'Close',
        '詳細情報がありません。': 'No details are available.',
        '詳細情報の取得に失敗しました。': 'Could not load details.',
        '編集': 'Edit',
        'タイトル': 'Title',
        'When（どのような場面で有効か）': 'When (in which situations it is useful)',
        'What（何を考える／行うとよいか）': 'What (what to consider or do)',
        'Why（なぜそれが有効か）': 'Why (why it is useful)',
        '接続KF': 'Linked KFs',
        '現在選択中のKFを反映': 'Use the current KF selection',
        '保存': 'Save',
        '接続KFはexperience:IDまたはdiscussion:IDで指定してください': 'Enter linked KFs as experience:ID or discussion:ID.',
        'タイトルを入力してください': 'Enter a title.',
        '保存中...': 'Saving…',
        '保存に失敗しました': 'Could not save.',
        '保存しました': 'Saved.',
        '順番の更新に失敗しました': 'Could not update the order.',
        '順番更新の応答処理でエラーが発生しました': 'Could not process the reordering response.',
        'タイトルの保存に失敗しました': 'Could not save the title.',
        'タイトル保存の応答処理でエラーが発生しました': 'Could not process the title update response.',
        '議論対象とするフラグメントを選択してください': 'Select a knowledge fragment to discuss.',
        '一部のフラグメントの更新に失敗しました': 'Some knowledge fragments could not be updated.',
        '組織知の要約を入力してください。': 'Enter a summary of the organizational knowledge.',
        '登録中...': 'Registering…',
        '登録に失敗しました。': 'Could not register organizational knowledge.',
        '登録しました。': 'Organizational knowledge registered.',
        '通信エラーにより投稿できませんでした。': 'A connection error prevented posting.',
        '投稿の保存に失敗しました。': 'Could not save the post.',
        'この組織知に紐づく KF はありません。': 'This item has no linked knowledge fragments.',
        '投稿はまだありません。': 'No posts yet.',
        ' さん': '',
        'ユーザー': 'User',
        '経験': 'Experience',
        'discussion': 'Experience',
        '経験の振り返り': 'Reflection on Experience',
        '活動文脈固有の振り返り': 'Activity-Context-Specific Reflection',
        '研究固有の振り返り': 'Research-Specific Reflection',
        '【経験の振り返り】': 'Reflection on Experience',
        '【discussionの振り返り】': 'Reflection on Experience',
        '【活動文脈固有の振り返り】': 'Activity-Context-Specific Reflection',
        '【研究固有の振り返り】': 'Research-Specific Reflection',
        '表示できるフラグメントがありません。': 'No knowledge fragments to display.',
        '未入力': 'Not provided',
        'コメント': 'Comments',
        'エッジ追加終了': 'Finish adding edges',
        'エッジ追加': 'Add edges',
        '新しいラベルを入力してください:': 'Enter a new label:',
        'このノードにはすでに概念がつけられているため概念付けできません': 'This node already has a concept.',
        '選択した組織知を削除していいですか': 'Delete the selected organizational knowledge?',
        '組織知の削除に失敗しました': 'Could not delete the organizational knowledge.',
        '編集を保存': 'Save changes',
        '編集フォームを読み込めませんでした。ページをリロードしてください。': 'Could not load the edit form. Reload the page.',
        '組織知を更新しました。': 'Organizational knowledge updated.',
        '組織知の更新に失敗しました': 'Could not update the organizational knowledge.',
        '形成文脈を表示': 'View formation context',
        'マップ描画ライブラリを読み込めません。': 'Could not load the map library.',
        'きっかけ': 'Trigger',
        '自己内対話': 'Self-dialogue',
        '議論資料作成': 'Discussion Material Preparation',
        '議論内省': 'Discussion Reflection',
        '論文執筆': 'Paper Writing',
        '論文読解': 'Paper Reading',
        '思考支援': 'Thinking Support',
        '会議内省': 'Meeting Reflection',
        '会議': 'Meeting',
        '資料作成': 'Document Creation',
        '資料検討': 'Document Review',
        '表示できる思考過程ノードがありません。': 'No thinking process nodes to display.',
        'ノード {nodes}件・接続 {edges}件': 'Nodes: {nodes} · Connections: {edges}',
        'このKFにはForestの思考過程マップへの参照がありません。': 'This KF has no reference to a Forest thinking process map.',
        'Forest側に元KFまたは対応する文脈が見つかりません。': 'The source KF or its context could not be found in Forest.',
        'Forest側に元KFまたは対応する思考過程マップが見つかりません。': 'The source KF or its thinking process map could not be found in Forest.',
        'Forest参照APIが設定されていません。': 'The Forest API connection is not configured.',
        'この思考過程マップを閲覧する権限がありません。': 'You do not have permission to view this thinking process map.',
        'このOK-Coreグループの閲覧権限がありません。': 'You do not have permission to view this OK-Core group.',
        'このKFは選択中のOK-Coreグループに共有されていません。': 'This KF is not shared with the selected OK-Core group.',
        'OK-Coreの閲覧確認をForestで検証できません。両システムのAPI設定とコードを確認してください。': 'Forest could not verify the OK-Core access grant. Check the API configuration in both systems.',
        '思考過程マップを読み込めません。': 'Could not load the thinking process map.',
        '「{title}」の思考過程': 'Thinking process for “{title}”',
        '思考過程マップへの接続に失敗しました。': 'Could not connect to the thinking process map service.',
        '{title}（関連発言 {count}件）': '{title} (linked utterances: {count})',
        'セッション {id}': 'Session {id}',
        'このKFに紐づく発言が見つかりません。': 'No utterances are linked to this KF.',
        '紐づく発言にsession_idが登録されていないため、セッションを特定できません。': 'The linked utterances do not have a session ID.',
        '指定されたセッションはこのKFに紐づいていません。': 'The requested session is not linked to this KF.',
        'KF「{title}」': 'KF “{title}”',
        'KFの根拠となった議論': 'Discussion behind this KF',
        '話者不明': 'Unknown speaker',
        'KFに紐づく発言': 'Linked to this KF',
        '{start}–{end} / {total} 発言': 'Utterances {start}–{end} of {total}',
        'セッション全体の発言を時系列で表示しています。色付きの発言がこのKFに紐づいています。': 'Utterances are shown in chronological order. Highlighted utterances are linked to this KF.',
        ' セッション未登録の関連発言が{count}件あります。': ' Linked utterances with no session: {count}.',
        '文字起こしを読み込み中…': 'Loading transcript…',
        '文字起こしを取得できません。': 'Could not load the transcript.',
        '文字起こしの読み込みに失敗しました。接続を確認して再度開いてください。': 'Could not load the transcript. Check your connection and open it again.',
        'ログインが必要です。': 'Sign in to continue.',
        '組織またはKFの指定が正しくありません。': 'The organization or KF selection is invalid.',
        'OK-CoreのDBに接続できません。': 'Could not connect to the OK-Core database.',
        'この組織の閲覧権限がありません。': 'You do not have permission to view this organization.',
        'この組織に共有されたdiscussion KFが見つかりません。': 'The discussion KF shared with this organization could not be found.',
        '形成文脈': 'Formation context',
        '産出元から取得': 'Load from source',
        '送信時点の内容': 'View submitted snapshot',
        '形成文脈を取得できません。': 'Could not load the formation context.',
        'OK-Coreに保存した送信時点の内容です。': 'Showing the snapshot saved when this KF was submitted.',
        '産出元APIから取得した文脈パッケージです。': 'Showing the context package from the source API.',
        'KFの形成文脈': 'KF formation context',
        '産出元: {source} / KF: {id}': 'Source: {source} / KF: {id}',
        '活動: {type} / {id}': 'Activity: {type} / {id}',
        '文脈項目はありません。': 'No context items.',
        'DB接続失敗': 'Database connection failed.',
        '組織知を確認できませんでした。': 'Could not verify the organizational knowledge.',
        '組織知が見つかりません。': 'Organizational knowledge not found.',
        'フラグメントIDが特定できませんでした。': 'Could not identify the fragment ID.',
        'この組織で参照できるフラグメントがありません。': 'No knowledge fragments are available to this organization.',
        '選択したフラグメント数:': 'Selected knowledge fragments:',
        'フラグメント': 'Fragment',
        '種類:': 'Type:',
        '共有したユーザー:': 'Shared by:',
        ' の詳細取得先が未設定です。': ' has no configured detail provider.',
        ' の情報が見つかりません。': ' could not be found.',
        '産出元から取得できないため、送信時点の保存内容を表示しています。': 'Showing the submitted snapshot because the source context could not be loaded.',
        'DBユーザーの認証に失敗しています。php/connect_db.phpの接続ユーザー・パスワードと、DB側の認証方式・接続権限を確認してください。': 'Database authentication failed. Check the connection credentials in php/connect_db.php and the database authentication settings and permissions.',
        '指定したデータベースへのアクセス権限がありません。接続ユーザーと対象DBの権限を確認してください。': 'Access to the configured database was denied. Check the database user’s permissions.',
        '指定したデータベースが見つかりません。DB名が正しいか、対象DBが作成済みかを確認してください。': 'The configured database was not found. Check its name and confirm it exists.',
        'DBサーバーへ接続できません。ホスト・ポート・ソケットと、DBサーバーの稼働状態を確認してください。': 'Could not reach the database server. Check the host, port, socket, and server status.',
        'php/connect_db.phpとOK_CORE_DB_*の接続設定を確認してください。': 'Check php/connect_db.php and the OK_CORE_DB_* settings.'
    };

    const storageKey = 'ok-core.ui-language';
    let language = 'ja';
    try {
        if (localStorage.getItem(storageKey) === 'en') language = 'en';
    } catch (_) { /* The selector still works when browser storage is unavailable. */ }
    document.documentElement.lang = language;

    const bindings = new WeakMap();
    const marker = '[data-i18n], [data-i18n-attrs], [data-i18n-bound]';
    const hasTranslation = (key) => Object.prototype.hasOwnProperty.call(english, key);

    function t(key, values = {}) {
        const message = language === 'en' && hasTranslation(key) ? english[key] : key;
        return String(message ?? '').replace(/\{(\w+)\}/g, (part, name) =>
            Object.prototype.hasOwnProperty.call(values, name) ? String(values[name]) : part);
    }

    function error(message, fallback) {
        return (message && (language === 'ja' || hasTranslation(message))) ? message : fallback;
    }

    function write(node, attribute, value) {
        if (attribute === 'text') {
            if (node.textContent !== value) node.textContent = value;
        } else if (node.getAttribute(attribute) !== value) {
            node.setAttribute(attribute, value);
        }
    }

    // Only translate explicitly marked UI text. A renderer may replace a bound
    // value with raw content or clear it; leave that value alone on language changes.
    function refreshNode(node) {
        let items = bindings.get(node);
        if (!items) {
            items = new Map();
            if (node.hasAttribute('data-i18n')) {
                const key = node.getAttribute('data-i18n') || node.textContent.trim();
                items.set('text', { render: () => t(key) });
            }
            const attributes = node.getAttribute('data-i18n-attrs');
            if (attributes) attributes.split(/\s+/).forEach((attribute) => {
                const key = node.getAttribute(attribute);
                if (key != null) items.set(attribute, { render: () => t(key) });
            });
            bindings.set(node, items);
        }
        for (const [attribute, item] of items) {
            const current = attribute === 'text' ? node.textContent : node.getAttribute(attribute);
            if (item.last !== undefined && item.last !== current) {
                items.delete(attribute);
                continue;
            }
            item.last = item.render();
            write(node, attribute, item.last);
        }
    }

    function bind(node, key, values, attribute = 'text') {
        if (!node) return node;
        if (!bindings.has(node)) refreshNode(node);
        const render = typeof key === 'function' ? key : () => t(key, values);
        const last = render();
        bindings.get(node).set(attribute, { render, last });
        node.setAttribute('data-i18n-bound', '');
        write(node, attribute, last);
        return node;
    }

    function refresh(root) {
        if (root.matches && root.matches(marker)) refreshNode(root);
        root.querySelectorAll(marker).forEach(refreshNode);
    }

    window.okCoreLanguage = { t, bind, error, isEnglish: () => language === 'en' };
    document.addEventListener('DOMContentLoaded', () => {
        const select = document.getElementById('language-toggle');
        if (select) {
            select.value = language;
            select.addEventListener('change', () => {
                language = select.value === 'en' ? 'en' : 'ja';
                document.documentElement.lang = language;
                try { localStorage.setItem(storageKey, language); } catch (_) { /* Optional storage. */ }
                refresh(document);
                document.dispatchEvent(new Event('ok-core-language-change'));
            });
        }
        refresh(document);
        // The KF list and details are returned as HTML by existing endpoints.
        new MutationObserver((records) => {
            for (const record of records) for (const node of record.addedNodes) {
                if (node.nodeType === Node.ELEMENT_NODE) refresh(node);
            }
        }).observe(document.body, { childList: true, subtree: true });
    });
}());
