(function () {
    'use strict';
    let dialog, content, status, currentId, request, serial = 0;
    const make = (tag, text) => { const node = document.createElement(tag); if (text != null) node.textContent = text; return node; };

    function close() {
        serial++;
        if (request) request.abort();
        request = null;
        if (dialog && dialog.open) dialog.close();
    }

    function ensureDialog() {
        if (dialog) return;
        dialog = make('dialog'); dialog.className = 'source-context-dialog';
        dialog.setAttribute('aria-labelledby', 'source-context-title');
        const toolbar = make('div'); toolbar.className = 'source-context-toolbar';
        const title = make('h2', '形成文脈'); title.id = 'source-context-title'; toolbar.appendChild(title);
        for (const [label, action] of [['産出元から取得', () => load('latest')], ['送信時点の内容', () => load('snapshot')], ['閉じる', close]]) {
            const button = make('button', label); button.type = 'button'; button.addEventListener('click', action); toolbar.appendChild(button);
        }
        status = make('p'); status.setAttribute('role', 'status'); status.className = 'source-context-status';
        content = make('div'); content.className = 'source-context-content';
        dialog.append(toolbar, status, content); document.body.appendChild(dialog);
        dialog.addEventListener('cancel', event => { event.preventDefault(); close(); });
        dialog.addEventListener('click', event => { if (event.target === dialog) close(); });
        const group = document.getElementById('group_select');
        if (group) group.addEventListener('change', close);
    }

    async function load(mode) {
        if (request) request.abort();
        request = new AbortController(); const turn = ++serial;
        const group = document.getElementById('group_select');
        const groupId = group && group.value;
        content.replaceChildren(); status.textContent = '読み込み中…';
        try {
            const url = 'php/get_source_context.php?knowledge_fragment_id=' + encodeURIComponent(currentId)
                + '&group_id=' + encodeURIComponent(groupId || '') + '&mode=' + mode;
            const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', signal: request.signal });
            const result = await response.json();
            if (turn !== serial) return;
            if (!response.ok || result.status !== 'ok') throw new Error(result.message || '形成文脈を取得できません。');
            const context = result.context; const pack = context.data.context_package;
            status.textContent = context.warning ? context.warning.message + ' [' + context.warning.code + ']'
                : context.provenance === 'snapshot' ? 'OK-Coreに保存した送信時点の内容です。' : '産出元APIから取得した文脈パッケージです。';
            content.appendChild(make('h3', context.summary || 'KFの形成文脈'));
            content.appendChild(make('p', '産出元: ' + context.source_system + ' / KF: ' + context.data.external_kf_id));
            const activity = pack.activity || {};
            content.appendChild(make('p', '活動: ' + (activity.type_code || '') + ' / ' + (activity.external_activity_id || '')));
            if (!pack.items.length) content.appendChild(make('p', '文脈項目はありません。'));
            for (const item of pack.items) {
                const article = make('article');
                article.appendChild(make('h4', item.entity_type_code + ' / ' + item.entity_id));
                article.appendChild(make('pre', JSON.stringify(item.snapshot, null, 2)));
                content.appendChild(article);
            }
        } catch (error) {
            if (error.name !== 'AbortError' && turn === serial) status.textContent = error.message || '形成文脈を取得できません。';
        } finally { if (turn === serial) request = null; }
    }

    window.showSourceContext = function (id) {
        if (!/^[1-9]\d*$/.test(String(id))) return;
        ensureDialog(); currentId = String(id);
        if (!dialog.open) dialog.showModal();
        load('latest');
    };
}());
