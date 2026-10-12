(function () {
    'use strict';

    const byId = (id) => document.getElementById(id);
    let fragmentId = '';
    let groupId = '';
    let request = null;
    let serial = 0;
    let page = null;

    function setStatus(text, isError) {
        const status = byId('transcript-status');
        status.textContent = text;
        status.classList.toggle('is-error', !!isError);
    }

    function setLoading(loading) {
        byId('transcript-scroll').setAttribute('aria-busy', String(loading));
        byId('transcript-session').disabled = loading || !byId('transcript-session').options.length;
        byId('transcript-jump').disabled = loading || !page;
        byId('transcript-previous').disabled = loading || !page || !page.has_previous;
        byId('transcript-next').disabled = loading || !page || !page.has_next;
    }

    function cancelRequest() {
        serial++;
        if (request) request.abort();
        request = null;
    }

    function clearTranscript() {
        page = null;
        byId('transcript-utterances').replaceChildren();
        byId('transcript-page').textContent = '';
    }

    function scrollToLinked() {
        const first = byId('transcript-utterances').querySelector('.is-linked');
        if (first) first.scrollIntoView({ block: 'center', behavior: 'auto' });
        else byId('transcript-scroll').scrollTop = 0;
    }

    function render(transcript) {
        const select = byId('transcript-session');
        select.replaceChildren();
        (transcript.sessions || []).forEach((session) => {
            const option = document.createElement('option');
            option.value = String(session.session_id);
            option.textContent = String(session.title) + '（関連発言 ' + session.linked_count + '件）';
            select.appendChild(option);
        });
        select.value = String(transcript.session_id);
        byId('transcript-kf-title').textContent = transcript.title
            ? 'KF「' + transcript.title + '」' : 'KFの根拠となった議論';
        const list = byId('transcript-utterances');
        const rows = document.createDocumentFragment();
        (transcript.utterances || []).forEach((utterance) => {
            const item = document.createElement('li');
            item.className = 'transcript-utterance' + (utterance.is_linked ? ' is-linked' : '');
            const meta = document.createElement('div');
            meta.className = 'transcript-utterance-meta';
            const speaker = document.createElement('strong');
            speaker.textContent = String(utterance.speaker_name || '話者不明');
            const time = document.createElement('time');
            time.textContent = String(utterance.uttered_at || '');
            if (utterance.uttered_at) time.dateTime = String(utterance.uttered_at).replace(' ', 'T');
            meta.append(speaker, time);
            if (utterance.is_linked) {
                const badge = document.createElement('span');
                badge.className = 'transcript-linked-badge';
                badge.textContent = 'KFに紐づく発言';
                meta.appendChild(badge);
            }
            const content = document.createElement('p');
            content.textContent = String(utterance.utterance_text || '');
            item.append(meta, content);
            rows.appendChild(item);
        });
        list.replaceChildren(rows);
        page = transcript.page;
        const start = page.total ? page.offset + 1 : 0;
        byId('transcript-page').textContent = start + '–' + (page.offset + transcript.utterances.length)
            + ' / ' + page.total + ' 発言';
        let message = 'セッション全体の発言を時系列で表示しています。色付きの発言がこのKFに紐づいています。';
        if (transcript.unassigned_link_count) {
            message += ' セッション未登録の関連発言が' + transcript.unassigned_link_count + '件あります。';
        }
        setStatus(message, false);
    }

    async function load(sessionId, offset) {
        cancelRequest();
        request = new AbortController();
        const current = serial;
        clearTranscript();
        setLoading(true);
        setStatus('文字起こしを読み込み中…', false);
        const query = new URLSearchParams({ group_id: groupId, externalized_contents_id: fragmentId });
        if (sessionId) query.set('session_id', sessionId);
        if (offset !== undefined) query.set('offset', String(offset));
        try {
            const response = await fetch('php/get_discussion_transcript.php?' + query,
                { credentials: 'same-origin', signal: request.signal });
            const result = await response.json();
            if (current !== serial) return;
            if (!response.ok || result.status !== 'ok' || !result.transcript) {
                setStatus(result.message || '文字起こしを取得できません。', true);
                return;
            }
            render(result.transcript);
            if (offset === undefined) scrollToLinked();
            else byId('transcript-scroll').scrollTop = 0;
        } catch (error) {
            if (error.name !== 'AbortError' && current === serial) {
                setStatus('文字起こしの読み込みに失敗しました。接続を確認して再度開いてください。', true);
            }
        } finally {
            if (current === serial) { request = null; setLoading(false); }
        }
    }

    function close() {
        cancelRequest();
        const dialog = byId('discussion-transcript-dialog');
        if (dialog.open) dialog.close();
        clearTranscript();
    }

    window.showDiscussionTranscript = function (node) {
        const id = String(node && (node.source_record_id || node.externalized_contents_id) || '');
        const group = byId('group_select');
        const selectedGroup = group ? String(group.value) : '';
        if (!node || node.source_type !== 'discussion' || !/^[1-9]\d*$/.test(id)
            || !/^[1-9]\d*$/.test(selectedGroup)) return;
        fragmentId = id;
        groupId = selectedGroup;
        byId('transcript-session').replaceChildren();
        byId('transcript-kf-title').textContent = 'KFの根拠となった議論';
        const dialog = byId('discussion-transcript-dialog');
        if (!dialog.open) dialog.showModal();
        load();
    };

    document.addEventListener('DOMContentLoaded', () => {
        const dialog = byId('discussion-transcript-dialog');
        if (!dialog) return;
        byId('transcript-close').addEventListener('click', close);
        dialog.addEventListener('cancel', (event) => { event.preventDefault(); close(); });
        dialog.addEventListener('click', (event) => { if (event.target === dialog) close(); });
        dialog.addEventListener('close', () => { cancelRequest(); clearTranscript(); });
        byId('transcript-session').addEventListener('change', (event) => load(event.target.value));
        byId('transcript-jump').addEventListener('click', () => load(byId('transcript-session').value));
        byId('transcript-previous').addEventListener('click', () => {
            if (page && page.has_previous) load(byId('transcript-session').value, Math.max(0, page.offset - page.limit));
        });
        byId('transcript-next').addEventListener('click', () => {
            if (page && page.has_next) load(byId('transcript-session').value, page.offset + page.limit);
        });
        const group = byId('group_select');
        if (group) group.addEventListener('change', close);
    });
}());
