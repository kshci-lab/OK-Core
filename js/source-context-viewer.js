(function () {
    'use strict';

    const ui = window.okCoreLanguage;
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
        const title = ui.bind(make('h2'), '形成文脈'); title.id = 'source-context-title'; toolbar.appendChild(title);
        for (const [label, action] of [['産出元から取得', () => load('latest')], ['送信時点の内容', () => load('snapshot')], ['閉じる', close]]) {
            const button = ui.bind(make('button'), label); button.type = 'button'; button.addEventListener('click', action); toolbar.appendChild(button);
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
        content.replaceChildren(); ui.bind(status, '読み込み中…');
        try {
            const url = 'php/get_source_context.php?knowledge_fragment_id=' + encodeURIComponent(currentId)
                + '&group_id=' + encodeURIComponent(groupId || '') + '&mode=' + mode;
            const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', signal: request.signal });
            const result = await response.json();
            if (turn !== serial) return;
            if (!response.ok || result.status !== 'ok') throw new Error(result.message || '形成文脈を取得できません。');
            const context = result.context; const pack = context.data.context_package;
            const provenance = context.provenance === 'snapshot'
                ? 'OK-Coreに保存した送信時点の内容です。' : '産出元APIから取得した文脈パッケージです。';
            ui.bind(status, () => context.warning
                ? ui.t(ui.error(context.warning.message, '産出元から取得できないため、送信時点の保存内容を表示しています。')) + ' [' + context.warning.code + ']'
                : ui.t(provenance));
            content.appendChild(context.summary ? make('h3', context.summary) : ui.bind(make('h3'), 'KFの形成文脈'));
            content.appendChild(ui.bind(make('p'), '産出元: {source} / KF: {id}', {
                source: context.source_system, id: context.data.external_kf_id,
            }));
            const activity = pack.activity || {};
            content.appendChild(ui.bind(make('p'), '活動: {type} / {id}', {
                type: activity.type_code || '', id: activity.external_activity_id || '',
            }));
            if (!pack.items.length) content.appendChild(ui.bind(make('p'), '文脈項目はありません。'));
            for (const item of pack.items) {
                const article = make('article');
                article.appendChild(make('h4', item.entity_type_code + ' / ' + item.entity_id));
                article.appendChild(make('pre', JSON.stringify(item.snapshot, null, 2)));
                content.appendChild(article);
            }
        } catch (error) {
            if (error.name !== 'AbortError' && turn === serial) {
                ui.bind(status, () => ui.t(ui.error(error.message, '形成文脈を取得できません。')));
            }
        } finally { if (turn === serial) request = null; }
    }

    window.showSourceContext = function (id) {
        if (!/^[1-9]\d*$/.test(String(id))) return;
        ensureDialog(); currentId = String(id);
        if (!dialog.open) dialog.showModal();
        load('latest');
    };
}());
