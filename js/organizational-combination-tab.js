// forest-mrn: organizational map sub-tabs + "combination" overlay behaviors
(function(){
  'use strict';

  function qs(sel, root){ return (root || document).querySelector(sel); }
  function qsa(sel, root){ return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
  var SOURCE_TYPES = ['experience', 'discussion', 'SRL'];
  var sourceFilterState = {
    cooperation: SOURCE_TYPES.slice(),
    fragments: SOURCE_TYPES.slice(),
    'knowledge-tree': SOURCE_TYPES.slice()
  };
  var displayFilterState = {
    cooperation: {
      hideOrganizationalKnowledge: false
    },
    'knowledge-tree': {
      hideOrganizationalKnowledge: false
    }
  };
  var kfragRelationVisible = false;
  var KFRAG_VIEW_STORAGE_KEY = 'ok_core_combination_kfrag_view_mode';
  var KFRAG_ZOOM_STORAGE_KEY = 'ok_core_combination_kfrag_canvas_zoom';

  function getKfragViewStorageKey(){
    var gid = '';
    try{ gid = getSelectedGroupId ? getSelectedGroupId() : ''; }catch(_){ gid = ''; }
    return KFRAG_VIEW_STORAGE_KEY + ':' + (gid || 'default');
  }

  function getKfragZoomStorageKey(){
    var gid = '';
    try{ gid = getSelectedGroupId ? getSelectedGroupId() : ''; }catch(_){ gid = ''; }
    return KFRAG_ZOOM_STORAGE_KEY + ':' + (gid || 'default');
  }

  function normalizeSourceType(type){
    var raw = String(type || '').trim();
    var lower = raw.toLowerCase();
    if(lower === 'discussion' || lower === 'externalized') return 'discussion';
    if(lower === 'srl') return 'SRL';
    if(lower === 'experience') return 'experience';
    if(lower === 'discussion') return 'discussion';
    return '';
  }

  function parseSourceTypes(value, fallback){
    var values = String(value || '').split(',').map(function(v){ return normalizeSourceType(v); }).filter(Boolean);
    values = values.filter(function(v, i){ return values.indexOf(v) === i; });
    if(values.length === 0 && fallback){ values = [fallback]; }
    return values;
  }

  function getActiveSourceTypes(scope){
    var active = sourceFilterState[scope] || SOURCE_TYPES.slice();
    if(!active.length) return SOURCE_TYPES.slice();
    return active.slice();
  }

  function matchesSourceFilter(sourceTypes, scope){
    var active = getActiveSourceTypes(scope);
    return sourceTypes.some(function(type){ return active.indexOf(type) !== -1; });
  }

  function applyFragmentSourceFilter(){
    var workspace = document.getElementById('knowledge_fragments_workspace');
    if(!workspace) return;
    qsa('.fragment-node-wrapper', workspace).forEach(function(wrap){
      var sourceTypes = parseSourceTypes(wrap.getAttribute('data-source-type'), 'experience');
      wrap.style.display = matchesSourceFilter(sourceTypes, 'fragments') ? '' : 'none';
    });
  }

  function applyKnowledgeTreeSourceFilter(){
    var tree = document.getElementById('overlay_knowledge_tree');
    if(!tree) return;
    if(displayFilterState['knowledge-tree'] && displayFilterState['knowledge-tree'].hideOrganizationalKnowledge){
      qsa(':scope > .kt-node', tree).forEach(function(node){
        node.style.display = 'none';
      });
      return;
    }
    function applyNode(node){
      var children = qsa(':scope > .kt-children > .kt-node', node);
      var childVisible = false;
      children.forEach(function(child){
        if(applyNode(child)) childVisible = true;
      });
      var sourceTypes = parseSourceTypes(node.getAttribute('data-source-types'), '');
      var isRoot = node.classList.contains('kt-root');
      var ownVisible = isRoot || sourceTypes.length === 0 || matchesSourceFilter(sourceTypes, 'knowledge-tree');
      var visible = childVisible || ownVisible;
      node.style.display = visible ? '' : 'none';
      return visible;
    }
    qsa(':scope > .kt-node', tree).forEach(applyNode);
  }

  function applySourceFilter(scope){
    if(scope === 'fragments') applyFragmentSourceFilter();
    if(scope === 'knowledge-tree') applyKnowledgeTreeSourceFilter();
    if(scope === 'cooperation'){
      try{
        if(typeof defaultOrganizational !== 'undefined' && defaultOrganizational && typeof defaultOrganizational.applySourceFilter === 'function'){
          defaultOrganizational.applySourceFilter(getActiveSourceTypes('cooperation'), {
            hideOrganizationalKnowledge: !!(displayFilterState.cooperation && displayFilterState.cooperation.hideOrganizationalKnowledge)
          });
        }
      }catch(_){ }
    }
  }

  function bindSourceFilters(){
    qsa('.source-filter').forEach(function(group){
      if(group.__sourceFilterBound) return;
      group.__sourceFilterBound = true;
      var scope = group.getAttribute('data-filter-scope') || '';
      group.addEventListener('click', function(e){
        var btn = e.target && e.target.closest ? e.target.closest('.source-filter-btn') : null;
        if(!btn || !group.contains(btn)) return;
        var type = normalizeSourceType(btn.getAttribute('data-source-filter'));
        if(!type) return;
        var active = sourceFilterState[scope] || SOURCE_TYPES.slice();
        var idx = active.indexOf(type);
        if(idx === -1) active.push(type);
        else active.splice(idx, 1);
        if(active.length === 0) active = SOURCE_TYPES.slice();
        sourceFilterState[scope] = active;
        qsa('.source-filter-btn', group).forEach(function(each){
          var eachType = normalizeSourceType(each.getAttribute('data-source-filter'));
          each.classList.toggle('is-active', active.indexOf(eachType) !== -1);
        });
        applySourceFilter(scope);
      }, false);
    });
    qsa('.display-filter').forEach(function(group){
      if(group.__displayFilterBound) return;
      group.__displayFilterBound = true;
      var scope = group.getAttribute('data-filter-scope') || '';
      qsa('.display-filter-btn', group).forEach(function(btn){
        var filterName = String(btn.getAttribute('data-display-filter') || '').trim();
        if(filterName === 'hide-organizational-knowledge'){
          var hidden = !!(displayFilterState[scope] && displayFilterState[scope].hideOrganizationalKnowledge);
          btn.classList.toggle('is-active', !hidden);
          btn.textContent = '組織知';
        }
      });
      group.addEventListener('click', function(e){
        var btn = e.target && e.target.closest ? e.target.closest('.display-filter-btn') : null;
        if(!btn || !group.contains(btn)) return;
        var filterName = String(btn.getAttribute('data-display-filter') || '').trim();
        if(filterName !== 'hide-organizational-knowledge') return;
        if(!displayFilterState[scope]) displayFilterState[scope] = {};
        displayFilterState[scope].hideOrganizationalKnowledge = !displayFilterState[scope].hideOrganizationalKnowledge;
        var hidden = !!displayFilterState[scope].hideOrganizationalKnowledge;
        btn.classList.toggle('is-active', !hidden);
        btn.textContent = '組織知';
        applySourceFilter(scope);
      }, false);
    });
  }

  function setTab(activeId){
    var tabIds = ['org-tab-cooperation','org-tab-combination'];
    var panelByTab = {
      'org-tab-cooperation': 'org-tabpanel-cooperation',
      'org-tab-combination': 'org-tabpanel-combination'
    };
    tabIds.forEach(function(id){
      var tab = document.getElementById(id);
      var panel = document.getElementById(panelByTab[id]);
      var isActive = (id === activeId);
      if(tab){
        tab.classList.toggle('is-active', isActive);
        tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
      }
      if(panel){
        panel.classList.toggle('is-active', isActive);
        if(isActive) panel.removeAttribute('hidden');
        else panel.setAttribute('hidden','hidden');
      }
    });

    if(activeId === 'org-tab-combination'){
      try{ initCombinationOverlay(); }catch(e){ /* no-op */ }
      bindSourceFilters();
      applyFragmentSourceFilter();
      applyKnowledgeTreeSourceFilter();
    } else if(activeId === 'org-tab-cooperation'){
      try{
        if(window.defaultOrganizational && typeof window.defaultOrganizational.loadProducedKnowledgeNodes === 'function'){
          window.defaultOrganizational.loadProducedKnowledgeNodes(getSelectedGroupId());
        } else if(typeof defaultOrganizational !== 'undefined' && defaultOrganizational && typeof defaultOrganizational.loadProducedKnowledgeNodes === 'function'){
          defaultOrganizational.loadProducedKnowledgeNodes(getSelectedGroupId());
        }
      }catch(e){ /* no-op */ }
    }
  }

  var _combinationInited = false;
  function initCombinationOverlay(){
    if(_combinationInited) return;
    _combinationInited = true;

    var workspace = document.getElementById('knowledge_fragments_workspace');
    if(workspace){
      ensureKfragViewControls(workspace);
      initFragmentWorkspace(workspace);
      bindKfragActions(workspace);
      loadKfragRelations(workspace);
    }

    bindDiscussedControls();
    bindKnowledgeRegister();
    loadKnowledgeTree();
    bindKnowledgeTreeContextMenu();
    bindKnowledgeTreeAddNode();
    bindGroupSelectSync(workspace);
    bindDiscussion();
    bindSourceFilters();
    applyFragmentSourceFilter();
    applyKnowledgeTreeSourceFilter();
    autoRestoreUnderwayTargets(workspace, function(){
      // Whether restored or not, load discussion once with the current selection state.
      loadDiscussion();
    });
  }

  function getKfragViewMode(){
    try{
      var saved = window.localStorage ? window.localStorage.getItem(getKfragViewStorageKey()) : '';
      if(saved === 'canvas') return 'canvas';
    }catch(_){ }
    return 'list';
  }

  function saveKfragViewMode(mode){
    try{
      if(window.localStorage) window.localStorage.setItem(getKfragViewStorageKey(), mode === 'canvas' ? 'canvas' : 'list');
    }catch(_){ }
  }

  function getKfragCanvasZoom(){
    try{
      if(window.localStorage){
        var raw = parseFloat(window.localStorage.getItem(getKfragZoomStorageKey()) || '1');
        if(!isNaN(raw) && raw >= 0.5 && raw <= 1.8) return raw;
      }
    }catch(_){ }
    return 1;
  }

  function saveKfragCanvasZoom(zoom){
    try{
      if(window.localStorage) window.localStorage.setItem(getKfragZoomStorageKey(), String(zoom));
    }catch(_){ }
  }

  function ensureKfragViewControls(workspace){
    if(!workspace) return;
    var group = qs('.kfrag-action-group', workspace);
    if(!group || group.__viewControlsReady) return;
    group.__viewControlsReady = true;

    var viewGroup = document.createElement('span');
    viewGroup.className = 'kfrag-view-switch';
    viewGroup.setAttribute('role', 'group');

    var listBtn = document.createElement('button');
    listBtn.type = 'button';
    listBtn.id = 'kfrag-view-list';
    listBtn.className = 'kfrag-view-btn';
    listBtn.setAttribute('data-kfrag-view', 'list');
    listBtn.textContent = 'List';

    var canvasBtn = document.createElement('button');
    canvasBtn.type = 'button';
    canvasBtn.id = 'kfrag-view-canvas';
    canvasBtn.className = 'kfrag-view-btn';
    canvasBtn.setAttribute('data-kfrag-view', 'canvas');
    canvasBtn.textContent = 'Canvas';

    viewGroup.appendChild(listBtn);
    viewGroup.appendChild(canvasBtn);
    group.insertBefore(viewGroup, group.firstChild);

    var zoomGroup = document.createElement('span');
    zoomGroup.className = 'kfrag-zoom-switch';
    zoomGroup.setAttribute('role', 'group');

    var zoomOutBtn = document.createElement('button');
    zoomOutBtn.type = 'button';
    zoomOutBtn.className = 'kfrag-zoom-btn';
    zoomOutBtn.setAttribute('data-kfrag-zoom-action', 'out');
    zoomOutBtn.textContent = '−';

    var zoomLabel = document.createElement('span');
    zoomLabel.className = 'kfrag-zoom-label';
    zoomLabel.id = 'kfrag-zoom-label';
    zoomLabel.textContent = '100%';

    var zoomInBtn = document.createElement('button');
    zoomInBtn.type = 'button';
    zoomInBtn.className = 'kfrag-zoom-btn';
    zoomInBtn.setAttribute('data-kfrag-zoom-action', 'in');
    zoomInBtn.textContent = '+';

    var zoomResetBtn = document.createElement('button');
    zoomResetBtn.type = 'button';
    zoomResetBtn.className = 'kfrag-zoom-btn';
    zoomResetBtn.setAttribute('data-kfrag-zoom-action', 'reset');
    zoomResetBtn.textContent = '100%';

    zoomGroup.appendChild(zoomOutBtn);
    zoomGroup.appendChild(zoomLabel);
    zoomGroup.appendChild(zoomInBtn);
    zoomGroup.appendChild(zoomResetBtn);
    group.appendChild(zoomGroup);

    var relationBtn = document.createElement('button');
    relationBtn.type = 'button';
    relationBtn.id = 'kfrag-toggle-relations';
    relationBtn.className = 'kfrag-action-btn kfrag-relation-toggle';
    relationBtn.textContent = 'KFリンク';
    group.appendChild(relationBtn);

    viewGroup.addEventListener('click', function(e){
      var btn = e.target && e.target.closest ? e.target.closest('.kfrag-view-btn') : null;
      if(!btn || !viewGroup.contains(btn)) return;
      var mode = btn.getAttribute('data-kfrag-view') === 'canvas' ? 'canvas' : 'list';
      setKfragViewMode(workspace, mode, true);
    }, false);

    zoomGroup.addEventListener('click', function(e){
      var btn = e.target && e.target.closest ? e.target.closest('.kfrag-zoom-btn') : null;
      if(!btn || !zoomGroup.contains(btn)) return;
      var action = btn.getAttribute('data-kfrag-zoom-action') || '';
      var current = getKfragCanvasZoom();
      var next = current;
      if(action === 'in') next = Math.min(1.8, Math.round((current + 0.1) * 10) / 10);
      else if(action === 'out') next = Math.max(0.5, Math.round((current - 0.1) * 10) / 10);
      else next = 1;
      applyKfragCanvasZoom(workspace, next, true);
    }, false);
  }

  function updateKfragViewButtons(mode){
    qsa('.kfrag-view-btn').forEach(function(btn){
      var active = (btn.getAttribute('data-kfrag-view') === mode);
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  }

  function updateKfragRelationToggleButtons(){
    qsa('.kfrag-relation-toggle').forEach(function(btn){
      var active = !!kfragRelationVisible;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  }

  function updateKfragZoomUI(workspace, zoom){
    var label = document.getElementById('kfrag-zoom-label');
    if(label) label.textContent = String(Math.round((zoom || 1) * 100)) + '%';
    workspace.classList.toggle('is-canvas-zoomable', workspace.classList.contains('is-canvas-mode'));
  }

  function getKfragCanvasZoomValue(workspace){
    if(!workspace) return 1;
    var raw = parseFloat(workspace.getAttribute('data-kfrag-canvas-zoom') || '1');
    return (!isNaN(raw) && raw > 0) ? raw : 1;
  }

  function applyKfragCanvasZoom(workspace, zoom, persist){
    if(!workspace) return;
    var list = qs('.knowledge-fragment-list', workspace);
    if(!list) return;
    zoom = parseFloat(zoom);
    if(isNaN(zoom)) zoom = 1;
    zoom = Math.max(0.5, Math.min(1.8, zoom));
    list.style.zoom = String(zoom);
    workspace.setAttribute('data-kfrag-canvas-zoom', String(zoom));
    updateKfragZoomUI(workspace, zoom);
    if(persist) saveKfragCanvasZoom(zoom);
    if(workspace.classList.contains('is-canvas-mode')){
      renderKfragRelations(workspace);
      if(window.activeKnowledgeLinkedFragmentIds && window.activeKnowledgeLinkedFragmentIds.length){
        updateKnowledgeConnectionOverlay(
          workspace,
          window.activeKnowledgeLinkedFragmentIds,
          window.activeKnowledgeLinkedTitle || '',
          window.activeKnowledgeLinkedFragmentRefs || []
        );
      }
    }
  }

  function setKfragViewMode(workspace, mode, persist){
    if(!workspace) return;
    mode = (mode === 'canvas') ? 'canvas' : 'list';
    workspace.setAttribute('data-kfrag-view', mode);
    workspace.classList.toggle('is-canvas-mode', mode === 'canvas');
    workspace.classList.toggle('is-list-mode', mode !== 'canvas');
    updateKfragViewButtons(mode);
    if(persist) saveKfragViewMode(mode);
    if(mode === 'canvas'){
      applyCanvasLayout(workspace, false);
    } else {
      var list = qs('.knowledge-fragment-list', workspace);
      if(list) list.style.minHeight = '';
      qsa('.fragment-node-wrapper', workspace).forEach(function(w){
        w.style.position = '';
        w.style.left = '';
        w.style.top = '';
        w.style.width = '';
        w.style.zIndex = '';
      });
    }
    applyKfragCanvasZoom(workspace, getKfragCanvasZoom(), false);
    try{
      if(window.activeKnowledgeLinkedFragmentIds && window.activeKnowledgeLinkedFragmentIds.length){
        updateKnowledgeConnectionOverlay(
          workspace,
          window.activeKnowledgeLinkedFragmentIds,
          window.activeKnowledgeLinkedTitle || '',
          window.activeKnowledgeLinkedFragmentRefs || []
        );
      }
    }catch(_){ }
    renderKfragRelations(workspace);
  }

  // ---------------------------------------------------------------------------
  // Knowledge Tree Context Menu (right click -> delete/cancel)

  function bindKnowledgeTreeContextMenu(){
    if(document.__ktConmenuBound) return;
    document.__ktConmenuBound = true;

    var menu = document.getElementById('kt-node-conmenu');
    if(!menu){
      menu = document.createElement('div');
      menu.id = 'kt-node-conmenu';
      menu.style.position = 'absolute';
      menu.style.zIndex = 99999;
      menu.style.padding = '6px';
      menu.style.border = '1px solid #ccc';
      menu.style.background = '#fff';
      menu.style.boxShadow = '0 2px 6px rgba(0,0,0,0.12)';
      menu.style.display = 'none';
      menu.style.fontSize = '13px';
      menu.style.borderRadius = '4px';

      var del = document.createElement('div');
      del.id = 'kt-conmenu-delete';
      del.textContent = '削除する';
      del.style.padding = '6px 10px';
      del.style.cursor = 'pointer';
      del.style.color = '#b30000';

      var cancel = document.createElement('div');
      cancel.id = 'kt-conmenu-cancel';
      cancel.textContent = 'キャンセル';
      cancel.style.padding = '6px 10px';
      cancel.style.cursor = 'pointer';
      cancel.style.color = '#333';

      menu.appendChild(del);
      menu.appendChild(cancel);
      document.body.appendChild(menu);
    }

    function hide(){
      try{ menu.style.display = 'none'; }catch(_){ }
      try{ menu._target = null; }catch(_){ }
    }

    // click outside -> hide
    document.addEventListener('mousedown', function(e){
      if(menu.style.display === 'block'){
        if(e.target !== menu && !menu.contains(e.target)) hide();
      }
    }, true);
    // esc -> hide
    document.addEventListener('keydown', function(e){
      if(e && e.key === 'Escape') hide();
    }, true);

    // right click in tree
    document.addEventListener('contextmenu', function(e){
      try{
        var tree = document.getElementById('overlay_knowledge_tree');
        if(!tree) return;
        if(!tree.contains(e.target)) return;
        var node = e.target && e.target.closest ? e.target.closest('.kt-node') : null;
        if(!node) return;
        e.preventDefault();
        e.stopPropagation();

        menu._target = node;
        var x = (e.pageX != null) ? e.pageX : (e.clientX + (document.documentElement.scrollLeft||document.body.scrollLeft));
        var y = (e.pageY != null) ? e.pageY : (e.clientY + (document.documentElement.scrollTop||document.body.scrollTop));
        menu.style.left = x + 'px';
        menu.style.top = y + 'px';
        menu.style.display = 'block';
      }catch(_){ }
    }, true);

    // delete
    var delBtn = document.getElementById('kt-conmenu-delete');
    var cancelBtn = document.getElementById('kt-conmenu-cancel');
    if(delBtn && !delBtn.__bound){
      delBtn.__bound = true;
      delBtn.addEventListener('click', function(){
        try{
          var target = menu._target;
          if(!target){ hide(); return; }
          var idAttr = target.getAttribute('data-node-id');
          var id = idAttr ? parseInt(idAttr, 10) : NaN;
          if(!id || isNaN(id) || id <= 0){
            if(window.alert) alert('このノードは削除できません（ID不正）');
            hide();
            return;
          }
          // prevent deleting the 3 guaranteed top nodes (common convention)
          if(id === 1 || id === 2 || id === 3){
            if(window.alert) alert('トップレベルのノードは削除できません');
            hide();
            return;
          }
          if(window.confirm && window.confirm('本当にこのノードを削除しますか？') !== true){
            hide();
            return;
          }

          window._ktNodeDeleteInProgress = window._ktNodeDeleteInProgress || {};
          if(window._ktNodeDeleteInProgress[id]){
            if(window.alert) alert('削除処理が進行中です');
            hide();
            return;
          }
          window._ktNodeDeleteInProgress[id] = true;

          var xhr = new XMLHttpRequest();
          var fd = new FormData();
          fd.append('node_id', String(id));
          xhr.open('POST', 'php/mark_delete_knowledge_node.php', true);
          xhr.onreadystatechange = function(){
            if(xhr.readyState !== 4) return;
            window._ktNodeDeleteInProgress[id] = false;
            try{
              if(xhr.status === 200){
                var r = JSON.parse(xhr.responseText || '{}');
                if(r && r.status === 'ok'){
                  loadKnowledgeTree();
                } else {
                  if(window.alert) alert('削除に失敗しました' + (r && r.message ? (': ' + r.message) : ''));
                }
              } else {
                if(window.alert) alert('通信エラーで削除できませんでした');
              }
            }catch(_){
              if(window.alert) alert('削除応答の処理でエラーが発生しました');
            }
          };
          xhr.send(fd);
        }catch(_){ }
        hide();
      }, false);
    }
    if(cancelBtn && !cancelBtn.__bound){
      cancelBtn.__bound = true;
      cancelBtn.addEventListener('click', function(){ hide(); }, false);
    }
  }

  function bindKnowledgeTreeAddNode(){
    var btn = document.getElementById('kt-add-node');
    if(!btn || btn.__bound) return;
    btn.__bound = true;
    btn.addEventListener('click', function(){
      var title = '';
      try{ title = window.prompt ? window.prompt('追加する項目名を入力してください') : ''; }catch(_){ title = ''; }
      if(title === null) return;
      title = String(title || '').trim();
      if(!title) return;

      // Default parent: currently selected tree node, if any. If none, create as root.
      var parentId = null;
      try{
        var sel = document.querySelector('#overlay_knowledge_tree .kt-node.is-selected');
        if(sel){
          var pid = sel.getAttribute('data-node-id');
          if(pid && String(pid).trim() !== '') parentId = String(pid).trim();
        }
      }catch(_){ }

      var fd = new FormData();
      fd.append('node_title', title);
      if(parentId) fd.append('parent_id', parentId);
      var gid = getSelectedGroupId();
      if(gid) fd.append('group_id', gid);

      var xhr = new XMLHttpRequest();
      xhr.open('POST', 'php/insert_knowledge_node.php', true);
      xhr.onreadystatechange = function(){
        if(xhr.readyState !== 4) return;
        if(xhr.status !== 200){
          try{ if(window.alert) alert('項目の作成に失敗しました'); }catch(_){ }
          return;
        }
        var res = null;
        try{ res = JSON.parse(xhr.responseText || '{}'); }catch(_){ res = null; }
        if(!res || res.status !== 'ok'){
          try{ if(window.alert) alert((res && res.message) ? res.message : '項目の作成に失敗しました'); }catch(_){ }
          return;
        }
        loadKnowledgeTree();
      };
      xhr.send(fd);
    }, false);
  }

  function getSelectedGroupId(){
    var sel = document.getElementById('group_select');
    if(!sel) return '';
    try{ return String(sel.value || '').trim(); }catch(_){ return ''; }
  }

  function clearFragmentSelectionState(workspace){
    try{
      window.activeKnowledgeFragmentId = null;
      window.activeKnowledgeFragmentSourceType = null;
      window.activeKnowledgeFragmentDiscussed = null;
      window.activeKnowledgeNoLinked = false;
      window.kfrag_add_selected = { ext: [], kfid: [] };
      window.kfrag_display_list = null;
      window.kfrag_add_select_mode = false;
      window.activeKnowledgeLinkedFragmentIds = [];
      window.activeKnowledgeLinkedFragmentRefs = [];
      window.activeKnowledgeLinkedTitle = '';
      var addBtn = document.getElementById('fragment-add-select-toggle');
      if(addBtn) addBtn.classList.remove('is-adding');
    }catch(_){ }
    if(workspace){
      try{ qsa('.knowledge_fragment.is-selected', workspace).forEach(function(el){ el.classList.remove('is-selected'); }); }catch(_){ }
      try{ updateAdditionalHighlight(workspace); }catch(_){ }
      try{ updateFragmentDiscussedIndicators(workspace); }catch(_){ }
      try{ clearKnowledgeConnectionOverlay(workspace); }catch(_){ }
    }
    try{ updateFragmentSelectedUI(); }catch(_){ }
    try{ updateDiscussedButton(); }catch(_){ }
  }

  function replaceFragmentListHTML(workspace, html){
    if(!workspace) return;
    // Remove old wrappers + list
    try{ qsa('.fragment-node-wrapper', workspace).forEach(function(w){ if(w && w.parentNode) w.parentNode.removeChild(w); }); }catch(_){ }
    try{ qsa('.knowledge-fragment-list', workspace).forEach(function(l){ if(l && l.parentNode) l.parentNode.removeChild(l); }); }catch(_){ }
    try{ qsa('.no-fragment-note', workspace).forEach(function(n){ if(n && n.parentNode) n.parentNode.removeChild(n); }); }catch(_){ }

    // Insert new list after the title row
    var title = qs('.overlay-title', workspace);
    var tmp = document.createElement('div');
    tmp.innerHTML = String(html || '');
    var list = tmp.querySelector('.knowledge-fragment-list') || null;
    if(!list){
      // fallback: if the response isn't wrapped, create wrapper
      list = document.createElement('div');
      list.className = 'knowledge-fragment-list';
      list.innerHTML = tmp.innerHTML;
    }
    if(title && title.parentNode === workspace){
      if(title.nextSibling) workspace.insertBefore(list, title.nextSibling);
      else workspace.appendChild(list);
    } else {
      workspace.appendChild(list);
    }
  }

  function reloadFragmentsForGroup(workspace, done){
    if(!workspace){ if(done) done(false); return; }
    var gid = getSelectedGroupId();
    var url = 'php/get_knowledge_fragments_by_group.php';
    if(gid) url += '?group_id=' + encodeURIComponent(gid);
    var xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.onreadystatechange = function(){
      if(xhr.readyState !== 4) return;
      if(xhr.status !== 200){ if(done) done(false); return; }
      replaceFragmentListHTML(workspace, xhr.responseText || '');
      // Reset undo/redo history when the list changes
      try{ _kfragUndo.length = 0; _kfragRedo.length = 0; updateKfragButtons(); }catch(_){ }
      // Re-init workspace wrappers for new cards
      initFragmentWorkspace(workspace);
      loadKfragRelations(workspace);
      applyFragmentSourceFilter();
      clearFragmentSelectionState(workspace);
      loadDiscussion();
      if(done) done(true);
    };
    xhr.send(null);
  }

  function bindGroupSelectSync(workspace){
    var sel = document.getElementById('group_select');
    if(!sel || sel.__combBound) return;
    sel.__combBound = true;
    sel.addEventListener('change', function(){
      reloadFragmentsForGroup(workspace);
      loadKnowledgeTree();
    }, false);
    document.addEventListener('organizationalGroupChanged', function(){
      reloadFragmentsForGroup(workspace);
      loadKnowledgeTree();
    }, false);
    // Also refresh once on init so the fragment list matches the latest group selection behavior.
    reloadFragmentsForGroup(workspace);
  }

  // ---------------------------------------------------------------------------
  // Fragments Workspace (draggable nodes + reset/undo/redo + selection)

  var _kfragUndo = [];
  var _kfragRedo = [];
  // additional fragment selection (for combined discussion)
  window.kfrag_add_select_mode = false;
  window.kfrag_add_selected = window.kfrag_add_selected || { ext: [], kfid: [] };
  // When discussion is UNDERWAY, keep the selected fragment set fixed (fukushima-system style).
  window.kfrag_display_list = window.kfrag_display_list || null;
  // Avoid race conditions when restoring multi-fragment selection async.
  window._kfragRestoreSeq = window._kfragRestoreSeq || 0;
  window.activeKnowledgeFragmentSourceType = window.activeKnowledgeFragmentSourceType || null;
  window.activeKnowledgeNoLinked = window.activeKnowledgeNoLinked || false;

  function getActiveFragmentId(){
    return (typeof window.activeKnowledgeFragmentId !== 'undefined' && window.activeKnowledgeFragmentId !== null)
      ? window.activeKnowledgeFragmentId
      : null;
  }

  function getActiveFragmentSourceType(){
    return normalizeSourceType(window.activeKnowledgeFragmentSourceType || '');
  }

  function getSelectedFragmentRefs(){
    var refs = [];
    var primary = getActiveFragmentId();
    var primaryType = getActiveFragmentSourceType();
    if(primary !== null && !isNaN(primary) && primaryType){
      refs.push(makeKfragRef(primaryType, primary));
    }
    try{
      if(window.kfrag_add_selected && Array.isArray(window.kfrag_add_selected.kfid)){
        window.kfrag_add_selected.kfid.forEach(function(ref){
          var normalizedRef = splitKfragRefs(String(ref || '')).shift() || '';
          if(normalizedRef && refs.indexOf(normalizedRef) === -1) refs.push(normalizedRef);
        });
      } else if(primaryType === 'experience' && window.kfrag_add_selected && Array.isArray(window.kfrag_add_selected.ext)){
        window.kfrag_add_selected.ext.forEach(function(x){
          var ref = makeKfragRef('experience', x);
          if(ref && refs.indexOf(ref) === -1) refs.push(ref);
        });
      }
    }catch(_){ }
    return refs.filter(Boolean);
  }

  function findFragmentWrapper(workspace, sourceType, sourceId){
    if(!workspace || !sourceType || !sourceId) return null;
    return qs('.fragment-node-wrapper[data-source-type="'+sourceType+'"][data-source-id="'+String(sourceId)+'"]', workspace);
  }

  function findFragmentCard(workspace, sourceType, sourceId){
    if(!workspace || !sourceType || !sourceId) return null;
    return qs('.knowledge_fragment[data-source-type="'+sourceType+'"][data-source-id="'+String(sourceId)+'"]', workspace);
  }

  function getSelectedFragmentIds(){
    if(getActiveFragmentSourceType() && getActiveFragmentSourceType() !== 'experience'){
      return [];
    }
    // If discussion is underway, prefer the frozen display list.
    try{
      if(String(window.activeKnowledgeFragmentDiscussed || '').trim() === 'UNDERWAY' &&
         Array.isArray(window.kfrag_display_list) && window.kfrag_display_list.length){
        return window.kfrag_display_list.map(function(x){ return String(x); });
      }
    }catch(_){ }
    var ids = [];
    var primary = getActiveFragmentId();
    if(primary !== null && !isNaN(primary)) ids.push(String(primary));
    try{
      if(window.kfrag_add_selected && Array.isArray(window.kfrag_add_selected.ext)){
        window.kfrag_add_selected.ext.forEach(function(x){
          var s = String(x);
          if(s && ids.indexOf(s) === -1) ids.push(s);
        });
      }
    }catch(_){ }
    return ids;
  }

  function getSelectedFragmentSourceIds(){
    return getSelectedFragmentRefs().map(function(ref){
      var pos = ref.indexOf(':');
      return pos === -1 ? '' : String(ref.slice(pos + 1)).trim();
    }).filter(Boolean);
  }

  function updateAdditionalHighlight(workspace){
    try{
      qsa('.fragment-node-wrapper.additional-selected', workspace).forEach(function(w){ w.classList.remove('additional-selected'); });
      qsa('.fragment-node-wrapper.kfrag-selected-highlight', workspace).forEach(function(w){ w.classList.remove('kfrag-selected-highlight'); });
      var refs = getSelectedFragmentRefs();
      refs.forEach(function(ref, idx){
        var pos = ref.indexOf(':');
        if(pos === -1) return;
        var sourceType = normalizeSourceType(ref.slice(0, pos));
        var id = String(ref.slice(pos + 1)).trim();
        var w = findFragmentWrapper(workspace, sourceType, id);
        if(w){
          w.classList.add('kfrag-selected-highlight');
          if(idx > 0) w.classList.add('additional-selected');
        }
      });
      updateKnowledgeTreeReverseHighlights(refs);
    }catch(_){ }
  }

  function splitKfragIds(value){
    return String(value || '').split(',').map(function(x){ return String(x || '').trim(); }).filter(function(x){
      return x !== '' && !isNaN(parseInt(x, 10));
    }).filter(function(x, i, arr){
      return arr.indexOf(x) === i;
    });
  }

  function makeKfragRef(sourceType, id){
    var normalizedType = normalizeSourceType(sourceType);
    var normalizedId = String(id || '').trim();
    if(!normalizedType || !normalizedId || isNaN(parseInt(normalizedId, 10))) return '';
    return normalizedType + ':' + normalizedId;
  }

  function splitKfragRefs(value){
    return String(value || '').split(',').map(function(part){
      var raw = String(part || '').trim();
      if(!raw) return '';
      var pos = raw.indexOf(':');
      if(pos === -1) return '';
      return makeKfragRef(raw.slice(0, pos), raw.slice(pos + 1));
    }).filter(Boolean).filter(function(ref, index, arr){
      return arr.indexOf(ref) === index;
    });
  }

  function getNodeKfragRefs(nodeEl){
    if(!nodeEl) return [];
    var refs = splitKfragRefs(nodeEl.getAttribute('data-kfrag-refs'));
    if(refs.length) return refs;
    var ids = splitKfragIds(nodeEl.getAttribute('data-kfrag-id'));
    var sourceTypes = parseSourceTypes(nodeEl.getAttribute('data-source-types'), ids.length ? 'experience' : '');
    var fallbackType = sourceTypes[0] || 'experience';
    return ids.map(function(id){ return makeKfragRef(fallbackType, id); }).filter(Boolean);
  }

  function updateKnowledgeTreeReverseHighlights(refs){
    var tree = document.getElementById('overlay_knowledge_tree');
    if(!tree) return;
    refs = (refs || []).map(function(x){ return String(x); }).filter(Boolean);
    qsa('.kt-node.kfrag-reverse-linked', tree).forEach(function(node){ node.classList.remove('kfrag-reverse-linked'); });
    qsa('.kt-node.kfrag-reverse-primary', tree).forEach(function(node){ node.classList.remove('kfrag-reverse-primary'); });
    if(!refs.length) return;

    var primary = refs[0];
    qsa('.kt-node[data-kfrag-id]', tree).forEach(function(node){
      var nodeRefs = getNodeKfragRefs(node);
      var matched = nodeRefs.some(function(ref){ return refs.indexOf(String(ref)) !== -1; });
      if(!matched) return;
      node.classList.add('kfrag-reverse-linked');
      if(primary && nodeRefs.indexOf(String(primary)) !== -1){
        node.classList.add('kfrag-reverse-primary');
      }
      var p = node.parentNode;
      while(p && p !== tree){
        if(p.classList && p.classList.contains('kt-children')){
          p.style.display = 'block';
          var parentNode = p.parentNode;
          var tg = parentNode ? qs(':scope > .kt-toggle', parentNode) : null;
          if(tg) tg.textContent = '-';
        }
        p = p.parentNode;
      }
    });
  }

  function clearKnowledgeConnectionOverlay(workspace){
    workspace = workspace || document.getElementById('knowledge_fragments_workspace');
    if(!workspace) return;
    try{ qsa('.fragment-node-wrapper.knowledge-linked-highlight', workspace).forEach(function(w){ w.classList.remove('knowledge-linked-highlight'); }); }catch(_){ }
    try{
      var svg = qs('.kfrag-link-overlay', workspace);
      if(svg) svg.innerHTML = '';
    }catch(_){ }
    try{
      var summary = qs('.kfrag-connection-summary', workspace);
      if(summary) summary.parentNode.removeChild(summary);
    }catch(_){ }
  }

  function updateKnowledgeConnectionOverlay(workspace, ids, titleText, refs){
    if(!workspace) return;
    clearKnowledgeConnectionOverlay(workspace);
    ids = (ids || []).map(function(x){ return String(x); }).filter(Boolean);
    refs = (refs || []).map(function(ref){ return String(ref || '').trim(); }).filter(Boolean);
    if(!ids.length && !refs.length) return;
    try{
      window.activeKnowledgeLinkedFragmentIds = ids.slice();
      window.activeKnowledgeLinkedFragmentRefs = refs.slice();
      window.activeKnowledgeLinkedTitle = String(titleText || '');
    }catch(_){ }

    var list = qs('.knowledge-fragment-list', workspace);
    if(!list) return;
    var wrappers = [];
    var seen = {};
    var effectiveRefs = refs.length ? refs : ids.map(function(id){ return makeKfragRef('experience', id); }).filter(Boolean);
    effectiveRefs.forEach(function(ref){
      var pos = ref.indexOf(':');
      if(pos === -1) return;
      var sourceType = normalizeSourceType(ref.slice(0, pos));
      var id = String(ref.slice(pos + 1)).trim();
      if(!sourceType || !id) return;
      var key = sourceType + ':' + id;
      if(seen[key]) return;
      seen[key] = true;
      var w = findFragmentWrapper(list, sourceType, id);
      if(w){
        w.classList.add('knowledge-linked-highlight');
        wrappers.push(w);
      }
    });
    if(!wrappers.length) return;

    var summary = document.createElement('div');
    summary.className = 'kfrag-connection-summary';
    summary.textContent = 'Linked KF: ' + (effectiveRefs.length ? effectiveRefs.join(', ') : ids.join(', '));
    if(titleText && String(titleText).trim() !== ''){
      summary.setAttribute('title', String(titleText).trim());
    }
    list.insertBefore(summary, list.firstChild);

    if(!workspace.classList.contains('is-canvas-mode')) return;

    var svg = qs('.kfrag-link-overlay', list);
    if(!svg){
      svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      svg.setAttribute('class', 'kfrag-link-overlay');
      list.insertBefore(svg, list.firstChild);
    }
    svg.innerHTML = '';

    var listRect = list.getBoundingClientRect();
    var zoom = getKfragCanvasZoomValue(workspace);
    var width = Math.max(list.scrollWidth * zoom, list.clientWidth * zoom, 1);
    var height = Math.max(list.scrollHeight * zoom, list.clientHeight * zoom, 1);
    svg.setAttribute('width', String(width));
    svg.setAttribute('height', String(height));
    svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);
    svg.style.zoom = String(1 / zoom);

    var points = wrappers.map(function(w){
      var r = w.getBoundingClientRect();
      return {
        x: (r.left - listRect.left) + (list.scrollLeft * zoom) + (r.width / 2),
        y: (r.top - listRect.top) + (list.scrollTop * zoom) + (r.height / 2)
      };
    });

    for(var i=0; i<points.length - 1; i++){
      var line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
      line.setAttribute('x1', String(points[i].x));
      line.setAttribute('y1', String(points[i].y));
      line.setAttribute('x2', String(points[i+1].x));
      line.setAttribute('y2', String(points[i+1].y));
      line.setAttribute('class', 'kfrag-link-line');
      svg.appendChild(line);
    }
    points.forEach(function(p){
      var dot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
      dot.setAttribute('cx', String(p.x));
      dot.setAttribute('cy', String(p.y));
      dot.setAttribute('r', '5');
      dot.setAttribute('class', 'kfrag-link-dot');
      svg.appendChild(dot);
    });
  }

  function updateFragmentDiscussedIndicators(workspace){
    if(!workspace) return;
    qsa('.fragment-node-wrapper', workspace).forEach(function(w){
      var card = qs('.knowledge_fragment', w);
      if(!card) return;
      var s = String(card.getAttribute('data-discussed') || '').trim();
      var ind = qs('.fragment-discussed-indicator', w);
      if(s === 'UNDERWAY'){
        if(!ind){
          ind = document.createElement('div');
          ind.className = 'fragment-discussed-indicator';
          ind.setAttribute('aria-hidden', 'true');
          ind.textContent = '議論中';
          w.appendChild(ind);
        } else {
          ind.textContent = '議論中';
        }
      } else {
        if(ind && ind.parentNode){ ind.parentNode.removeChild(ind); }
      }
    });
  }

  function _normalizeIdList(primaryId, arr){
    var p = (primaryId !== null && typeof primaryId !== 'undefined') ? String(primaryId) : '';
    var out = [];
    if(p) out.push(p);
    try{
      (arr || []).forEach(function(x){
        var s = String(x);
        if(!s || s === p) return;
        if(out.indexOf(s) === -1) out.push(s);
      });
    }catch(_){ }
    return out;
  }

  function restoreDiscussionTargetsForPrimary(primaryId, workspace, discussedStatus, done){
    if(primaryId === null || typeof primaryId === 'undefined' || String(primaryId).trim() === ''){ if(done) done(false); return; }
    var fid = parseInt(primaryId, 10);
    if(!fid || isNaN(fid) || fid <= 0){ if(done) done(false); return; }

    var seq = ++window._kfragRestoreSeq;
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'php/get_discussion_targets.php?fragment_id=' + encodeURIComponent(String(fid)), true);
    xhr.onreadystatechange = function(){
      if(xhr.readyState !== 4) return;
      if(seq !== window._kfragRestoreSeq){ if(done) done(false); return; } // stale
      if(getActiveFragmentId() !== fid){ if(done) done(false); return; } // selection changed

      if(xhr.status !== 200){ if(done) done(false); return; }
      var data = null;
      try{ data = JSON.parse(xhr.responseText || '{}'); }catch(_){ data = null; }
      if(!data || data.status !== 'ok' || !Array.isArray(data.targets)){ if(done) done(false); return; }

      var ids = _normalizeIdList(fid, data.targets.map(function(x){ return String(x); }));
      window.kfrag_add_selected = window.kfrag_add_selected || { ext: [], kfid: [] };
      window.kfrag_add_selected.ext = ids.slice(1);
      window.kfrag_add_selected.kfid = ids.slice(1).map(function(id){ return makeKfragRef('experience', id); }).filter(Boolean);

      // When UNDERWAY, lock the selection set to the latest stored target list.
      if(String(discussedStatus || '').trim() === 'UNDERWAY'){
        window.kfrag_display_list = ids.slice();
      }

      updateFragmentSelectedUI();
      if(workspace) updateAdditionalHighlight(workspace);
      updateDiscussedButton();
      if(done) done(true);
    };
    xhr.send(null);
  }

  function autoRestoreUnderwayTargets(workspace, done){
    // If something is already selected, do nothing.
    if(getActiveFragmentId() !== null){ if(done) done(false); return; }
    if(!workspace){ if(done) done(false); return; }

    // Find an UNDERWAY fragment card and restore its last target set from discussion_history.
    var cards = qsa('.knowledge_fragment', workspace);
    var underway = null;
    cards.some(function(c){
      var s = String(c.getAttribute('data-discussed') || '').trim();
      if(s === 'UNDERWAY'){ underway = c; return true; }
      return false;
    });
    if(!underway){ if(done) done(false); return; }

    var fid = underway.getAttribute('data-ext-id');
    if(!fid){ if(done) done(false); return; }

    // Make it the active primary selection.
    window.kfrag_add_selected = { ext: [], kfid: [] };
    window.kfrag_display_list = null;
    qsa('.knowledge_fragment.is-selected', workspace).forEach(function(el){ el.classList.remove('is-selected'); });
    underway.classList.add('is-selected');
    setActiveFragment(fid, {
      discussed: 'UNDERWAY',
      title: (qs('.card-body', underway) ? qs('.card-body', underway).textContent.trim() : null)
    });
    updateAdditionalHighlight(workspace);

    restoreDiscussionTargetsForPrimary(fid, workspace, 'UNDERWAY', function(ok){
      if(done) done(!!ok);
    });
  }

  function setActiveFragment(id, meta){
    try{ window.activeKnowledgeFragmentId = (id !== null ? parseInt(id, 10) : null); }catch(_){ window.activeKnowledgeFragmentId = null; }
    try{ window.activeKnowledgeFragmentSourceType = (meta && meta.sourceType) ? normalizeSourceType(meta.sourceType) : null; }catch(_){ window.activeKnowledgeFragmentSourceType = null; }
    try{ window.activeKnowledgeNoLinked = !!(meta && meta.noLinked); }catch(_){ window.activeKnowledgeNoLinked = false; }
    // store discussed status (for UI)
    try{
      if(meta && typeof meta.discussed !== 'undefined'){ window.activeKnowledgeFragmentDiscussed = meta.discussed; }
    }catch(_){ }
    updateFragmentSelectedUI(meta);
    updateDiscussedButton();
  }

  function updateFragmentSelectedUI(meta){
    var box = document.getElementById('fragment-selected-list');
    if(!box) return;
    box.innerHTML = '';

    var refs = getSelectedFragmentRefs();
    if(refs.length === 0){
      var empty = document.createElement('div');
      empty.className = 'selected-empty';
      empty.textContent = window.activeKnowledgeNoLinked ? '--' : '未選択';
      box.appendChild(empty);
      return;
    }
    refs.forEach(function(ref, idx){
      var pos = ref.indexOf(':');
      var sourceType = pos === -1 ? '' : normalizeSourceType(ref.slice(0, pos));
      var id = pos === -1 ? ref : ref.slice(pos + 1);
      var item = document.createElement('span');
      item.className = 'fragment-selected-item' + (idx === 0 ? ' primary' : '');
      item.setAttribute('data-kfid', id);
      item.setAttribute('data-source-type', sourceType || '');
      item.textContent = '#' + id + (sourceType ? ' [' + sourceType + ']' : '');
      if(idx > 0){
        var rm = document.createElement('span');
        rm.className = 'remove-btn';
        rm.title = '選択解除';
        rm.textContent = '×';
        item.appendChild(rm);
      }
      box.appendChild(item);
    });
  }

  function updateKfragButtons(){
    var undoBtn = document.getElementById('kfrag-undo');
    var redoBtn = document.getElementById('kfrag-redo');
    if(undoBtn) undoBtn.disabled = (_kfragUndo.length === 0);
    if(redoBtn) redoBtn.disabled = (_kfragRedo.length === 0);
  }

  function snapshotPositions(workspace){
    var snap = {};
    qsa('.fragment-node-wrapper', workspace).forEach(function(w){
      var id = w.getAttribute('data-ext-id');
      if(!id) return;
      var left = parseFloat(w.style.left || w.getAttribute('data-canvas-x') || '0') || 0;
      var top = parseFloat(w.style.top || w.getAttribute('data-canvas-y') || '0') || 0;
      snap[id] = { left: left, top: top };
    });
    return snap;
  }

  function applyPositions(workspace, snap){
    if(!snap) return;
    qsa('.fragment-node-wrapper', workspace).forEach(function(w){
      var id = w.getAttribute('data-ext-id');
      if(!id || !snap[id]) return;
      var left = parseFloat(snap[id].left || 0) || 0;
      var top = parseFloat(snap[id].top || 0) || 0;
      w.setAttribute('data-canvas-x', String(left));
      w.setAttribute('data-canvas-y', String(top));
      if(workspace.classList.contains('is-canvas-mode')){
        w.style.left = left + 'px';
        w.style.top = top + 'px';
      }
    });
    saveFragmentCanvasPositions(workspace);
  }

  function pushHistory(workspace, beforeSnap){
    var after = snapshotPositions(workspace);
    // If nothing changed, do nothing
    var changed = false;
    Object.keys(after).some(function(k){
      var a = after[k];
      var b = beforeSnap && beforeSnap[k];
      if(!b || a.left !== b.left || a.top !== b.top){ changed = true; return true; }
      return false;
    });
    if(!changed) return;
    _kfragUndo.push(beforeSnap);
    _kfragRedo = [];
    updateKfragButtons();
  }

  function defaultLayout(workspace){
    var wrappers = qsa('.fragment-node-wrapper', workspace);
    wrappers.forEach(function(w){
      w.style.left = '';
      w.style.top = '';
    });
  }

  function hasStoredCanvasPosition(w){
    return w && w.hasAttribute('data-canvas-x') && w.hasAttribute('data-canvas-y');
  }

  function buildAutoCanvasPosition(index, listWidth){
    var cardW = 250;
    var gap = 34;
    var cols = Math.max(1, Math.floor((Math.max(listWidth || 0, cardW) + gap) / (cardW + gap)));
    return {
      left: gap + (index % cols) * (cardW + gap),
      top: gap + Math.floor(index / cols) * 186
    };
  }

  function applyCanvasLayout(workspace, forceReset){
    var list = qs('.knowledge-fragment-list', workspace);
    if(!list) return;
    var rect = list.getBoundingClientRect();
    var wrappers = qsa('.fragment-node-wrapper', list);
    var maxBottom = 0;
    wrappers.forEach(function(w, idx){
      var pos = null;
      if(!forceReset && hasStoredCanvasPosition(w)){
        pos = {
          left: parseFloat(w.getAttribute('data-canvas-x') || '0') || 0,
          top: parseFloat(w.getAttribute('data-canvas-y') || '0') || 0
        };
      } else {
        pos = buildAutoCanvasPosition(idx, rect.width || list.clientWidth || 0);
        w.setAttribute('data-canvas-x', String(pos.left));
        w.setAttribute('data-canvas-y', String(pos.top));
      }
      w.style.position = 'absolute';
      w.style.left = pos.left + 'px';
      w.style.top = pos.top + 'px';
      w.style.width = '';
      var h = w.offsetHeight || 128;
      maxBottom = Math.max(maxBottom, pos.top + h + 24);
    });
    list.style.minHeight = Math.max(260, maxBottom) + 'px';
    if(forceReset) saveFragmentCanvasPositions(workspace);
  }

  function getCanvasPositions(workspace){
    var out = [];
    qsa('.fragment-node-wrapper', workspace).forEach(function(w){
      var id = w.getAttribute('data-ext-id');
      if(!id) return;
      out.push({
        id: id,
        x: parseFloat(w.getAttribute('data-canvas-x') || w.style.left || '0') || 0,
        y: parseFloat(w.getAttribute('data-canvas-y') || w.style.top || '0') || 0
      });
    });
    return out;
  }

  function getFragmentOrder(workspace){
    return qsa('.fragment-node-wrapper', workspace).map(function(w){
      return w.getAttribute('data-ext-id') || '';
    }).filter(function(id){
      return String(id).trim() !== '';
    });
  }

  function saveFragmentOrder(workspace){
    var order = getFragmentOrder(workspace);
    if(!order.length) return;

    var xhr = new XMLHttpRequest();
    var fd = new FormData();
    fd.append('order', JSON.stringify(order));
    var gid = getSelectedGroupId();
    if(gid) fd.append('group_id', gid);
    xhr.open('POST', 'php/save_fragment_order.php', true);
    xhr.onreadystatechange = function(){
      if(xhr.readyState !== 4) return;
      if(xhr.status !== 200){
        try{ console.warn('knowledge_fragment order save failed', xhr.status, xhr.responseText || ''); }catch(_){ }
        return;
      }
      try{
        var res = JSON.parse(xhr.responseText || '{}');
        if(!res || res.status !== 'ok'){
          try{ console.warn('knowledge_fragment order save error', res); }catch(_){ }
        }
      }catch(err){
        try{ console.warn('knowledge_fragment order save parse error', err, xhr.responseText || ''); }catch(_){ }
      }
    };
    xhr.send(fd);
  }

  function saveFragmentCanvasPositions(workspace){
    var positions = getCanvasPositions(workspace);
    if(!positions.length) return;

    var xhr = new XMLHttpRequest();
    var fd = new FormData();
    fd.append('positions', JSON.stringify(positions));
    var gid = getSelectedGroupId();
    if(gid) fd.append('group_id', gid);
    xhr.open('POST', 'php/save_fragment_order.php', true);
    xhr.onreadystatechange = function(){
      if(xhr.readyState !== 4) return;
      if(xhr.status !== 200){
        try{ console.warn('knowledge_fragment position save failed', xhr.status, xhr.responseText || ''); }catch(_){ }
        return;
      }
      try{
        var res = JSON.parse(xhr.responseText || '{}');
        if(!res || res.status !== 'ok'){
          try{ console.warn('knowledge_fragment position save error', res); }catch(_){ }
        }
      }catch(err){
        try{ console.warn('knowledge_fragment position save parse error', err, xhr.responseText || ''); }catch(_){ }
      }
    };
    xhr.send(fd);
  }

  function normalizeRelationPair(a, b){
    var as = String(a || '').trim();
    var bs = String(b || '').trim();
    if(!as || !bs || as === bs) return null;
    return as < bs ? { from: as, to: bs } : { from: bs, to: as };
  }

  function getRelationPairsFromRefs(refs){
    refs = (refs || []).map(function(x){ return String(x); }).filter(Boolean);
    if(refs.length < 2) return [];
    var primary = refs[0];
    var seen = {};
    var pairs = [];
    refs.slice(1).forEach(function(id){
      var p = normalizeRelationPair(primary, id);
      if(!p) return;
      var key = p.from + '-' + p.to;
      if(seen[key]) return;
      seen[key] = true;
      pairs.push(p);
    });
    return pairs;
  }

  function loadKfragRelations(workspace){
    workspace = workspace || document.getElementById('knowledge_fragments_workspace');
    if(!workspace) return;
    var knowledgeRelations = [];
    qsa('#overlay_knowledge_tree .kt-node[data-kfrag-refs]').forEach(function(node){
      var refs = getNodeKfragRefs(node);
      getRelationPairsFromRefs(refs).forEach(function(pair){
        knowledgeRelations.push({
          from: pair.from,
          to: pair.to,
          relation_scope: 'knowledge'
        });
      });
    });
    var gid = getSelectedGroupId();
    var url = 'php/get_underway_kfrag_relations.php';
    if(gid) url += '?group_id=' + encodeURIComponent(gid);
    var xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.onreadystatechange = function(){
      if(xhr.readyState !== 4) return;
      var discussionRelations = [];
      if(xhr.status === 200){
        var res = null;
        try{ res = JSON.parse(xhr.responseText || '{}'); }catch(_){ res = null; }
        if(res && res.status === 'ok' && Array.isArray(res.relations)){
          discussionRelations = res.relations.map(function(rel){
            return {
              from: String(rel.from || ''),
              to: String(rel.to || ''),
              relation_scope: 'discussion'
            };
          }).filter(function(rel){ return rel.from && rel.to; });
        }
      }
      window.kfragRelations = knowledgeRelations.concat(discussionRelations);
      var res = null;
      renderKfragRelations(workspace);
    };
    xhr.send(null);
  }

  function clearKfragRelationOverlay(workspace){
    workspace = workspace || document.getElementById('knowledge_fragments_workspace');
    if(!workspace) return;
    try{
      var svg = qs('.kfrag-relation-overlay', workspace);
      if(svg) svg.innerHTML = '';
    }catch(_){ }
    try{ qsa('.fragment-node-wrapper.has-kfrag-relation', workspace).forEach(function(w){ w.classList.remove('has-kfrag-relation'); }); }catch(_){ }
  }

  function renderKfragRelations(workspace){
    workspace = workspace || document.getElementById('knowledge_fragments_workspace');
    if(!workspace) return;
    clearKfragRelationOverlay(workspace);
    var relations = Array.isArray(window.kfragRelations) ? window.kfragRelations : [];
    if(!relations.length) return;
    var list = qs('.knowledge-fragment-list', workspace);
    if(!list) return;
    if(!kfragRelationVisible) return;

    relations.forEach(function(rel){
      var a = String(rel.from_fragment_id || rel.from || rel.source_id || '');
      var b = String(rel.to_fragment_id || rel.to || rel.target_id || '');
      var pa = a.indexOf(':');
      var pb = b.indexOf(':');
      var wa = pa === -1 ? null : findFragmentWrapper(list, normalizeSourceType(a.slice(0, pa)), a.slice(pa + 1));
      var wb = pb === -1 ? null : findFragmentWrapper(list, normalizeSourceType(b.slice(0, pb)), b.slice(pb + 1));
      if(wa) wa.classList.add('has-kfrag-relation');
      if(wb) wb.classList.add('has-kfrag-relation');
    });

    if(!workspace.classList.contains('is-canvas-mode')) return;
    var svg = qs('.kfrag-relation-overlay', list);
    if(!svg){
      svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      svg.setAttribute('class', 'kfrag-relation-overlay');
      list.insertBefore(svg, list.firstChild);
    }
    svg.innerHTML = '';

    var listRect = list.getBoundingClientRect();
    var zoom = getKfragCanvasZoomValue(workspace);
    var width = Math.max(list.scrollWidth * zoom, list.clientWidth * zoom, 1);
    var height = Math.max(list.scrollHeight * zoom, list.clientHeight * zoom, 1);
    svg.setAttribute('width', String(width));
    svg.setAttribute('height', String(height));
    svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);
    svg.style.zoom = String(1 / zoom);

    relations.forEach(function(rel){
      var a = String(rel.from_fragment_id || rel.from || rel.source_id || '');
      var b = String(rel.to_fragment_id || rel.to || rel.target_id || '');
      var pa = a.indexOf(':');
      var pb = b.indexOf(':');
      var wa = pa === -1 ? null : findFragmentWrapper(list, normalizeSourceType(a.slice(0, pa)), a.slice(pa + 1));
      var wb = pb === -1 ? null : findFragmentWrapper(list, normalizeSourceType(b.slice(0, pb)), b.slice(pb + 1));
      if(!wa || !wb) return;
      var ra = wa.getBoundingClientRect();
      var rb = wb.getBoundingClientRect();
      var x1 = (ra.left - listRect.left) + (list.scrollLeft * zoom) + (ra.width / 2);
      var y1 = (ra.top - listRect.top) + (list.scrollTop * zoom) + (ra.height / 2);
      var x2 = (rb.left - listRect.left) + (list.scrollLeft * zoom) + (rb.width / 2);
      var y2 = (rb.top - listRect.top) + (list.scrollTop * zoom) + (rb.height / 2);
      var line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
      line.setAttribute('x1', String(x1));
      line.setAttribute('y1', String(y1));
      line.setAttribute('x2', String(x2));
      line.setAttribute('y2', String(y2));
      line.setAttribute('class', 'kfrag-relation-line');
      svg.appendChild(line);
    });
  }

  function getFragmentDisplayNumber(wrapper){
    if(!wrapper) return 0;
    var card = qs('.knowledge_fragment', wrapper);
    var raw = card ? card.getAttribute('data-kfrag-num') : '';
    if(!raw){
      var badge = qs('.fragment-number-badge', wrapper);
      raw = badge ? badge.textContent : '';
    }
    var num = parseInt(String(raw || '').trim(), 10);
    return isNaN(num) ? 0 : num;
  }

  function resetFragmentOrderByInitialNumber(workspace){
    if(!workspace) return;
    var list = qs('.knowledge-fragment-list', workspace);
    if(!list) return;
    var wrappers = qsa('.fragment-node-wrapper', list).map(function(w, idx){
      return { wrapper: w, num: getFragmentDisplayNumber(w), idx: idx };
    });
    if(wrappers.length === 0) return;

    wrappers.sort(function(a, b){
      if(a.num !== b.num) return b.num - a.num;
      return a.idx - b.idx;
    });
    wrappers.forEach(function(item){
      item.wrapper.style.left = '';
      item.wrapper.style.top = '';
      list.appendChild(item.wrapper);
    });
    updateAdditionalHighlight(workspace);
    saveFragmentOrder(workspace);
  }

  function bindFragmentReorder(workspace){
    if(!workspace || workspace.__fragmentReorderBound) return;
    workspace.__fragmentReorderBound = true;

    var candidate = null;
    var dragging = null;
    var placeholder = null;
    var startX = 0;
    var startY = 0;
    var offsetX = 0;
    var offsetY = 0;
    var moved = false;

    function getList(){
      return qs('.knowledge-fragment-list', workspace);
    }

    function resetDrag(){
      if(dragging){
        dragging.classList.remove('is-reordering');
        dragging.style.position = '';
        dragging.style.left = '';
        dragging.style.top = '';
        dragging.style.width = '';
        dragging.style.zIndex = '';
        dragging.style.pointerEvents = '';
      }
      if(placeholder && placeholder.parentNode){
        placeholder.parentNode.removeChild(placeholder);
      }
      candidate = null;
      dragging = null;
      placeholder = null;
      moved = false;
      document.body.classList.remove('is-fragment-reordering');
    }

    function getInsertBefore(list, clientY){
      var items = qsa('.fragment-node-wrapper', list).filter(function(item){
        return item !== dragging;
      });
      var closest = { offset: Number.NEGATIVE_INFINITY, element: null };
      items.forEach(function(item){
        var box = item.getBoundingClientRect();
        var offset = clientY - box.top - (box.height / 2);
        if(offset < 0 && offset > closest.offset){
          closest = { offset: offset, element: item };
        }
      });
      return closest.element;
    }

    function startDrag(ev){
      if(!candidate || dragging) return;
      dragging = candidate.wrapper;
      moved = true;

      var rect = dragging.getBoundingClientRect();
      offsetX = ev.clientX - rect.left;
      offsetY = ev.clientY - rect.top;

      placeholder = document.createElement('div');
      placeholder.className = 'fragment-drop-placeholder';
      placeholder.style.height = rect.height + 'px';
      dragging.parentNode.insertBefore(placeholder, dragging.nextSibling);

      dragging.classList.add('is-reordering');
      dragging.style.position = 'fixed';
      dragging.style.left = rect.left + 'px';
      dragging.style.top = rect.top + 'px';
      dragging.style.width = rect.width + 'px';
      dragging.style.zIndex = '10030';
      dragging.style.pointerEvents = 'none';
      document.body.classList.add('is-fragment-reordering');
    }

    function moveDrag(ev){
      if(!candidate) return;
      var dx = ev.clientX - startX;
      var dy = ev.clientY - startY;
      if(!dragging && Math.sqrt(dx * dx + dy * dy) < 6) return;
      if(!dragging) startDrag(ev);
      if(!dragging) return;

      ev.preventDefault();
      dragging.style.left = (ev.clientX - offsetX) + 'px';
      dragging.style.top = (ev.clientY - offsetY) + 'px';

      var list = getList();
      if(!list) return;
      var before = getInsertBefore(list, ev.clientY);
      if(before){
        list.insertBefore(placeholder, before);
      } else {
        list.appendChild(placeholder);
      }
    }

    function endDrag(ev){
      document.removeEventListener('mousemove', moveDrag, true);
      document.removeEventListener('mouseup', endDrag, true);
      if(dragging && placeholder && placeholder.parentNode){
        placeholder.parentNode.insertBefore(dragging, placeholder);
        updateAdditionalHighlight(workspace);
        saveFragmentOrder(workspace);
        try{ ev.preventDefault(); ev.stopPropagation(); }catch(_){ }
      }
      var suppressClick = moved;
      resetDrag();
      if(suppressClick){
        workspace.__suppressNextFragmentClick = true;
        setTimeout(function(){ workspace.__suppressNextFragmentClick = false; }, 0);
      }
    }

    workspace.addEventListener('mousedown', function(ev){
      if(workspace.classList.contains('is-canvas-mode')) return;
      if(ev.button !== 0) return;
      if(ev.target && ev.target.closest && ev.target.closest('button, input, textarea, select, a')) return;
      var list = getList();
      if(!list) return;
      var wrapper = ev.target && ev.target.closest ? ev.target.closest('.fragment-node-wrapper') : null;
      if(!wrapper || !list.contains(wrapper)) return;
      if(String(wrapper.getAttribute('data-source-type') || 'experience') !== 'experience') return;
      candidate = { wrapper: wrapper };
      startX = ev.clientX;
      startY = ev.clientY;
      document.addEventListener('mousemove', moveDrag, true);
      document.addEventListener('mouseup', endDrag, true);
    }, false);
  }

  function bindKfragActions(workspace){
    var resetBtn = document.getElementById('kfrag-reset');
    var undoBtn = document.getElementById('kfrag-undo');
    var redoBtn = document.getElementById('kfrag-redo');
    var relationToggle = document.getElementById('kfrag-toggle-relations');

    if(resetBtn){
      resetBtn.addEventListener('click', function(){
        if(workspace.classList.contains('is-canvas-mode')){
          var before = snapshotPositions(workspace);
          applyCanvasLayout(workspace, true);
          pushHistory(workspace, before);
        } else {
          resetFragmentOrderByInitialNumber(workspace);
        }
      }, false);
    }
    if(undoBtn){
      undoBtn.addEventListener('click', function(){
        if(_kfragUndo.length === 0) return;
        var cur = snapshotPositions(workspace);
        var prev = _kfragUndo.pop();
        _kfragRedo.push(cur);
        applyPositions(workspace, prev);
        updateKfragButtons();
      }, false);
    }
    if(redoBtn){
      redoBtn.addEventListener('click', function(){
        if(_kfragRedo.length === 0) return;
        var cur = snapshotPositions(workspace);
        var next = _kfragRedo.pop();
        _kfragUndo.push(cur);
        applyPositions(workspace, next);
        updateKfragButtons();
      }, false);
    }
    if(relationToggle && !relationToggle.__bound){
      relationToggle.__bound = true;
      relationToggle.addEventListener('click', function(){
        kfragRelationVisible = !kfragRelationVisible;
        updateKfragRelationToggleButtons();
        renderKfragRelations(workspace);
      }, false);
    }
    updateKfragRelationToggleButtons();
    updateKfragButtons();
  }

  function toggleAdditionalSelection(workspace, extId){
    if(!extId) return;
    var sourceType = getActiveFragmentSourceType() || 'experience';
    var primary = getActiveFragmentId();
    var s = String(extId);
    if(primary !== null && String(primary) === s) return;
    window.kfrag_add_selected = window.kfrag_add_selected || { ext: [], kfid: [] };
    var ref = makeKfragRef(sourceType, s);
    var arr = window.kfrag_add_selected.kfid;
    if(!Array.isArray(arr)) arr = window.kfrag_add_selected.kfid = [];
    if(sourceType === 'experience'){
      window.kfrag_add_selected.ext = window.kfrag_add_selected.ext || [];
    }
    var idx = arr.indexOf(ref);
    if(idx === -1){
      arr.push(ref);
      if(sourceType === 'experience' && Array.isArray(window.kfrag_add_selected.ext) && window.kfrag_add_selected.ext.indexOf(s) === -1){
        window.kfrag_add_selected.ext.push(s);
      }
    } else {
      arr.splice(idx,1);
      if(sourceType === 'experience' && Array.isArray(window.kfrag_add_selected.ext)){
        window.kfrag_add_selected.ext = window.kfrag_add_selected.ext.filter(function(x){ return String(x) !== s; });
      }
    }
    updateFragmentSelectedUI();
    updateAdditionalHighlight(workspace);
  }

  function initFragmentWorkspace(workspace){
    // Ensure each .knowledge_fragment is contained by .fragment-node-wrapper
    // inside .knowledge-fragment-list. PHP emits this structure; JS keeps
    // a fallback for older/unwrapped responses.

    ensureKfragViewControls(workspace);
    try{ workspace.style.position = 'relative'; }catch(_){ }

    var cards = qsa('.knowledge_fragment', workspace);
    if(cards.length === 0) return;

    cards.forEach(function(card){
      var extId = card.getAttribute('data-ext-id') || '';
      var sourceId = card.getAttribute('data-source-id') || extId || '';
      var num = card.getAttribute('data-kfrag-num') || '';
      var wrap = card.closest && card.closest('.fragment-node-wrapper');
      if(!wrap){
        wrap = document.createElement('div');
        wrap.className = 'fragment-node-wrapper';
        var badge = document.createElement('div');
        badge.className = 'fragment-number-badge';
        badge.setAttribute('aria-hidden', 'true');
        badge.textContent = String(num || '');
        wrap.appendChild(badge);
        if(card.parentNode) card.parentNode.insertBefore(wrap, card);
        wrap.appendChild(card);
      }
      if(extId){ wrap.setAttribute('data-ext-id', extId); }
      if(sourceId){ wrap.setAttribute('data-source-id', sourceId); }
      if(!wrap.getAttribute('data-source-type')){ wrap.setAttribute('data-source-type', card.getAttribute('data-source-type') || 'experience'); }
      if(!card.getAttribute('data-source-type')){ card.setAttribute('data-source-type', wrap.getAttribute('data-source-type') || 'experience'); }
      if(sourceId && !card.getAttribute('data-source-id')){ card.setAttribute('data-source-id', sourceId); }
      wrap.style.left = '';
      wrap.style.top = '';

      try{ card.setAttribute('draggable','false'); }catch(_){ }
      if(!card.__kfragDragStartBound){
        card.__kfragDragStartBound = true;
        card.addEventListener('dragstart', function(ev){
          try{ ev.preventDefault(); }catch(_){ }
        }, false);
      }
      try{ card.style.width = ''; }catch(_){ }

      // details hidden by default
      var d = qs('.card-detail', card);
      if(d){ d.setAttribute('aria-hidden','true'); d.style.display = 'none'; }
      var detailBtn = qs('.detail-button', card);
      if(detailBtn){ detailBtn.textContent = '詳細▼'; }
    });

    bindFragmentReorder(workspace);
    setKfragViewMode(workspace, getKfragViewMode(), false);
    updateFragmentDiscussedIndicators(workspace);

    // click handlers: detail toggle + selection
    if(workspace.__fragmentClickBound) return;
    workspace.__fragmentClickBound = true;
    workspace.addEventListener('click', function(e){
      var btn = e.target && e.target.closest ? e.target.closest('.detail-button') : null;
      if(btn){
        var card = btn.closest('.knowledge_fragment');
        if(!card) return;
        var detail = qs('.card-detail', card);
        if(!detail) return;
        var open = (detail.getAttribute('aria-hidden') !== 'false');
        detail.setAttribute('aria-hidden', open ? 'false' : 'true');
        detail.style.display = open ? 'block' : 'none';
        btn.textContent = open ? '▲閉じる' : '詳細▼';
        return;
      }

      if(workspace.__suppressNextFragmentClick){
        e.preventDefault();
        e.stopPropagation();
        workspace.__suppressNextFragmentClick = false;
        return;
      }

      var frag = e.target && e.target.closest ? e.target.closest('.knowledge_fragment') : null;
      if(frag){
        var selectedSourceType = normalizeSourceType(frag.getAttribute('data-source-type') || 'experience') || 'experience';
        var ext = frag.getAttribute('data-source-id') || frag.getAttribute('data-ext-id');
        if(ext){
          try{
            window.activeKnowledgeLinkedFragmentIds = [];
            window.activeKnowledgeLinkedFragmentRefs = [];
            window.activeKnowledgeLinkedTitle = '';
            clearKnowledgeConnectionOverlay(workspace);
          }catch(_){ }
          // If discussion is underway, keep the selection set fixed.
          // Allow toggling additional only when add-select mode is on.
          if(selectedSourceType === 'experience' && String(window.activeKnowledgeFragmentDiscussed || '').trim() === 'UNDERWAY'){
            if(window.kfrag_add_select_mode){
              toggleAdditionalSelection(workspace, String(ext));
              loadDiscussion();
            } else {
              loadDiscussion();
            }
            return;
          }
          var primary = getActiveFragmentId();
          var primarySourceType = getActiveFragmentSourceType();
          // add-select mode: keep primary, toggle additional
          if(window.kfrag_add_select_mode && primary !== null && String(primary) !== String(ext) && primarySourceType === selectedSourceType){
            // mark wrapper additional selection
            toggleAdditionalSelection(workspace, String(ext));
            loadDiscussion();
            return;
          }

          // normal select: set primary and clear additional
          window.kfrag_add_selected = { ext: [], kfid: [] };
          window.kfrag_display_list = null;
          qsa('.knowledge_fragment.is-selected', workspace).forEach(function(el){ el.classList.remove('is-selected'); });
          frag.classList.add('is-selected');
          var discussed2 = frag.getAttribute('data-discussed') || '';
          setActiveFragment(ext, {
            discussed: discussed2,
            sourceType: selectedSourceType,
            title: (qs('.card-body', frag) ? qs('.card-body', frag).textContent.trim() : null)
          });
          updateAdditionalHighlight(workspace);
          if(selectedSourceType === 'experience' && String(discussed2 || '').trim() === 'UNDERWAY'){
            // Restore latest multi-fragment targets from discussion_history (CSV) so reload still works.
            restoreDiscussionTargetsForPrimary(ext, workspace, discussed2, function(){
              loadDiscussion();
            });
          } else {
            loadDiscussion();
          }
        }
      }
    }, false);

    // remove badge click
    var selList = document.getElementById('fragment-selected-list');
    if(selList && !selList.__bound){
      selList.__bound = true;
      selList.addEventListener('click', function(ev){
        var btn = ev.target && ev.target.closest ? ev.target.closest('.remove-btn') : null;
        if(!btn) return;
        ev.stopPropagation();
        var item = btn.closest('.fragment-selected-item');
        if(!item) return;
        var id = item.getAttribute('data-kfid');
        var sourceType = normalizeSourceType(item.getAttribute('data-source-type') || '') || getActiveFragmentSourceType() || 'experience';
        if(!id) return;
        // do not remove primary
        var refs = getSelectedFragmentRefs();
        var ref = makeKfragRef(sourceType, id);
        if(refs.length && refs[0] === ref) return;
        if(window.kfrag_add_selected && Array.isArray(window.kfrag_add_selected.kfid)){
          window.kfrag_add_selected.kfid = window.kfrag_add_selected.kfid.filter(function(x){ return String(x) !== String(ref); });
        }
        if(sourceType === 'experience' && window.kfrag_add_selected && Array.isArray(window.kfrag_add_selected.ext)){
          window.kfrag_add_selected.ext = window.kfrag_add_selected.ext.filter(function(x){ return String(x) !== String(id); });
        }
        updateFragmentSelectedUI();
        updateAdditionalHighlight(workspace);
        loadDiscussion();
      }, false);
    }

    // Canvas layout support (list layout is reordered by bindFragmentReorder()).
    if(!workspace.__dragBound){
      workspace.__dragBound = true;
      var dragging = null;
      var startX = 0, startY = 0, origL = 0, origT = 0, beforeSnap = null, moved = false;

      function onMove(ev){
        if(!dragging) return;
        var zoom = getKfragCanvasZoomValue(workspace);
        var dx = (ev.clientX - startX) / zoom;
        var dy = (ev.clientY - startY) / zoom;
        if(!moved && Math.sqrt(dx * dx + dy * dy) < 4) return;
        moved = true;
        ev.preventDefault();
        dragging.style.left = (origL + dx) + 'px';
        dragging.style.top = (origT + dy) + 'px';
        dragging.setAttribute('data-canvas-x', String(origL + dx));
        dragging.setAttribute('data-canvas-y', String(origT + dy));
      }
      function onUp(ev){
        if(!dragging) return;
        document.removeEventListener('mousemove', onMove, true);
        document.removeEventListener('mouseup', onUp, true);
        dragging.classList.remove('is-canvas-dragging');
        document.body.classList.remove('is-fragment-canvas-dragging');
        if(moved){
          pushHistory(workspace, beforeSnap);
          saveFragmentCanvasPositions(workspace);
          applyCanvasLayout(workspace, false);
          renderKfragRelations(workspace);
          if(window.activeKnowledgeLinkedFragmentIds && window.activeKnowledgeLinkedFragmentIds.length){
            updateKnowledgeConnectionOverlay(
              workspace,
              window.activeKnowledgeLinkedFragmentIds,
              window.activeKnowledgeLinkedTitle || '',
              window.activeKnowledgeLinkedFragmentRefs || []
            );
          }
          try{ ev.preventDefault(); ev.stopPropagation(); }catch(_){ }
          workspace.__suppressNextFragmentClick = true;
          setTimeout(function(){ workspace.__suppressNextFragmentClick = false; }, 0);
        }
        dragging = null;
        moved = false;
      }

      workspace.addEventListener('mousedown', function(ev){
        if(!workspace.classList.contains('is-canvas-mode')) return;
        if(ev.button !== 0) return;
        var w = ev.target && ev.target.closest ? ev.target.closest('.fragment-node-wrapper') : null;
        if(!w) return;
        // ignore when clicking a button inside card (detail button etc.)
        if(ev.target && ev.target.closest && ev.target.closest('button, input, textarea, select, a')) return;
        var list = qs('.knowledge-fragment-list', workspace);
        if(!list || !list.contains(w)) return;
        try{ ev.preventDefault(); ev.stopPropagation(); }catch(_){ }
        dragging = w;
        dragging.classList.add('is-canvas-dragging');
        document.body.classList.add('is-fragment-canvas-dragging');
        beforeSnap = snapshotPositions(workspace);
        startX = ev.clientX; startY = ev.clientY;
        origL = parseFloat(w.style.left || w.getAttribute('data-canvas-x') || '0') || 0;
        origT = parseFloat(w.style.top || w.getAttribute('data-canvas-y') || '0') || 0;
        moved = false;
        document.addEventListener('mousemove', onMove, true);
        document.addEventListener('mouseup', onUp, true);
      }, false);
    }
  }

  function loadKnowledgeTree(){
    var el = document.getElementById('overlay_knowledge_tree');
    if(!el) return;
    el.textContent = '読み込み中...';

    var gid = getSelectedGroupId();
    var url = 'php/get_knowledge_tree.php';
    if(gid) url += '?group_id=' + encodeURIComponent(gid);

    var xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.onreadystatechange = function(){
      if(xhr.readyState !== 4) return;
      if(xhr.status !== 200){
        el.textContent = '読み込みに失敗しました';
        return;
      }
      var data = null;
      try{ data = JSON.parse(xhr.responseText || '{}'); }catch(e){ data = null; }
      if(!data || data.status !== 'ok' || !Array.isArray(data.nodes)){
        el.textContent = 'データ形式が不正です';
        return;
      }
      try{ syncAreaSelectFromRoots(data.nodes); }catch(_){ }
      renderKnowledgeTree(el, data.nodes);
    };
    xhr.send(null);
  }

  function syncAreaSelectFromRoots(nodes){
    var sel = document.getElementById('kra_area_select');
    if(!sel) return;
    var prev = null;
    try{ prev = sel.value; }catch(_){ prev = null; }
    // Build root list (parent_id == null)
    var roots = (nodes || []).filter(function(n){
      return (n && (n.parent_id === null || typeof n.parent_id === 'undefined'));
    });
    // Sort by sort_order then node_id for stable order
    roots.sort(function(a,b){
      var ao = (a && a.sort_order != null) ? parseInt(a.sort_order,10) : 999999;
      var bo = (b && b.sort_order != null) ? parseInt(b.sort_order,10) : 999999;
      if(ao !== bo) return ao - bo;
      return (a.node_id||0) - (b.node_id||0);
    });

    // Replace options: value=node_id, label=node_title
    sel.innerHTML = '';
    roots.forEach(function(r){
      if(!r) return;
      var opt = document.createElement('option');
      opt.value = String(r.node_id || '');
      opt.textContent = String(r.node_title || '');
      sel.appendChild(opt);
    });
    if(sel.options.length === 0) return;

    // Restore previous selection when possible (supports old value=title as well)
    var restored = false;
    if(prev !== null && typeof prev !== 'undefined'){
      // try match by node_id
      for(var i=0;i<sel.options.length;i++){
        if(String(sel.options[i].value) === String(prev)){ sel.selectedIndex = i; restored = true; break; }
      }
      // try match by title (old behavior)
      if(!restored){
        for(var j=0;j<sel.options.length;j++){
          if(String(sel.options[j].textContent) === String(prev)){ sel.selectedIndex = j; restored = true; break; }
        }
      }
    }
    if(!restored) sel.selectedIndex = 0;
  }

  function renderKnowledgeTree(rootEl, nodes){
    // Render as nested divs to match fukushima-system DOM/CSS expectations:
    // <div class="kt-node" data-node-id="..."><span class="kt-toggle">+</span><span class="kt-node-title">...</span> ... <div class="kt-children">...</div></div>

    function buildKnowledgeStructureText(n){
      var fields = [
        ['When', n ? n.tacto_when : ''],
        ['What', n ? n.tacto_what : ''],
        ['Why', n ? n.tacto_why : ''],
        ['組織知化の根拠', n ? n.organizational_basis : ''],
        ['補足コメント', n ? n.comment : '']
      ];
      var lines = [];
      fields.forEach(function(pair){
        var body = pair[1] != null ? String(pair[1]).trim() : '';
        if(body !== '') lines.push('【' + pair[0] + '】\n' + body);
      });
      return lines.join('\n\n');
    }

    function getKnowledgeStructureFromNodeEl(nodeEl){
      if(!nodeEl) return '';
      return buildKnowledgeStructureText({
        tacto_when: nodeEl.getAttribute('data-tacto-when') || '',
        tacto_what: nodeEl.getAttribute('data-tacto-what') || '',
        tacto_why: nodeEl.getAttribute('data-tacto-why') || '',
        organizational_basis: nodeEl.getAttribute('data-organizational-basis') || '',
        comment: nodeEl.getAttribute('data-comment') || ''
      });
    }

    function removeKnowledgeStructureBlock(nodeEl){
      if(!nodeEl) return;
      var toggleEl = qs(':scope > .kt-comment-toggle', nodeEl);
      var commentEl = qs(':scope > .kt-comment', nodeEl);
      if(toggleEl && toggleEl.parentNode) toggleEl.parentNode.removeChild(toggleEl);
      if(commentEl && commentEl.parentNode) commentEl.parentNode.removeChild(commentEl);
    }

    function appendKnowledgeStructureBlock(nodeEl, structureText){
      if(!nodeEl || !structureText) return;
      var toggleBtn = document.createElement('button');
      toggleBtn.type = 'button';
      toggleBtn.className = 'kt-comment-toggle';
      toggleBtn.setAttribute('aria-expanded', 'false');
      toggleBtn.textContent = '知の内容を表示';

      var c = document.createElement('div');
      c.className = 'kt-comment';
      c.hidden = true;
      c.textContent = structureText;

      var childrenEl = qs(':scope > .kt-children', nodeEl);
      var updatedEl = qs(':scope > .kt-updated', nodeEl);
      var refEl = childrenEl || updatedEl;
      if(refEl){
        nodeEl.insertBefore(toggleBtn, refEl);
        nodeEl.insertBefore(c, refEl);
      } else {
        nodeEl.appendChild(toggleBtn);
        nodeEl.appendChild(c);
      }
    }

    function syncKnowledgeStructureBlock(nodeEl){
      if(!nodeEl) return;
      var structureText = getKnowledgeStructureFromNodeEl(nodeEl);
      removeKnowledgeStructureBlock(nodeEl);
      if(structureText) appendKnowledgeStructureBlock(nodeEl, structureText);
    }

    function pad2(n){ return (n < 10 ? '0' : '') + String(n); }
    function formatTs(ts){
      if(!ts) return '';
      // Accept "YYYY-MM-DD HH:MM:SS" or ISO-ish.
      var s = String(ts).trim();
      var m = s.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
      if(m){
        return m[1] + '年' + m[2] + '月' + m[3] + '日 ' + m[4] + ':' + m[5];
      }
      // Fallback: try Date parse
      var d = new Date(s);
      if(!isNaN(d.getTime())){
        return d.getFullYear() + '年' + pad2(d.getMonth()+1) + '月' + pad2(d.getDate()) + '日 ' + pad2(d.getHours()) + ':' + pad2(d.getMinutes());
      }
      return s;
    }

    // Build parent->children map; keep order stable by node_id asc
    var children = {};
    nodes.forEach(function(n){
      var pid = (n && n.parent_id != null) ? String(n.parent_id) : 'root';
      if(!children[pid]) children[pid] = [];
      children[pid].push(n);
    });
    function sortKey(n){
      var so = (n && n.sort_order != null) ? parseInt(n.sort_order, 10) : 999999;
      if(isNaN(so)) so = 999999;
      return [so, (n && n.node_id) ? (n.node_id||0) : 0];
    }
    Object.keys(children).forEach(function(k){
      children[k].sort(function(a,b){
        var ak = sortKey(a);
        var bk = sortKey(b);
        if(ak[0] !== bk[0]) return ak[0] - bk[0];
        return ak[1] - bk[1];
      });
    });

    function buildNode(n){
      var node = document.createElement('div');
      node.className = 'kt-node';
      node.setAttribute('data-node-id', String(n.node_id));
      try{ node.setAttribute('data-node-title', String(n.node_title != null ? n.node_title : '')); }catch(_){ }
      try{ node.setAttribute('data-comment', String(n.comment != null ? n.comment : '')); }catch(_){ }
      try{ node.setAttribute('data-tacto-when', String(n.tacto_when != null ? n.tacto_when : '')); }catch(_){ }
      try{ node.setAttribute('data-tacto-what', String(n.tacto_what != null ? n.tacto_what : '')); }catch(_){ }
      try{ node.setAttribute('data-tacto-why', String(n.tacto_why != null ? n.tacto_why : '')); }catch(_){ }
      try{ node.setAttribute('data-organizational-basis', String(n.organizational_basis != null ? n.organizational_basis : '')); }catch(_){ }
      if(n && (n.parent_id === null || typeof n.parent_id === 'undefined')){
        node.classList.add('kt-root');
      }

      var kids = children[String(n.node_id)] || [];
      var hasChildren = kids.length > 0;
      var kfragId = null;
      if(typeof n.knowledge_fragment_id !== 'undefined' && n.knowledge_fragment_id !== null){
        kfragId = n.knowledge_fragment_id;
      } else if(typeof n.externalized_contents_id !== 'undefined' && n.externalized_contents_id !== null){
        kfragId = n.externalized_contents_id;
      }
      var sourceTypes = [];
      if(Array.isArray(n.fragment_source_types)){
        sourceTypes = n.fragment_source_types.map(function(type){ return normalizeSourceType(type); }).filter(Boolean);
      }
      if(sourceTypes.length === 0 && kfragId !== null && kfragId !== '' && typeof kfragId !== 'undefined'){
        sourceTypes = ['experience'];
      }
      if(sourceTypes.length){
        try{ node.setAttribute('data-source-types', sourceTypes.filter(function(v, i){ return sourceTypes.indexOf(v) === i; }).join(',')); }catch(_){ }
      }
      if(n && n.fragment_source_ids && typeof n.fragment_source_ids === 'object'){
        var refs = [];
        Object.keys(n.fragment_source_ids).forEach(function(sourceType){
          var normalizedType = normalizeSourceType(sourceType);
          var sourceIds = Array.isArray(n.fragment_source_ids[sourceType]) ? n.fragment_source_ids[sourceType] : [];
          sourceIds.forEach(function(sourceId){
            var ref = makeKfragRef(normalizedType, sourceId);
            if(ref && refs.indexOf(ref) === -1) refs.push(ref);
          });
        });
        if(refs.length){
          try{ node.setAttribute('data-kfrag-refs', refs.join(',')); }catch(_){ }
        }
      }
      if(kfragId !== null && kfragId !== '' && typeof kfragId !== 'undefined'){
        try{ node.setAttribute('data-kfrag-id', String(kfragId)); }catch(_){ }
      }

      var toggle = document.createElement('span');
      toggle.className = 'kt-toggle';
      toggle.textContent = hasChildren ? '-' : '·';
      node.appendChild(toggle);

      // Title / content
      var titleSpan = document.createElement('span');
      if((kfragId !== null && kfragId !== '' || sourceTypes.length > 0) && !hasChildren){
        titleSpan.className = 'kt-content-title';
      } else {
        titleSpan.className = 'kt-node-title';
      }
      titleSpan.textContent = (n.node_title != null ? String(n.node_title) : '(no title)');
      node.appendChild(titleSpan);

      if((kfragId !== null && kfragId !== '' && typeof kfragId !== 'undefined') || sourceTypes.length > 0){
        var detailBtn = document.createElement('button');
        detailBtn.type = 'button';
        detailBtn.className = 'kt-detail-button';
        detailBtn.textContent = '詳細';
        detailBtn.setAttribute('aria-label', '算出した知の詳細を表示');
        node.appendChild(detailBtn);
      }

      // Root nodes can be reordered (up/down)
      if(n && (n.parent_id === null || typeof n.parent_id === 'undefined')){
        var mv = document.createElement('span');
        mv.className = 'kt-root-move';
        var up = document.createElement('button');
        up.type = 'button';
        up.className = 'kt-root-move-btn kt-move-up';
        up.textContent = '▲';
        up.setAttribute('data-dir','up');
        up.setAttribute('aria-label','上へ移動');
        var dn = document.createElement('button');
        dn.type = 'button';
        dn.className = 'kt-root-move-btn kt-move-down';
        dn.textContent = '▼';
        dn.setAttribute('data-dir','down');
        dn.setAttribute('aria-label','下へ移動');
        mv.appendChild(up);
        mv.appendChild(dn);
        node.appendChild(mv);
      }

      // Structured organizational knowledge details.
      var knowledgeStructureText = buildKnowledgeStructureText(n);
      if(knowledgeStructureText !== ''){
        appendKnowledgeStructureBlock(node, knowledgeStructureText);
      }

      // Updated info
      if(n.updated_at != null && String(n.updated_at).trim() !== ''){
        var u = document.createElement('div');
        u.className = 'kt-updated';
        var txt = '更新日時: ' + formatTs(n.updated_at);
        if(n.updated_by_name != null && String(n.updated_by_name).trim() !== ''){
          txt += ' 更新ユーザー: ' + String(n.updated_by_name);
        }
        u.textContent = txt;
        node.appendChild(u);
      }

      if(hasChildren){
        var box = document.createElement('div');
        box.className = 'kt-children';
        box.style.display = 'block';
        kids.forEach(function(ch){
          box.appendChild(buildNode(ch));
        });
        node.appendChild(box);
      }

      return node;
    }

    rootEl.innerHTML = '';
    (children['root'] || []).forEach(function(n){
      rootEl.appendChild(buildNode(n));
    });
    applyKnowledgeTreeSourceFilter();
    updateKnowledgeTreeReverseHighlights(getSelectedFragmentRefs());
    loadKfragRelations(document.getElementById('knowledge_fragments_workspace'));

    function getKnowledgeDetailOverlay(){
      var overlay = document.getElementById('knowledge-detail-overlay-tab');
      if(overlay) return overlay;

      overlay = document.createElement('div');
      overlay.id = 'knowledge-detail-overlay-tab';
      overlay.setAttribute('aria-hidden', 'true');

      var inner = document.createElement('div');
      inner.id = 'knowledge-detail-overlay-inner';
      inner.setAttribute('role', 'dialog');
      inner.setAttribute('aria-modal', 'true');
      inner.setAttribute('aria-labelledby', 'knowledge-detail-overlay-title');

      var header = document.createElement('div');
      header.id = 'knowledge-detail-overlay-header';

      var title = document.createElement('div');
      title.id = 'knowledge-detail-overlay-title';
      title.textContent = '算出した知の詳細';

      var close = document.createElement('button');
      close.type = 'button';
      close.id = 'knowledge-detail-overlay-close';
      close.textContent = '×';
      close.setAttribute('aria-label', '閉じる');
      close.addEventListener('click', function(){
        overlay.style.display = 'none';
        overlay.setAttribute('aria-hidden', 'true');
      }, false);

      var content = document.createElement('div');
      content.id = 'knowledge-detail-overlay-content';

      header.appendChild(title);
      header.appendChild(close);
      inner.appendChild(header);
      inner.appendChild(content);
      overlay.appendChild(inner);
      overlay.addEventListener('click', function(e){
        if(e.target === overlay){
          overlay.style.display = 'none';
          overlay.setAttribute('aria-hidden', 'true');
        }
      }, false);
      document.body.appendChild(overlay);
      return overlay;
    }

    function showKnowledgeNodeDetailOverlay(nodeEl){
      if(!nodeEl) return;
      var overlay = getKnowledgeDetailOverlay();
      var content = document.getElementById('knowledge-detail-overlay-content');
      if(!content) return;

      var nodeId = nodeEl.getAttribute('data-node-id') || '';
      var fragmentIds = nodeEl.getAttribute('data-kfrag-id') || '';
      var titleEl = qs('.kt-node-title, .kt-content-title', nodeEl);
      var title = titleEl ? (titleEl.textContent || '').trim() : '';

      overlay.style.display = 'flex';
      overlay.setAttribute('aria-hidden', 'false');
      content.innerHTML = '<div class="knowledge-detail-loading">読み込み中...</div>';

      $.ajax({
        url: 'php/get_knowledge_fragment_detail.php',
        type: 'GET',
        dataType: 'html',
        data: {
          node_id: nodeId,
          fragment_ids: fragmentIds
        }
      }).done(function(html){
        content.innerHTML = '';
        if(title){
          var heading = document.createElement('div');
          heading.className = 'knowledge-detail-selected-title';
          heading.textContent = title;
          content.appendChild(heading);
        }
        renderKnowledgeEditPanel(content, nodeEl, title, fragmentIds);
        var body = document.createElement('div');
        body.innerHTML = html || '<div class="knowledge-detail-empty">詳細情報がありません。</div>';
        content.appendChild(body);
      }).fail(function(xhr){
        var message = '詳細情報の取得に失敗しました。';
        if(xhr && xhr.responseText){
          try{
            var json = JSON.parse(xhr.responseText);
            if(json && json.message) message = json.message;
          }catch(_){ }
        }
        content.innerHTML = '<div class="knowledge-detail-error"></div>';
        renderKnowledgeEditPanel(content, nodeEl, title, fragmentIds);
        var err = qs('.knowledge-detail-error', content);
        if(err) err.textContent = message;
      });
    }

    function renderKnowledgeEditPanel(content, nodeEl, title, fragmentIds){
      if(!content || !nodeEl) return;
      var nodeId = nodeEl.getAttribute('data-node-id') || '';
      if(!nodeId) return;

      var actionsBar = document.createElement('div');
      actionsBar.className = 'knowledge-detail-actions';
      var toggleBtn = document.createElement('button');
      toggleBtn.type = 'button';
      toggleBtn.className = 'knowledge-detail-edit-toggle';
      toggleBtn.textContent = '編集';
      actionsBar.appendChild(toggleBtn);

      var panel = document.createElement('form');
      panel.className = 'knowledge-edit-panel';
      panel.setAttribute('action', 'javascript:void(0)');
      panel.style.display = 'none';

      var titleLabel = document.createElement('label');
      titleLabel.className = 'knowledge-edit-label';
      titleLabel.textContent = 'タイトル';
      var titleInput = document.createElement('textarea');
      titleInput.className = 'knowledge-edit-control knowledge-edit-title';
      titleInput.rows = 2;
      titleInput.maxLength = 255;
      titleInput.value = nodeEl.getAttribute('data-node-title') || title || '';
      titleLabel.appendChild(titleInput);

      function makeEditTextArea(labelText, value, rows){
        var label = document.createElement('label');
        label.className = 'knowledge-edit-label';
        label.textContent = labelText;
        var input = document.createElement('textarea');
        input.className = 'knowledge-edit-control';
        input.rows = rows || 2;
        input.value = value || '';
        label.appendChild(input);
        return { label: label, input: input };
      }

      var whenField = makeEditTextArea('When（どのような場面で有効か）', nodeEl.getAttribute('data-tacto-when') || '', 2);
      var whatField = makeEditTextArea('What（何を考える／行うとよいか）', nodeEl.getAttribute('data-tacto-what') || '', 2);
      var whyField = makeEditTextArea('Why（なぜそれが有効か）', nodeEl.getAttribute('data-tacto-why') || '', 2);
      var basisField = makeEditTextArea('組織知化の根拠', nodeEl.getAttribute('data-organizational-basis') || '', 3);
      var commentLabel = document.createElement('label');
      commentLabel.className = 'knowledge-edit-label';
      commentLabel.textContent = '補足コメント';
      var commentInput = document.createElement('textarea');
      commentInput.className = 'knowledge-edit-control';
      commentInput.rows = 3;
      commentInput.value = nodeEl.getAttribute('data-comment') || '';
      commentLabel.appendChild(commentInput);

      var linkRow = document.createElement('div');
      linkRow.className = 'knowledge-edit-link-row';
      var linkLabel = document.createElement('label');
      linkLabel.className = 'knowledge-edit-label knowledge-edit-link-label';
      linkLabel.textContent = '接続KF';
      var linkInput = document.createElement('input');
      linkInput.type = 'text';
      linkInput.className = 'knowledge-edit-control knowledge-edit-links';
      linkInput.value = splitKfragIds(fragmentIds).join(',');
      linkLabel.appendChild(linkInput);

      var reflectBtn = document.createElement('button');
      reflectBtn.type = 'button';
      reflectBtn.className = 'knowledge-edit-reflect';
      reflectBtn.textContent = '現在選択中のKFを反映';
      reflectBtn.addEventListener('click', function(){
        linkInput.value = getSelectedFragmentIds().join(',');
      }, false);
      linkRow.appendChild(linkLabel);
      linkRow.appendChild(reflectBtn);

      var actions = document.createElement('div');
      actions.className = 'knowledge-edit-actions';
      var feedback = document.createElement('div');
      feedback.className = 'knowledge-edit-feedback';
      var cancelBtn = document.createElement('button');
      cancelBtn.type = 'button';
      cancelBtn.className = 'knowledge-edit-reflect';
      cancelBtn.textContent = '閉じる';
      var saveBtn = document.createElement('button');
      saveBtn.type = 'submit';
      saveBtn.className = 'knowledge-edit-save';
      saveBtn.textContent = '保存';
      actions.appendChild(feedback);
      actions.appendChild(cancelBtn);
      actions.appendChild(saveBtn);

      panel.appendChild(titleLabel);
      panel.appendChild(whenField.label);
      panel.appendChild(whatField.label);
      panel.appendChild(whyField.label);
      panel.appendChild(basisField.label);
      panel.appendChild(commentLabel);
      panel.appendChild(linkRow);
      panel.appendChild(actions);

      toggleBtn.addEventListener('click', function(){
        panel.style.display = 'block';
        toggleBtn.style.display = 'none';
      }, false);
      cancelBtn.addEventListener('click', function(){
        panel.style.display = 'none';
        toggleBtn.style.display = '';
        feedback.textContent = '';
      }, false);

      panel.addEventListener('submit', function(){
        var newTitle = String(titleInput.value || '').trim();
        var newWhen = String(whenField.input.value || '').trim();
        var newWhat = String(whatField.input.value || '').trim();
        var newWhy = String(whyField.input.value || '').trim();
        var newBasis = String(basisField.input.value || '').trim();
        var newComment = String(commentInput.value || '').trim();
        var ids = splitKfragIds(linkInput.value).join(',');
        if(!newTitle){
          feedback.textContent = 'タイトルを入力してください';
          return;
        }
        feedback.textContent = '保存中...';
        saveBtn.disabled = true;

        var fd = new FormData();
        fd.append('node_id', String(nodeId));
        fd.append('node_title', newTitle);
        fd.append('tacto_when', newWhen);
        fd.append('tacto_what', newWhat);
        fd.append('tacto_why', newWhy);
        fd.append('organizational_basis', newBasis);
        fd.append('comment', newComment);
        fd.append('knowledge_fragment_id', ids);
        fd.append('fragment_source_type', 'experience');
        var gid = getSelectedGroupId();
        if(gid) fd.append('group_id', gid);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'php/update_knowledge_node.php', true);
        xhr.onreadystatechange = function(){
          if(xhr.readyState !== 4) return;
          saveBtn.disabled = false;
          if(xhr.status !== 200){
            feedback.textContent = '保存に失敗しました';
            return;
          }
          var res = null;
          try{ res = JSON.parse(xhr.responseText || '{}'); }catch(_){ res = null; }
          if(!res || res.status !== 'ok'){
            feedback.textContent = (res && res.message) ? res.message : '保存に失敗しました';
            return;
          }
          feedback.textContent = '保存しました';
          try{ nodeEl.setAttribute('data-node-title', newTitle); }catch(_){ }
          try{ nodeEl.setAttribute('data-tacto-when', newWhen); }catch(_){ }
          try{ nodeEl.setAttribute('data-tacto-what', newWhat); }catch(_){ }
          try{ nodeEl.setAttribute('data-tacto-why', newWhy); }catch(_){ }
          try{ nodeEl.setAttribute('data-organizational-basis', newBasis); }catch(_){ }
          try{ nodeEl.setAttribute('data-comment', newComment); }catch(_){ }
          try{ nodeEl.setAttribute('data-kfrag-id', ids); }catch(_){ }
          var titleEl = qs('.kt-node-title, .kt-content-title', nodeEl);
          if(titleEl) titleEl.textContent = newTitle;
          syncKnowledgeStructureBlock(nodeEl);
          var ws = document.getElementById('knowledge_fragments_workspace');
          if(ws) updateKnowledgeConnectionOverlay(ws, splitKfragIds(ids), newTitle);
          panel.style.display = 'none';
          toggleBtn.style.display = '';
          loadKnowledgeTree();
        };
        xhr.send(fd);
      }, false);

      content.appendChild(actionsBar);
      content.appendChild(panel);
    }

    function applySelectionFromKfragId(kid, nodeEl, titleText){
      var refs = getNodeKfragRefs(nodeEl);
      if(!refs.length && kid){
        refs = splitKfragIds(kid).map(function(id){ return makeKfragRef('experience', id); }).filter(Boolean);
      }
      if(!refs.length){
        setActiveFragment(null, {
          discussed: '',
          sourceType: null,
          title: (titleText || '').trim(),
          noLinked: true
        });
        var wsEmpty = document.getElementById('knowledge_fragments_workspace');
        if(wsEmpty){
          try{ qsa('.knowledge_fragment.is-selected', wsEmpty).forEach(function(el){ el.classList.remove('is-selected'); }); }catch(_){ }
          updateAdditionalHighlight(wsEmpty);
          clearKnowledgeConnectionOverlay(wsEmpty);
        }
        loadDiscussion();
        return;
      }
      var parts = refs.map(function(ref){ return String(ref.split(':')[1] || '').trim(); }).filter(Boolean);
      var primaryRef = refs[0];
      var primaryId = parts.length ? parts[0] : null;
      var primaryType = primaryRef ? normalizeSourceType(primaryRef.split(':')[0]) : '';
      if(!primaryId) return;

      window.kfrag_add_selected = window.kfrag_add_selected || { ext: [], kfid: [] };
      window.kfrag_add_selected.ext = primaryType === 'experience' ? parts.slice(1) : [];
      window.kfrag_add_selected.kfid = refs.slice(1);
      // If discussion is underway, keep display list frozen to the current selection set.
      // Otherwise ensure display list is cleared so additional selections are shown.
      if(primaryType === 'experience' && String(window.activeKnowledgeFragmentDiscussed || '').trim() === 'UNDERWAY'){
        window.kfrag_display_list = parts.slice();
      } else {
        window.kfrag_display_list = null;
      }

      // highlight tree
      qsa('#overlay_knowledge_tree .kt-node.is-selected').forEach(function(el){ el.classList.remove('is-selected'); });
      if(nodeEl) nodeEl.classList.add('is-selected');

      // also highlight the fragment card if present and sync discussed status
      var discussed = '';
      var ws = document.getElementById('knowledge_fragments_workspace');
      if(ws){
        qsa('.knowledge_fragment.is-selected', ws).forEach(function(el){ el.classList.remove('is-selected'); });
        var card = (primaryId && primaryType) ? findFragmentCard(ws, primaryType, primaryId) : null;
        if(card){
          card.classList.add('is-selected');
          discussed = card.getAttribute('data-discussed') || '';
        }
      }

      setActiveFragment(primaryId, {
        discussed: primaryType === 'experience' ? discussed : '',
        sourceType: primaryType,
        title: (titleText || '').trim()
      });
      if(ws) updateAdditionalHighlight(ws);
      if(ws) updateKnowledgeConnectionOverlay(ws, parts, titleText || '', refs);
      loadDiscussion();
    }

    // Bind once: toggle open/close and set active fragment id on leaf click.
    if(!rootEl.__ktBound2){
      rootEl.__ktBound2 = true;
      rootEl.addEventListener('click', function(e){
        var t = e.target;
        if(!t) return;
        var commentToggle = t.closest ? t.closest('.kt-comment-toggle') : null;
        if(commentToggle){
          e.preventDefault();
          e.stopPropagation();
          var nodeElC = commentToggle.closest ? commentToggle.closest('.kt-node') : null;
          var commentEl = nodeElC ? qs(':scope > .kt-comment', nodeElC) : null;
          if(!commentEl) return;
          var isOpen = commentToggle.getAttribute('aria-expanded') === 'true';
          commentToggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
          commentToggle.textContent = isOpen ? '知の内容を表示' : '知の内容を閉じる';
          commentEl.hidden = isOpen;
          return;
        }
        // Root reorder buttons
        var mvBtn = t.closest ? t.closest('.kt-root-move-btn') : null;
        if(mvBtn){
          e.preventDefault();
          e.stopPropagation();
          var nodeElM = t.closest ? t.closest('.kt-node') : null;
          if(!nodeElM) return;
          var nodeIdM = nodeElM.getAttribute('data-node-id');
          var dir = mvBtn.getAttribute('data-dir') || '';
          if(!nodeIdM) return;
          var xhrM = new XMLHttpRequest();
          var fdM = new FormData();
          fdM.append('node_id', String(nodeIdM));
          fdM.append('dir', String(dir));
          var gidM = getSelectedGroupId();
          if(gidM) fdM.append('group_id', gidM);
          xhrM.open('POST', 'php/reorder_root_nodes.php', true);
          xhrM.onreadystatechange = function(){
            if(xhrM.readyState !== 4) return;
            if(xhrM.status !== 200){
              try{ if(window.alert) alert('順番の更新に失敗しました'); }catch(_){ }
              return;
            }
            try{
              var r = JSON.parse(xhrM.responseText || '{}');
              if(!r || r.status !== 'ok'){
                try{ if(window.alert) alert((r && r.message) ? r.message : '順番の更新に失敗しました'); }catch(_){ }
                return;
              }
            }catch(_){
              try{ if(window.alert) alert('順番更新の応答処理でエラーが発生しました'); }catch(_){ }
              return;
            }
            loadKnowledgeTree();
          };
          xhrM.send(fdM);
          return;
        }
        var detailBtn = t.closest ? t.closest('.kt-detail-button') : null;
        if(detailBtn){
          e.preventDefault();
          e.stopPropagation();
          var detailNode = detailBtn.closest ? detailBtn.closest('.kt-node') : null;
          showKnowledgeNodeDetailOverlay(detailNode);
          return;
        }
        // While inline editing, ignore clicks in the tree to avoid toggling selection/state.
        try{
          if(t.closest && (t.closest('input.kt-edit') || t.closest('.kt-node-title.editing') || t.closest('.kt-content-title.editing'))){
            return;
          }
        }catch(_){ }

        // Toggle +/- when clicking toggle or title on nodes that have children.
        var toggleEl = t.closest ? t.closest('.kt-toggle') : null;
        var titleEl = t.closest ? t.closest('.kt-node-title') : null;
        if(toggleEl || titleEl){
          var nodeEl = t.closest ? t.closest('.kt-node') : null;
          if(!nodeEl) return;
          // Always highlight the clicked node in the tree (even if it has no kfrag linkage),
          // so the "+" button can use it as an insertion parent.
          if(titleEl){
            try{
              qsa('#overlay_knowledge_tree .kt-node.is-selected').forEach(function(el){ el.classList.remove('is-selected'); });
              nodeEl.classList.add('is-selected');
            }catch(_){ }
          }
          var childrenEl = qs('.kt-children', nodeEl);
          if(childrenEl){
            var open = (childrenEl.style.display !== 'none');
            childrenEl.style.display = open ? 'none' : 'block';
            var tg = qs('.kt-toggle', nodeEl);
            if(tg) tg.textContent = open ? '+' : '-';
          }
          // If this node has kfrag linkage, also update the "currently selected" list on title click.
          // (toggle click only expands/collapses)
          if(titleEl){
            var kid0 = nodeEl.getAttribute('data-kfrag-id');
            if(kid0){
              applySelectionFromKfragId(kid0, nodeEl, titleEl.textContent || '');
            } else {
              var ws0 = document.getElementById('knowledge_fragments_workspace');
              try{
                setActiveFragment(null, {
                  discussed: '',
                  sourceType: null,
                  title: (titleEl.textContent || '').trim(),
                  noLinked: true
                });
                window.activeKnowledgeLinkedFragmentIds = [];
                window.activeKnowledgeLinkedFragmentRefs = [];
                window.activeKnowledgeLinkedTitle = '';
                clearKnowledgeConnectionOverlay(ws0);
              }catch(_){ }
              loadDiscussion();
            }
          }
          return;
        }

        // Leaf selection by clicking content title
        var contentTitle = t.closest ? t.closest('.kt-content-title') : null;
        if(contentTitle){
          var nodeEl2 = t.closest ? t.closest('.kt-node') : null;
          if(!nodeEl2) return;
          var kid = nodeEl2.getAttribute('data-kfrag-id');
          if(kid){
            applySelectionFromKfragId(kid, nodeEl2, contentTitle.textContent || '');
          }
        }
      }, false);

      // Inline rename (double click) for kt-node-title (category / non-leaf titles)
      rootEl.addEventListener('dblclick', function(e){
        var t = e.target;
        if(!t) return;
        var span = t.closest ? t.closest('.kt-node-title') : null;
        if(!span) return;
        // Only allow editing for kt-node-title per request.
        if(span.classList && span.classList.contains('editing')) return;
        e.preventDefault();
        e.stopPropagation();

        var nodeEl = span.closest ? span.closest('.kt-node') : null;
        if(!nodeEl) return;
        var nodeId = nodeEl.getAttribute('data-node-id');
        if(!nodeId) return;

        var oldText = span.textContent || '';
        span.classList.add('editing');
        span.textContent = '';

        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'kt-edit';
        input.value = oldText;
        span.appendChild(input);
        try{ input.focus(); input.select(); }catch(_){ }

        var finished = false;
        function commit(txt){
          finished = true;
          span.classList.remove('editing');
          span.textContent = txt;
        }

        function saveIfChanged(newVal){
          var val = String(newVal || '').trim();
          if(!val || val === oldText){ return; }
          var xhr = new XMLHttpRequest();
          var fd = new FormData();
          fd.append('node_id', String(nodeId));
          fd.append('new_title', val);
          xhr.open('POST', 'php/update_node_title.php', true);
          xhr.onreadystatechange = function(){
            if(xhr.readyState !== 4) return;
            if(xhr.status !== 200){
              try{ if(window.alert) alert('タイトルの保存に失敗しました'); }catch(_){ }
              return;
            }
            try{
              var res = JSON.parse(xhr.responseText || '{}');
              if(!res || res.status !== 'ok'){
                try{ if(window.alert) alert('タイトルの保存に失敗しました'); }catch(_){ }
              }
            }catch(_){
              try{ if(window.alert) alert('タイトル保存の応答処理でエラーが発生しました'); }catch(_){ }
            }
          };
          xhr.send(fd);
        }

        input.addEventListener('keydown', function(ev){
          if(ev.key === 'Enter'){
            var v = (input.value || '').trim();
            saveIfChanged(v);
            commit(v || oldText);
          } else if(ev.key === 'Escape'){
            commit(oldText);
          }
        }, false);
        input.addEventListener('blur', function(){
          if(finished) return;
          commit(oldText);
        }, false);
      }, false);
    }
  }

  // deleteKnowledgeNode: intentionally unused in the current UI (kept in PHP API for future use).

  function discussedLabel(status){
    if(status === 'UNDERWAY') return '議論中';
    if(status === 'DONE') return '議論完了';
    return '議論開始';
  }

  function updateDiscussedButton(){
    var btn = document.getElementById('fragment-discussed-toggle');
    if(!btn) return;
    var s = (typeof window.activeKnowledgeFragmentDiscussed !== 'undefined' && window.activeKnowledgeFragmentDiscussed !== null)
      ? String(window.activeKnowledgeFragmentDiscussed).trim()
      : '';
    btn.textContent = discussedLabel(s);
    btn.disabled = (getActiveFragmentId() === null);
  }

  function bindDiscussedControls(){
    var btn = document.getElementById('fragment-discussed-toggle');
    if(btn){
      btn.addEventListener('click', function(){
        var ids = getSelectedFragmentSourceIds();
        if(ids.length === 0){
          if(window.alert) alert('議論対象とするフラグメントを選択してください');
          return;
        }
        var cur = (typeof window.activeKnowledgeFragmentDiscussed !== 'undefined' && window.activeKnowledgeFragmentDiscussed !== null)
          ? String(window.activeKnowledgeFragmentDiscussed).trim()
          : 'YET';
        var next = (cur === 'YET') ? 'UNDERWAY' : (cur === 'UNDERWAY') ? 'DONE' : 'YET';

        // update all selected fragments
        var pending = ids.length;
        var anyFail = false;
        var activeSourceType = getActiveFragmentSourceType() || 'experience';
        ids.forEach(function(fid){
          var fd = new FormData();
          if(activeSourceType === 'experience') fd.append('experience_knowledge_id', String(fid));
          else if(activeSourceType === 'discussion') fd.append('externalized_contents_id', String(fid));
          else fd.append('srl_id', String(fid));
          fd.append('fragment_source_type', activeSourceType);
          fd.append('status', next);
          var xhr = new XMLHttpRequest();
          xhr.open('POST', 'php/update_discussed_status.php', true);
          xhr.onreadystatechange = function(){
            if(xhr.readyState !== 4) return;
            if(xhr.status !== 200){ anyFail = true; }
            else {
              try{
                var res = JSON.parse(xhr.responseText || '{}');
                if(!res || res.status !== 'ok') anyFail = true;
              }catch(_){ anyFail = true; }
            }
            pending--;
            if(pending === 0){
              if(anyFail){
                if(window.alert) alert('一部のフラグメントの更新に失敗しました');
              }
              window.activeKnowledgeFragmentDiscussed = next;
              // When discussion starts, freeze the current selection set.
              // When it ends (DONE/YET), unlock and allow normal selection again.
              if(next === 'UNDERWAY'){
                window.kfrag_display_list = ids.slice();
              } else {
                window.kfrag_display_list = null;
              }
              var ws = document.getElementById('knowledge_fragments_workspace');
              if(ws){
                ids.forEach(function(id2){
                  var card2 = findFragmentCard(ws, activeSourceType, id2);
                  if(card2){ try{ card2.setAttribute('data-discussed', next); }catch(_){ } }
                });
                updateFragmentDiscussedIndicators(ws);
                updateAdditionalHighlight(ws);
                updateFragmentSelectedUI();
              }
              updateDiscussedButton();
            }
          };
          xhr.send(fd);
        });
      }, false);
    }

    var addBtn = document.getElementById('fragment-add-select-toggle');
    if(addBtn){
      addBtn.addEventListener('click', function(){
        window.kfrag_add_select_mode = !window.kfrag_add_select_mode;
        addBtn.classList.toggle('is-adding', window.kfrag_add_select_mode);
        // When turning off, keep additional selections but allow user to continue; mirror fukushima behavior.
        var ws = document.getElementById('knowledge_fragments_workspace');
        if(ws) updateAdditionalHighlight(ws);
        updateFragmentSelectedUI();
      }, false);
    }

    updateDiscussedButton();
  }

  function bindKnowledgeRegister(){
    var form = document.getElementById('knowledge_register_form');
    if(!form) return;
    form.addEventListener('submit', function(){
      var area = qs('#kra_area_select', form);
      var content = qs('#kra_knowledge_content', form);
      var tactoWhen = qs('#kra_tacto_when', form);
      var tactoWhat = qs('#kra_tacto_what', form);
      var tactoWhy = qs('#kra_tacto_why', form);
      var organizationalBasis = qs('#kra_organizational_basis', form);
      var comment = qs('#kra_comment_input', form);
      var feedback = document.getElementById('knowledge_register_feedback');
      var parent_id = area ? String(area.value || '') : '';
      var parent_label = '';
      if(area && area.options && area.selectedIndex >= 0){
        parent_label = String(area.options[area.selectedIndex].textContent || '').trim();
      }
      var node_title = content ? content.value.trim() : '';
      var tacto_when = tactoWhen ? tactoWhen.value.trim() : '';
      var tacto_what = tactoWhat ? tactoWhat.value.trim() : '';
      var tacto_why = tactoWhy ? tactoWhy.value.trim() : '';
      var organizational_basis = organizationalBasis ? organizationalBasis.value.trim() : '';
      var node_comment = comment ? comment.value.trim() : '';
      if(!node_title){
        if(feedback) feedback.textContent = '組織知の要約を入力してください。';
        return;
      }
      if(feedback) feedback.textContent = '登録中...';

      var fd = new FormData();
      if(parent_id) fd.append('parent_id', parent_id);
      if(parent_label) fd.append('parent_label', parent_label);
      fd.append('node_title', node_title);
      fd.append('tacto_when', tacto_when);
      fd.append('tacto_what', tacto_what);
      fd.append('tacto_why', tacto_why);
      fd.append('organizational_basis', organizational_basis);
      if(node_comment){ fd.append('comment', node_comment); }
      var gid = getSelectedGroupId();
      if(gid) fd.append('group_id', gid);
      // Link to selected fragments (primary + additional) as CSV (fukushima-system behavior)
      var ids = getSelectedFragmentSourceIds();
      var sourceType = getActiveFragmentSourceType() || 'experience';
      if(ids.length){
        fd.append('knowledge_fragment_id', ids.join(','));
        fd.append('fragment_source_type', sourceType);
      }

      var xhr = new XMLHttpRequest();
      xhr.open('POST', 'php/insert_knowledge_node.php', true);
      xhr.onreadystatechange = function(){
        if(xhr.readyState !== 4) return;
        if(xhr.status !== 200){
          if(feedback) feedback.textContent = '登録に失敗しました。';
          return;
        }
        var data = null;
        try{ data = JSON.parse(xhr.responseText || '{}'); }catch(e){ data = null; }
        if(!data || data.status !== 'ok'){
          if(feedback) feedback.textContent = (data && data.message) ? data.message : '登録に失敗しました。';
          return;
        }
        if(content) content.value = '';
        if(tactoWhen) tactoWhen.value = '';
        if(tactoWhat) tactoWhat.value = '';
        if(tactoWhy) tactoWhy.value = '';
        if(organizationalBasis) organizationalBasis.value = '';
        if(comment) comment.value = '';
        if(feedback) feedback.textContent = '登録しました。';
        loadKnowledgeTree();
      };
      xhr.send(fd);
    }, false);
  }

  function bindDiscussion(){
    var form = document.getElementById('discussion_post_form');
    var input = document.getElementById('discussion_input');
    var list = document.getElementById('discussion_message_list');
    if(!form || !input || !list) return;

    form.addEventListener('submit', function(){
      var text = input.value.trim();
      if(!text) return;
      var ids = getSelectedFragmentSourceIds();
      var sourceType = getActiveFragmentSourceType() || 'experience';
      if(ids.length === 0){
        if(window.alert) alert('議論対象とするフラグメントを選択してください');
        return;
      }
      var fd = new FormData();
      fd.append('content', text);
      fd.append('knowledge_fragment_id', ids.join(','));
      fd.append('fragment_source_type', sourceType);
      var xhr = new XMLHttpRequest();
      xhr.open('POST', 'php/save_discussion_history.php', true);
      xhr.onreadystatechange = function(){
        if(xhr.readyState !== 4) return;
        if(xhr.status !== 200){
          if(window.alert) alert('通信エラーにより投稿できませんでした。');
          return;
        }
        var data = null;
        try{ data = JSON.parse(xhr.responseText || '{}'); }catch(e){ data = null; }
        if(!data || data.status !== 'ok'){
          if(window.alert) alert('投稿の保存に失敗しました。');
          return;
        }
        input.value = '';
        loadDiscussion();
      };
      xhr.send(fd);
    }, false);
  }

  function loadDiscussion(){
    var list = document.getElementById('discussion_message_list');
    var form = document.getElementById('discussion_post_form');
    if(!list) return;
    var sourceType = getActiveFragmentSourceType() || 'experience';
    var ids = getSelectedFragmentSourceIds();
    if(ids.length === 0){
      // No selection -> placeholder + hide form
      list.innerHTML = '';
      var ph = document.createElement('div');
      ph.className = 'discussion-placeholder';
      ph.textContent = window.activeKnowledgeNoLinked ? 'この組織知に紐づく KF はありません。' : '議論対象とするフラグメントを選択してください';
      list.appendChild(ph);
      if(form) form.style.display = 'none';
      updateDiscussedButton();
      return;
    }
    if(form) form.style.display = 'block';
    list.textContent = '読み込み中...';

    var url = 'php/get_discussion_history.php?limit=200';
    url += '&fragment_id=' + encodeURIComponent(ids.join(','));
    url += '&fragment_source_type=' + encodeURIComponent(sourceType);
    var xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.onreadystatechange = function(){
      if(xhr.readyState !== 4) return;
      if(xhr.status !== 200){
        list.textContent = '読み込みに失敗しました';
        return;
      }
      var data = null;
      try{ data = JSON.parse(xhr.responseText || '{}'); }catch(e){ data = null; }
      if(!data || data.status !== 'ok' || !Array.isArray(data.items)){
        list.textContent = 'データ形式が不正です';
        return;
      }
      list.innerHTML = '';
      if(data.items.length === 0){
        var empty2 = document.createElement('div');
        empty2.className = 'discussion-placeholder';
        empty2.textContent = '投稿はまだありません。';
        list.appendChild(empty2);
        return;
      }
      data.items.forEach(function(it){
        var card = document.createElement('div');
        card.className = 'message-card';
        try{ card.setAttribute('data-discussion-id', it.discussion_history_id || ''); }catch(_){ }
        var a = document.createElement('div');
        a.className = 'message-author';
        a.textContent = (it.user_name ? it.user_name : ('user ' + it.user_id)) + ' さん';
        var b = document.createElement('div');
        b.className = 'message-body';
        b.textContent = it.content || '';
        card.appendChild(a);
        card.appendChild(b);
        if(it.posted_time){
          var t = document.createElement('div');
          t.className = 'message-time';
          t.textContent = it.posted_time;
          card.appendChild(t);
        }
        list.appendChild(card);
      });
      try{ list.scrollTop = list.scrollHeight; }catch(e){}
    };
    xhr.send(null);
  }

  function onReady(fn){
    if(document.readyState === 'complete' || document.readyState === 'interactive') fn();
    else document.addEventListener('DOMContentLoaded', fn, false);
  }

  onReady(function(){
    var coop = document.getElementById('org-tab-cooperation');
    var comb = document.getElementById('org-tab-combination');
    if(coop) coop.addEventListener('click', function(){ setTab('org-tab-cooperation'); }, false);
    if(comb) comb.addEventListener('click', function(){ setTab('org-tab-combination'); }, false);
    bindSourceFilters();
    applySourceFilter('cooperation');

    // Default: cooperation (existing behavior); if URL has ?orgtab=combination, open it.
    try{
      var m = location && location.search ? location.search.match(/[?&]orgtab=([^&]+)/) : null;
      if(m && m[1] === 'combination'){ setTab('org-tab-combination'); }
    }catch(e){ /* no-op */ }
  });
})();
