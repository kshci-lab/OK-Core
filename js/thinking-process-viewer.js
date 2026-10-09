(function () {
    'use strict';

    let network = null;
    let activeRequest = null;
    let requestSerial = 0;
    let cardMenu = null;
    let cardSourceId = '';

    const byId = (id) => document.getElementById(id);
    const label = (value) => String(value == null ? '' : value)
        .split(/\r\n|\r|\n/)
        .map((line) => {
            const characters = Array.from(line);
            const rows = [];
            for (let index = 0; index < characters.length; index += 40) {
                rows.push(characters.slice(index, index + 40).join(''));
            }
            return rows.join('\n');
        })
        .join('\n');
    const position = (value, fallback) => {
        if (value == null || value === '') return fallback;
        const match = /^(-?(?:\d+(?:\.\d+)?|\.\d+))(?:px)?$/i.exec(String(value).trim());
        return match ? Number(match[1]) : fallback;
    };

    function setStatus(message, isError) {
        const status = byId('others_process_status');
        if (!status) return;
        status.textContent = message;
        status.classList.toggle('is-error', !!isError);
    }

    function destroyNetwork() {
        if (network) {
            network.destroy();
            network = null;
        }
        const canvas = byId('othersProcessnetwork');
        if (canvas) canvas.replaceChildren();
    }

    function closeMap() {
        requestSerial += 1;
        if (activeRequest) {
            activeRequest.abort();
            activeRequest = null;
        }
        destroyNetwork();
        const panel = byId('org-tabpanel-cooperation');
        if (panel) panel.classList.remove('is-showing-process-map');
        if (typeof window.closeOthersThinkingProcessMap === 'function') {
            window.closeOthersThinkingProcessMap();
        }
        setStatus('', false);
    }

    function hideCardMenu() {
        if (cardMenu) cardMenu.style.display = 'none';
        cardSourceId = '';
    }

    function bindFragmentCardMenu() {
        const workspace = byId('knowledge_fragments_workspace');
        if (!workspace) return;
        cardMenu = document.createElement('div');
        cardMenu.className = 'process-kf-context-menu';
        cardMenu.style.display = 'none';
        cardMenu.setAttribute('role', 'menu');
        const openButton = document.createElement('button');
        openButton.type = 'button';
        openButton.textContent = '思考過程表出化マップを表示';
        openButton.addEventListener('click', () => {
            const sourceId = cardSourceId;
            hideCardMenu();
            if (!/^\d+$/.test(sourceId)) return;
            const cooperationTab = byId('org-tab-cooperation');
            if (cooperationTab) cooperationTab.click();
            showThinkingProcessMap({ source_type: 'experience', source_record_id: sourceId });
        });
        cardMenu.appendChild(openButton);
        document.body.appendChild(cardMenu);
        workspace.addEventListener('contextmenu', (event) => {
            const card = event.target && event.target.closest
                ? event.target.closest('.knowledge_fragment[data-source-type="experience"]') : null;
            if (!card || !workspace.contains(card)) return;
            const sourceId = String(card.getAttribute('data-source-id') || '');
            if (!/^\d+$/.test(sourceId)) return;
            event.preventDefault();
            cardSourceId = sourceId;
            cardMenu.style.display = 'block';
            const bounds = cardMenu.getBoundingClientRect();
            cardMenu.style.left = Math.max(8, Math.min(event.clientX, window.innerWidth - bounds.width - 8)) + 'px';
            cardMenu.style.top = Math.max(8, Math.min(event.clientY, window.innerHeight - bounds.height - 8)) + 'px';
        });
        document.addEventListener('mousedown', (event) => {
            if (cardMenu && !cardMenu.contains(event.target)) hideCardMenu();
        }, true);
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') hideCardMenu();
        });
    }

    function renderMap(map) {
        destroyNetwork();
        const container = byId('othersProcessnetwork');
        if (!container || !window.vis || !vis.Network || !vis.DataSet) {
            setStatus('マップ描画ライブラリを読み込めません。', true);
            return;
        }
        const nodes = [];
        const edges = [];
        const known = new Set();
        const edgePairs = new Set();
        const versionTimeIds = new Map();
        const focusId = String(map.focus_id || '');
        const addNode = (id, definition) => {
            id = String(id || '');
            if (!id || known.has(id)) return;
            known.add(id);
            nodes.push(Object.assign({ id }, definition));
        };
        const addEdge = (from, to, definition) => {
            from = String(from || '');
            to = String(to || '');
            if (!known.has(from) || !known.has(to) || from === to) return;
            const pair = from + '\u0000' + to;
            if (edgePairs.has(pair)) return;
            edgePairs.add(pair);
            edges.push(Object.assign({ from, to }, definition || {}));
        };

        (map.process_nodes || []).forEach((row, index) => {
            const id = String(row.process_node_id || '');
            addNode(id, {
                label: label(row.content),
                shape: 'box',
                color: { background: '#ffdb4f', border: id === focusId ? '#176b87' : '#b78b00' },
                borderWidth: id === focusId ? 3 : 1,
                x: position(row.node_x, index * 220),
                y: position(row.node_y, 220),
            });
        });
        const versions = (map.node_versions || []).slice().sort((left, right) => {
            const byTime = String(left.appeared_at || '').localeCompare(String(right.appeared_at || ''));
            return byTime || String(left.node_version_id || '').localeCompare(String(right.node_version_id || ''));
        });
        versions.forEach((row, index) => {
            const id = String(row.node_version_id || '');
            const content = String(row.content || '');
            addNode(id, {
                label: label(content),
                shape: 'box',
                color: { background: '#ffbaa1', border: id === focusId ? '#176b87' : '#be7565' },
                borderWidth: id === focusId ? 3 : 1,
                fixed: { x: false, y: true },
                x: index * 300,
                y: 100,
            });
            const appearedAt = String(row.appeared_at || '').trim();
            if (id && appearedAt) {
                const timeId = '__version_time__:' + id;
                versionTimeIds.set(id, timeId);
                addNode(timeId, {
                    label: appearedAt,
                    shape: 'text',
                    font: { color: '#4d403c', size: 13 },
                    margin: { top: 2, right: 6, bottom: 2, left: 6 },
                    chosen: false,
                    fixed: true,
                    physics: false,
                    x: index * 300,
                    y: 160,
                });
            }
        });
        (map.triggers || []).forEach((row, index) => {
            const id = String(row.trigger_id || '');
            const activity = String(row.activity_type || 'きっかけ');
            const suppliedIcon = map.trigger_icons && map.trigger_icons[row.icon_id];
            const icon = typeof suppliedIcon === 'string'
                && /^data:image\/(?:png|jpeg|webp|gif);base64,[A-Za-z0-9+/=]+$/i.test(suppliedIcon)
                ? suppliedIcon : '';
            const triggerNode = {
                label: label(icon ? row.activity_time : activity),
                title: [row.activity_time, activity, row.content].filter(Boolean).join('\n'),
                shape: icon ? 'circularImage' : 'dot',
                size: icon ? 25 : 19,
                color: { background: '#82ae46', border: id === focusId ? '#176b87' : '#5f8530' },
                borderWidth: id === focusId ? 3 : 1,
                x: position(row.x, index * 160),
                y: position(row.y, 140),
            };
            if (icon) {
                triggerNode.image = icon;
                triggerNode.imagePadding = 7;
            }
            addNode(id, triggerNode);
        });
        (map.process_edges || []).forEach((row) => {
            addEdge(row.edge_start, row.edge_end, {
                label: String(row.label || ''),
                arrows: 'to',
            });
        });
        for (let index = 1; index < versions.length; index += 1) {
            addEdge(versions[index - 1].node_version_id, versions[index].node_version_id, {
                arrows: 'to',
                dashes: true,
                color: '#c08c80',
            });
        }
        (map.triggers || []).forEach((row) => {
            addEdge(row.edge_from, row.trigger_id, { arrows: 'to', color: '#82ae46' });
            addEdge(row.trigger_id, row.edge_to, { arrows: 'to', color: '#82ae46' });
        });

        if (nodes.length === 0) {
            setStatus('表示できる思考過程ノードがありません。', true);
            return;
        }
        const nodeData = new vis.DataSet(nodes);
        network = new vis.Network(container, {
            nodes: nodeData,
            edges: new vis.DataSet(edges),
        }, {
            autoResize: true,
            physics: false,
            interaction: { hover: true, navigationButtons: false },
            nodes: { font: { color: '#1e2b31', size: 14 }, margin: 10,
                widthConstraint: { maximum: 150 } },
            edges: { color: '#87969c', smooth: false, font: { size: 11 } },
        });
        const renderedNetwork = network;
        const alignVersionTime = (versionId) => {
            const timeId = versionTimeIds.get(String(versionId));
            if (!timeId) return;
            const bounds = renderedNetwork.getBoundingBox(String(versionId));
            if (!bounds || !Number.isFinite(bounds.bottom)) return;
            nodeData.update({
                id: timeId,
                x: (bounds.left + bounds.right) / 2,
                y: bounds.bottom + 20,
            });
        };
        renderedNetwork.on('dragEnd', (params) => {
            (params.nodes || []).forEach(alignVersionTime);
        });
        requestAnimationFrame(() => {
            if (network !== renderedNetwork) return;
            versionTimeIds.forEach((timeId, versionId) => alignVersionTime(versionId));
            renderedNetwork.fit({ animation: false });
        });
        setStatus('ノード ' + (nodes.length - versionTimeIds.size) + '件・接続 ' + edges.length + '件', false);
    }

    async function showThinkingProcessMap(node) {
        const sourceType = String(node && node.source_type || '');
        const fragmentId = String(node && (node.source_record_id || node.experience_knowledge_id) || '');
        const groupSelect = byId('group_select');
        const groupId = groupSelect ? String(groupSelect.value || '') : '';
        if (sourceType !== 'experience' || !/^\d+$/.test(fragmentId) || !/^\d+$/.test(groupId)) {
            return;
        }
        if (activeRequest) activeRequest.abort();
        activeRequest = new AbortController();
        const currentRequest = ++requestSerial;
        const panel = byId('org-tabpanel-cooperation');
        const pane = byId('process_others_network_container');
        if (panel) panel.classList.add('is-showing-process-map');
        if (pane) pane.style.display = '';
        destroyNetwork();
        const title = byId('others_conceptdisplay');
        if (title) title.textContent = '思考過程表出化マップ';
        setStatus('読み込み中…', false);
        try {
            const url = 'php/get_thinking_process_map.php?group_id=' + encodeURIComponent(groupId)
                + '&experience_knowledge_id=' + encodeURIComponent(fragmentId);
            const response = await fetch(url, { credentials: 'same-origin', signal: activeRequest.signal });
            const result = await response.json();
            if (currentRequest !== requestSerial) return;
            if (!response.ok || result.status !== 'ok' || !result.map) {
                const messages = {
                    source_fragment_unavailable: 'このKFにはForestの思考過程マップへの参照がありません。',
                    PROCESS_MAP_NOT_FOUND: 'Forest側の思考過程マップが見つかりません。',
                    FOREST_NOT_CONFIGURED: 'Forest参照APIが設定されていません。',
                    PROCESS_MAP_FORBIDDEN: 'この思考過程マップを閲覧する権限がありません。',
                };
                setStatus(messages[result.error] || result.message || '思考過程マップを読み込めません。', true);
                return;
            }
            if (title) title.textContent = '「' + String(result.map.summary || 'KF') + '」の思考過程';
            renderMap(result.map);
        } catch (error) {
            if (error.name !== 'AbortError' && currentRequest === requestSerial) {
                setStatus('思考過程マップへの接続に失敗しました。', true);
            }
        } finally {
            if (currentRequest === requestSerial) activeRequest = null;
        }
    }

    window.showThinkingProcessMap = showThinkingProcessMap;
    window.closeThinkingProcessViewer = closeMap;
    document.addEventListener('DOMContentLoaded', () => {
        bindFragmentCardMenu();
        const closeButton = byId('area_close');
        if (closeButton) closeButton.addEventListener('click', closeMap);
        const select = byId('group_select');
        if (select) select.addEventListener('change', closeMap);
        const combinationTab = byId('org-tab-combination');
        if (combinationTab) combinationTab.addEventListener('click', closeMap);
        const zoomIn = byId('process_ZoomIn');
        const zoomOut = byId('process_ZoomOut');
        const fit = byId('process_Fit');
        if (zoomIn) zoomIn.addEventListener('click', () => {
            if (network) network.moveTo({ scale: network.getScale() * 1.2 });
        });
        if (zoomOut) zoomOut.addEventListener('click', () => {
            if (network) network.moveTo({ scale: network.getScale() / 1.2 });
        });
        if (fit) fit.addEventListener('click', () => {
            if (network) network.fit({ animation: true });
        });
    });
}());
