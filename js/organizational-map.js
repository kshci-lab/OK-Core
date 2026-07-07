// 議論内省マップに関する処理プログラム
let defaultOrganizational;
let defaultRecordOrganizational;
let defaultShowOrganizational;

class Organizational { // forestMRN: forest Meeting Reflection Network
    constructor(container, load) {
        // this.ownNetwork = this.generateOrganizationalNetworkCanvas(container, {}, {}); // デフォルトのマップを表示

        defaultRecordOrganizational = new RecordOrganizational();
        this.nodes = new vis.DataSet();
        this.edges = new vis.DataSet();
        this.options = {
	        physics: {
                enabled: true,
                solver: 'barnesHut',
                barnesHut: {
                    gravitationalConstant: -500,
                    centralGravity: 0.1,
                    springLength: 170,
                    springConstant: 0.03,
                    damping: 0.12,
                    avoidOverlap: 0.4
                },
                stabilization: {
                    iterations: 250
                }
            },
            nodes: {
                margin: 10,
                widthConstraint: {
                    maximum: 150
                },
            },
	        edges: {
		        arrows: 'to', // エッジに矢印を付けて有向グラフにする
		        smooth: false // falseにするとエッジが直線になる
            },
            interaction: {
                multiselect: false,
                zoomView: false, // グラフの拡大縮小を無効にする
                hover: true
            },
        };
        this.nodeConnectEnabled = false; // マインドマップとの対応づけを可能にする（マインドマップのノードクリックが，議論内省マップノードとの対応を付与するのかそうでないのかを判定するよう）
        this.latest_selected_node_info = {
            x: 0,
            y: 35
        }; // 最後にクリックされたノードの情報
        this.output_input = {}; //オントロジーの入力と出力の対応付け
        this.output_list = [];  //概念として出すもの
        this.dragStartNodeId = null;  //ドラッグスタートしたノードのID
        this.dragEndNodeId = null; //ドラッグエンドしたノードID
        this.edgeEditMode = false; // リンクを編集できるかどうかのモード（Falseは編集不可）
        this.EdgeStartId = []; //エッジの開始ID
        this.EdgeEndId = []; //エッジの終了ID
        // this.OntologyNodeId = []; //オントロジーノードのノードID
        // this.OntologyConnectNodeId = []; //オントロジーノードと対応づいているノードID
        // this.ConnectNetworkNodeId = [];
        // this.ConnectMindMapNodeId = [];
        // this.RecruitNodeId = [];//採用or棄却されたノードID
        // this.Recruit = [];//採用or棄却
        // this.Feedback = [];//フィードバック書いたかどうか
        // this.FeedbackNodeId = null; //フィードバック書かれるノードID
        this.selectId = null;//選択されたノードID
        this.selectedOrganizationalNode = null;
        // this.interval = null; //インターバル抜けるための変数
        this.material_id = null;
        this.concept_id = null;
        this.scale = 1;
        this.activeSourceTypes = ['experience', 'discussion', 'SRL'];
        this.BoxDisplay = {
            x: 0,
            y: 0
        }//右クリックされやメニューの表示場所
        this.tooltipEl = null;
        this.ownNetwork = this.generateOrganizationalNetworkCanvas(container, this.nodes, this.edges); // デフォルトのマップを表示
        this.ownNetwork.on('stabilizationIterationsDone', this.applyNodeDisplayStyles.bind(this));
        this.ensureTooltipElement();
        this.choose_input_xmlLoad();
        if(load == "load"){
            this.jmindex = [];
            this.addEventLister();
            // $(`#jsmind_container`).on('click',this.connect_mindmap.bind(this));
            // $(`#organizational_conmenu1`).on('click',this.view_otherprocessmap.bind(this));
            // $(`#organizational_conmenu2`).on('click',this.connect_network.bind(this));
            // $(`#organizational_conmenu3`).on('click',this.Recruit_Idea.bind(this));
            $(`#organizational_conmenu4`).on('click',this.ContentmenuCancel.bind(this));
            // $(`#p_ontology_select`).on('click',this.addontology.bind(this));
            // $(`#p_recruit_select`).on('click',this.Selected_Recruit_Idea.bind(this));
            this.ownNetwork.on('click', this.networkClick.bind(this));
            this.ownNetwork.on('dragStart', this.dragstart.bind(this));
            this.ownNetwork.on('dragEnd', this.dragend.bind(this));
            this.ownNetwork.on('doubleClick', this.doubleclick.bind(this));
            this.ownNetwork.on("oncontext", this.onContext.bind(this));
            this.ownNetwork.on('select', this.selectdelete.bind(this));
            this.ownNetwork.on('hoverNode', this.onHoverNode.bind(this));
            this.ownNetwork.on('blurNode', this.onBlurNode.bind(this));
        }
        this.choose_input_xmlLoad();
    }


    choose_input_xmlLoad(){
        $.ajax({
            url:'js/hozo.xml',
            type:'get',
            dataType:'xml',
            timeout:1000,
            success:this.choose_input_parse_xml.bind(this)
        });
    }

    choose_input_parse_xml(xml,status){
        if(status!='success')return;
        const XML = $(xml).find('W_CONCEPTS');
        const label = Array.from(XML[0].getElementsByTagName('LABEL'));
        const isa = Array.from(XML[0].getElementsByTagName('ISA'));
        const rationaly_record = (return_slot) => {
            let rationaly_label;
            let input_count = 0;
            for(var i=0; i<return_slot.length; i++){
                if(return_slot[i].getAttribute("role") === "入力"){
                    label.map((content_label2) => {
                        if(return_slot[i].getAttribute('class_constraint') === content_label2.childNodes[0].nodeValue){
                            const slot_content = Array.from(content_label2.parentNode.getElementsByTagName("SLOT"));
                            slot_content.map((content_slot2) => {
                                if(content_slot2.getAttribute("role") === "出力"){
                                    if(!this.output_list.includes(content_slot2.getAttribute("class_constraint"))) {
                                        this.output_list.push(content_slot2.getAttribute("class_constraint"));
                                    }
                                    if(input_count == 0){
                                        rationaly_label = content_slot2.getAttribute("class_constraint");
                                    }else{
                                        this.output_input[rationaly_label] = content_slot2.getAttribute("class_constraint");
                                        this.output_input[content_slot2.getAttribute("class_constraint")] = rationaly_label;
                                    }
                                }
                            });
                        return;
                        }
                    });
                    input_count += 1;
                }
            }
        }
        //hozo.xmlファイルのタグを検索して変数に格納（たぶん，全てのタグが配列で格納されている），thisはhozo.xmlのことかな
        isa.map((content)=>{
            if(content.getAttribute('parent') === "合理性を考える"){
                label.map((content_label) => {
                    if(content.getAttribute('child') === content_label.childNodes[0].nodeValue){
                        rationaly_record(Array.from(content_label.parentNode.getElementsByTagName("SLOT")))
                        return;
                    }
                })
            }
        });
        // const selectElement = document.getElementById("selectionlist");
        // while (selectElement.options.length > 0) {
        //     selectElement.remove(0);
        // }
        // this.output_list.map((n) => {
        //     const optionElement = document.createElement('option');
        //     optionElement.value = n;
        //     optionElement.text = n;
        //     selectElement.appendChild(optionElement);
        // })
    }

    // //オントロジーノードを選択不可に
    selectdelete(params) {
        if (this.nodes.get(params.nodes[0]).shape == "ellipse") {
            // 選択を解除
            this.ownNetwork.setSelection({ nodes: [] });
        }
    }

    addEventLister(){
        // this.bindconnect_mindmap = this.connect_mindmap.bind(this);
        this.bindview_otherprocessmap = this.view_otherprocessmap.bind(this);
        // this.bindconnect_network = this.connect_network.bind(this);
        // this.bindRecruit_Idea = this.Recruit_Idea.bind(this);
        this.bindContentmenuCancel = this.ContentmenuCancel.bind(this);
        this.bindDeleteExperienceKnowledge = this.deleteExperienceKnowledge.bind(this);
        this.bindOpenExperienceKnowledgeEdit = this.openExperienceKnowledgeEdit.bind(this);
        // this.bindaddontology = this.addontology.bind(this);
        // this.bindSelected_Recruit_Idea = this.Selected_Recruit_Idea.bind(this);
        // this.bindfeedback = this.feedback.bind(this);
        // this.bindNodeblinking = this.Nodeblinking.bind(this);
        // $(`#jsmind_container`).on('click',this.bindconnect_mindmap);
        $(`#organizational_conmenu1`).on('click',this.bindview_otherprocessmap);
        // $(`#organizational_conmenu2`).on('click',this.bindconnect_network);
        // $(`#organizational_conmenu3`).on('click',this.bindRecruit_Idea);
        $(`#organizational_conmenu4`).on('click',this.bindContentmenuCancel);
        $(`#organizational_conmenu5`).off('click.organizationalDelete').on('click.organizationalDelete', this.bindDeleteExperienceKnowledge);
        $(`#organizational_conmenu6`).off('click.organizationalEdit').on('click.organizationalEdit', this.bindOpenExperienceKnowledgeEdit);
        // $(`#p_ontology_select`).on('click',this.bindaddontology);
        // $(`#p_recruit_select`).on('click',this.bindSelected_Recruit_Idea);
        // $(`#feedbackrecord`).on('click',this.bindfeedback);
        // this.interval = setInterval(this.bindNodeblinking, 1000);
    }

    ensureTooltipElement(){
        if (this.tooltipEl) return;
        const containerEl = document.getElementById('myOrganizationalnetwork');
        if (!containerEl) return;
        let tooltipEl = document.getElementById('organizational_tooltip');
        if (!tooltipEl) {
            tooltipEl = document.createElement('div');
            tooltipEl.id = 'organizational_tooltip';
            tooltipEl.className = 'organizational-tooltip';
            tooltipEl.style.display = 'none';
            containerEl.appendChild(tooltipEl);
        }
        this.tooltipEl = tooltipEl;
    }

    showTooltipForNode(nodeId){
        this.ensureTooltipElement();
        if (!this.tooltipEl) return;
        const node = this.nodes.get(nodeId);
        if (!node || !node.tooltip_data) {
            this.hideTooltip();
            return;
        }
        const tooltipData = node.tooltip_data;
        this.tooltipEl.textContent = '';
        const sections = Array.isArray(tooltipData.sections) ? tooltipData.sections : [
            { heading: '経験', body: tooltipData.selected_contents || '' },
            { heading: '経験の振り返り', body: tooltipData.stage1 || '' },
            { heading: '活動文脈固有の振り返り', body: tooltipData.stage2 || '' },
            { heading: '研究固有の振り返り', body: tooltipData.stage3 || '' }
        ];
        sections.forEach((section) => {
            const heading = document.createElement('div');
            heading.className = 'organizational-tooltip-heading';
            heading.textContent = section.heading;
            const body = document.createElement('div');
            body.textContent = section.body;
            this.tooltipEl.appendChild(heading);
            this.tooltipEl.appendChild(body);
        });
        const box = this.ownNetwork.getBoundingBox(nodeId);
        const domPoint = this.ownNetwork.canvasToDOM({ x: box.right, y: box.top });
        const offset = 12;
        this.tooltipEl.style.left = (domPoint.x + offset) + 'px';
        this.tooltipEl.style.top = (domPoint.y + offset) + 'px';
        this.tooltipEl.style.display = 'flex';
    }

    hideTooltip(){
        if (this.tooltipEl) {
            this.tooltipEl.style.display = 'none';
        }
    }

    onHoverNode(params){
        this.showTooltipForNode(params.node);
    }

    onBlurNode(){
        this.hideTooltip();
    }

    removeEventLister(){
        // $(`#jsmind_container`).off('click',this.bindconnect_mindmap);
        $(`#organizational_conmenu1`).off('click',this.bindview_otherprocessmap);
        // $(`#organizational_conmenu2`).off('click',this.bindconnect_network);
        // $(`#organizational_conmenu3`).off('click',this.bindRecruit_Idea);
        $(`#organizational_conmenu4`).off('click',this.bindContentmenuCancel);
        $(`#organizational_conmenu5`).off('click.organizationalDelete');
        $(`#organizational_conmenu6`).off('click.organizationalEdit');
        // $(`#p_ontology_select`).off('click',this.bindaddontology);
        // $(`#p_recruit_select`).off('click',this.bindSelected_Recruit_Idea);
        // $(`#feedbackrecord`).off('click',this.bindfeedback);
        // clearInterval(this.interval);
    }
    /*
     * マップ編集ユーティリティ
     */
    generateUniqueNumberText() {
        return `${new Date().getTime()}${Math.floor(1000000 * Math.random())}`;
    }

    setNodes(newNodes) {
        this.nodes = newNodes;
    }
    setEdges(newEdges) {
        this.edges = newEdges;
    }
    setOptions(options) {
        this.options = options;
    }

    //エッジ編集できるか切り替え
    SelectEditEdge(){
        this.edgeEditMode = !this.edgeEditMode;
        if(this.edgeEditMode){
            this.enableEditEdge();
        }else{
            this.disableEditEdge();
        }
    }

    //エッジ編集できる場合の処理
    enableEditEdge() {
        // エッジを編集するときは，ノードの動きを止める
        document.getElementById("organizational_startEditEdge").value="エッジ追加終了";
        this.nodes.update(this.nodes.map(n => {
            return { ...n, fixed: true };
        }));     
    }

    //エッジ編集できない場合の処理
    disableEditEdge() {
        document.getElementById("organizational_startEditEdge").value="エッジ追加";
        // エッジの編集モードを抜けたときは，ノードの動きを再度始める（ただし，タグノードはFixedにしておく）
        this.edgeEditMode = false;
        this.nodes.update(this.nodes.map(n => {
            return n.type !== "topic-tag" ? { ...n, fixed: false } : { ...n, fixed: true };
        }))
    }

    /*
     * 議論内省マップの表示・操作部分（Extend vis.js）
     */
    generateOrganizationalNetworkCanvas (canvas_dom_id, nodes, edges) {
        // マップを表示

        this.setNodes(nodes);
        this.setEdges(edges);

        return new vis.Network(
            document.getElementById(canvas_dom_id),
            {
                nodes: nodes,
                edges: edges,
            },
            this.options
        );

        // this.setCanvasOptions(load);
        // return network;
    }

    /*
     * ノードの操作
     */
    //ノード追加(完了)
    addNode(node_id, node_label, node_type) {
        let node_color = '#f8e58c'; // ノードの背景色
        let node_shape = 'box';     // ノードの形状
        let text_color = 'black';   // ノード内文字列の色
        let position_fixed = false;   // ノードを動かせるかどうか（Falseなら動かせる）

        let result_label = '';
        for (let i = 0; i < node_label.length; i += 10) {
            result_label += node_label.substr(i, 10) + '\n';
        }
        result_label = result_label.trim(); // 末尾の不要な改行を除去
        const newNode = {
            id: node_id,
            label: result_label,
            group: node_type,
            color: node_color, shape: node_shape,
            font: { color: text_color },
            fixed: position_fixed,
        };
        this.nodes.add(newNode);
        const boundingBox = this.ownNetwork.getBoundingBox(node_id);
        node_y += Math.floor(((boundingBox.bottom)-(boundingBox.top))/2);
        this.nodes.update({
            id : node_id,
            color: node_color,
            shape: node_shape,
            font: { color: text_color },
        });
        const boundingBoxupdate = this.ownNetwork.getBoundingBox(node_id);
        this.latest_selected_node_info.x = node_x;
        this.latest_selected_node_info.y = boundingBoxupdate.bottom+10;
        defaultRecordOrganizational.record_Node(node_id, node_label, node_type, node_x, node_y);
        return this.nodes;
    }

    addReloadProcessNode(user_id, node_id, node_label, node_type, concept_id, thought_experience_node_id, selected_contents, stage1, stage2, stage3, source_type, source_id) {
        const existingNode = this.nodes.get(node_id);
        if (existingNode) {
            console.log(`Node with ID ${node_id} already exists. Skipping addition.`);
            return; // 重複がある場合は追加せずにリターン
        }
        let node_shape = 'box';     // ノードの形状
        let text_color = 'black';   // ノード内文字列の色
        const normalizedSourceType = this.normalizeSourceType(source_type) || 'experience';
        const palette = this.getSourcePalette(normalizedSourceType);
        
        const contentLabel = node_label || '';
        const newNode = {
            id: `${node_id}`, label: contentLabel,
            node_type: node_type,
            experience_knowledge_id: normalizedSourceType === 'experience' ? String(source_id || '').trim() : '',
            source_record_id: String(source_id || '').trim(),
            concept_id: concept_id,
            thought_experience_node_id: thought_experience_node_id,
            user_id: user_id,
            source_type: normalizedSourceType,
            source_types: [normalizedSourceType],
            color: {
                background: palette.background,
                border: palette.border,
                highlight: { background: palette.highlightBackground, border: palette.border },
                hover: { background: palette.hoverBackground, border: palette.border }
            },
            shape: node_shape,
            font: { color: text_color },
            tooltip_data: {
                selected_contents: selected_contents,
                stage1: stage1,
                stage2: stage2,
                stage3: stage3
            },
            fixed: false,
        };
        defaultOrganizational.nodes.add(newNode);

        //　trigger_toの設定
        const newEdge = {
            from: user_id,
            to: node_id,
            arrows: 'dynamic',
            group: "shared_process",
            smooth: true,
        };
        defaultOrganizational.edges.add(newEdge);
        this.applyNodeDisplayStyles();

        return defaultOrganizational.nodes, defaultOrganizational.edges;
    }

    normalizeSourceType(type){
        const raw = String(type || '').trim();
        const lower = raw.toLowerCase();
        if (lower === 'discussion' || lower === 'externalized') return 'discussion';
        if (lower === 'srl') return 'SRL';
        if (lower === 'experience') return 'experience';
        if (lower === 'discussion') return 'discussion';
        return '';
    }

    parseSourceTypes(value, fallback){
        let sourceTypes = [];
        if (Array.isArray(value)) {
            sourceTypes = value;
        } else if (value !== null && typeof value !== 'undefined') {
            sourceTypes = String(value).split(',');
        }
        sourceTypes = sourceTypes.map((type) => this.normalizeSourceType(type)).filter((type) => type !== '');
        sourceTypes = sourceTypes.filter((type, index) => sourceTypes.indexOf(type) === index);
        if (sourceTypes.length === 0 && fallback) sourceTypes = [fallback];
        return sourceTypes;
    }

    getSourcePalette(sourceType){
        const normalized = this.normalizeSourceType(sourceType) || 'experience';
        if (normalized === 'discussion') {
            return {
                background: '#fde3ea',
                border: '#e4a8b8',
                highlightBackground: '#fbd6e1',
                hoverBackground: '#feeaf0'
            };
        }
        if (normalized === 'SRL') {
            return {
                background: '#dceeff',
                border: '#9fc4ea',
                highlightBackground: '#d0e8ff',
                hoverBackground: '#ebf5ff'
            };
        }
        return {
            background: '#fff7cf',
            border: '#e2c968',
            highlightBackground: '#fff1aa',
            hoverBackground: '#fff9de'
        };
    }

    applyNodeDisplayStyles(){
        const updates = [];
        this.nodes.forEach((node) => {
            if (node && node.group === 'produced_knowledge') {
                updates.push({
                    id: node.id,
                    color: {
                        background: '#dff2e6',
                        border: '#90B1AB',
                        highlight: { background: '#d2ecd9', border: '#90B1AB' },
                        hover: { background: '#eaf7ee', border: '#90B1AB' }
                    },
                    font: Object.assign({}, node.font || {}, { color: 'black' })
                });
                return;
            }
            const sourceTypes = this.parseSourceTypes(node && (node.source_types || node.source_type), '');
            if (!sourceTypes.length) return;
            const palette = this.getSourcePalette(sourceTypes[0]);
            updates.push({
                id: node.id,
                color: {
                    background: palette.background,
                    border: palette.border,
                    highlight: { background: palette.highlightBackground, border: palette.border },
                    hover: { background: palette.hoverBackground, border: palette.border }
                },
                font: Object.assign({}, node.font || {}, { color: 'black' })
            });
        });
        if (updates.length) {
            this.nodes.update(updates);
        }
    }

    nodeMatchesSourceFilter(node){
        const sourceTypes = this.parseSourceTypes(node && (node.source_types || node.source_type), '');
        if (sourceTypes.length === 0) return true;
        return sourceTypes.some((type) => this.activeSourceTypes.indexOf(type) !== -1);
    }

    applySourceFilter(sourceTypes, options){
        this.applyNodeDisplayStyles();
        this.activeSourceTypes = this.parseSourceTypes(sourceTypes, '').length ? this.parseSourceTypes(sourceTypes, '') : ['experience', 'discussion', 'SRL'];
        this.nodes.forEach((node) => {
            if (node && node.group === 'produced_knowledge') {
                this.ensureProducedKnowledgeEdges(node.id);
            }
        });
        const hideOrganizationalKnowledge = !!(options && options.hideOrganizationalKnowledge);
        const hiddenByNode = {};
        this.nodes.forEach((node) => {
            let hidden = !this.nodeMatchesSourceFilter(node);
            if (!hidden && hideOrganizationalKnowledge && node && node.group === 'produced_knowledge') {
                hidden = true;
            }
            hiddenByNode[String(node.id)] = hidden;
            if (node.hidden !== hidden) {
                this.nodes.update({ id: node.id, hidden: hidden });
            }
        });
        this.edges.forEach((edge) => {
            const edgeHidden = !!hiddenByNode[String(edge.from)] || !!hiddenByNode[String(edge.to)];
            if (edge.hidden !== edgeHidden) {
                this.edges.update({ id: edge.id, hidden: edgeHidden });
            }
        });
        this.applyNodeDisplayStyles();
        try{ this.ownNetwork.redraw(); }catch(_){}
        try{
            const self = this;
            setTimeout(function(){
                try{ self.applyNodeDisplayStyles(); }catch(_){}
                try{ self.ownNetwork.redraw(); }catch(_){}
            }, 0);
        }catch(_){}
    }

    addUserNode(user_id, user_name, node_type){
        const existingNode = defaultOrganizational.nodes.get(user_id);
        if (existingNode) {
            console.log(`Node with ID ${user_id} already exists. Skipping addition.`);
            return; // 重複がある場合は追加せずにリターン
        }
        let node_shape = 'image';     // ノードの形状
        var DIR_img = "../image/organizational/"; //ノードのアイコンとなる画像のパス
        let image = "user-solid-full.svg"; 

        const newNode = {
            id: user_id,
            label: user_name,
            group: node_type,
            shape: node_shape,
            image: DIR_img + image,
            imagePadding: 7,
            fixed: false,
        };
        
        defaultOrganizational.nodes.add(newNode);

        return defaultOrganizational.nodes;

    }

    makeFragmentNodeId(sourceType, sourceId){
        const normalized = this.normalizeSourceType(sourceType) || 'experience';
        const rawId = String(sourceId || '').trim();
        if (!rawId) return '';
        return normalized + ':' + rawId;
    }

    getKnowledgeTreeFragmentIds(nodeInfo){
        if (!nodeInfo) return [];
        let rawValue = null;
        if (nodeInfo.knowledge_fragment_id !== null && typeof nodeInfo.knowledge_fragment_id !== 'undefined') {
            rawValue = nodeInfo.knowledge_fragment_id;
        } else if (nodeInfo.externalized_contents_id !== null && typeof nodeInfo.externalized_contents_id !== 'undefined') {
            rawValue = nodeInfo.externalized_contents_id;
        }
        if (rawValue === null || typeof rawValue === 'undefined') return [];
        return String(rawValue).split(',').map((id) => id.trim()).filter((id) => id !== '');
    }

    getKnowledgeTreeFragmentNodeIds(nodeInfo){
        if (!nodeInfo) return [];
        const ids = [];
        if (nodeInfo.fragment_source_ids && typeof nodeInfo.fragment_source_ids === 'object') {
            Object.keys(nodeInfo.fragment_source_ids).forEach((sourceType) => {
                const sourceIds = Array.isArray(nodeInfo.fragment_source_ids[sourceType]) ? nodeInfo.fragment_source_ids[sourceType] : [];
                sourceIds.forEach((sourceId) => {
                    const nodeId = this.makeFragmentNodeId(sourceType, sourceId);
                    if (nodeId && ids.indexOf(nodeId) === -1) ids.push(nodeId);
                });
            });
        }
        if (ids.length) return ids;
        const fragmentIds = this.getKnowledgeTreeFragmentIds(nodeInfo);
        const sourceTypes = this.parseSourceTypes(nodeInfo.fragment_source_types, fragmentIds.length ? 'experience' : '');
        const fallbackType = sourceTypes[0] || 'experience';
        fragmentIds.forEach((fragmentId) => {
            const nodeId = this.makeFragmentNodeId(fallbackType, fragmentId);
            if (nodeId && ids.indexOf(nodeId) === -1) ids.push(nodeId);
        });
        return ids;
    }

    ensureProducedKnowledgeEdges(nodeId){
        const producedNode = this.nodes.get(nodeId);
        if (!producedNode || producedNode.group !== 'produced_knowledge') return;
        const fragmentNodeIds = Array.isArray(producedNode.knowledge_fragment_node_ids) ? producedNode.knowledge_fragment_node_ids : [];
        fragmentNodeIds.forEach((fragmentNodeId) => {
            if (!fragmentNodeId || !this.nodes.get(fragmentNodeId)) return;
            const edgeId = `kt_edge_${producedNode.produced_knowledge_id}_${fragmentNodeId}`;
            if (this.edges.get(edgeId)) return;
            this.edges.add({
                id: edgeId,
                from: nodeId,
                to: fragmentNodeId,
                arrows: '',
                color: '#90B1AB',
                group: 'produced_knowledge_fragment',
                smooth: true,
            });
        });
    }

    addProducedKnowledgeNode(nodeInfo, childMap){
        if (!nodeInfo || nodeInfo.node_id === null || typeof nodeInfo.node_id === 'undefined') return false;
        const fragmentIds = this.getKnowledgeTreeFragmentIds(nodeInfo);
        const fragmentNodeIds = this.getKnowledgeTreeFragmentNodeIds(nodeInfo);
        const sourceTypes = this.parseSourceTypes(nodeInfo.fragment_source_types, fragmentIds.length ? 'experience' : '');
        const children = childMap[String(nodeInfo.node_id)] || [];
        const isRootNode = (nodeInfo.parent_id === null || typeof nodeInfo.parent_id === 'undefined');
        if (isRootNode || children.length > 0) return false;

        const nodeId = `kt_${nodeInfo.node_id}`;
        let addedNode = false;
        if (!this.nodes.get(nodeId)) {
            const nodeTitle = nodeInfo.node_title != null ? String(nodeInfo.node_title) : '(no title)';
            this.nodes.add({
                id: nodeId,
                label: nodeTitle,
                group: 'produced_knowledge',
                produced_knowledge_id: String(nodeInfo.node_id),
                knowledge_fragment_ids: fragmentIds,
                knowledge_fragment_node_ids: fragmentNodeIds,
                source_type: sourceTypes[0] || '',
                source_types: sourceTypes,
                color: {
                    background: '#dff2e6',
                    border: '#90B1AB',
                    highlight: { background: '#d2ecd9', border: '#6f9f96' },
                    hover: { background: '#eaf7ee', border: '#6f9f96' }
                },
                shape: 'box',
                font: { color: 'black' },
                tooltip_data: {
                    sections: [
                        { heading: 'コメント', body: nodeInfo.comment || '' },
                        { heading: '更新日時', body: nodeInfo.updated_at || '' }
                    ]
                },
                fixed: false,
            });
            addedNode = true;
        } else {
            this.nodes.update({
                id: nodeId,
                knowledge_fragment_ids: fragmentIds,
                knowledge_fragment_node_ids: fragmentNodeIds,
                source_type: sourceTypes[0] || '',
                source_types: sourceTypes
            });
        }

        this.ensureProducedKnowledgeEdges(nodeId);
        this.applyNodeDisplayStyles();
        return addedNode;
    }

    refreshProducedKnowledgeView(addedCount){
        if (!addedCount || !this.ownNetwork) return;
        setTimeout(() => {
            this.applyNodeDisplayStyles();
            try{ this.ownNetwork.redraw(); }catch(_){}
            try{ this.ownNetwork.fit({ animation: false }); }catch(_){}
        }, 0);
    }

    syncProducedKnowledgeFromDom(){
        const tree = document.getElementById('overlay_knowledge_tree');
        if (!tree) return 0;
        let addedCount = 0;
        const contentTitles = tree.querySelectorAll('.kt-node .kt-content-title');
        contentTitles.forEach((titleEl) => {
            const ktNode = titleEl.closest ? titleEl.closest('.kt-node') : null;
            if (!ktNode) return;
            const nodeId = ktNode.getAttribute('data-node-id');
            if (!nodeId) return;
            const nodeInfo = {
                node_id: nodeId,
                parent_id: ktNode.classList.contains('kt-root') ? null : '__dom_parent__',
                node_title: titleEl.textContent || '',
                comment: '',
                updated_at: '',
                knowledge_fragment_id: ktNode.getAttribute('data-kfrag-id') || ''
            };
            const sourceTypes = ktNode.getAttribute('data-source-types') || '';
            if (sourceTypes) {
                nodeInfo.fragment_source_types = sourceTypes.split(',');
            }
            if (this.addProducedKnowledgeNode(nodeInfo, {})) {
                addedCount += 1;
            }
        });
        this.refreshProducedKnowledgeView(addedCount);
        return addedCount;
    }

    loadProducedKnowledgeNodes(groupId, allowGroupFallback = true){
        let url = 'php/get_knowledge_tree.php';
        if (groupId) {
            url += '?group_id=' + encodeURIComponent(groupId);
        }
        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            success: (data) => {
                if (!data || data.status !== 'ok' || !Array.isArray(data.nodes)) {
                    this.syncProducedKnowledgeFromDom();
                    return;
                }
                const childMap = {};
                data.nodes.forEach((nodeInfo) => {
                    const parentId = nodeInfo && nodeInfo.parent_id != null ? String(nodeInfo.parent_id) : 'root';
                    if (!childMap[parentId]) childMap[parentId] = [];
                    childMap[parentId].push(nodeInfo);
                });
                let addedCount = 0;
                data.nodes.forEach((nodeInfo) => {
                    if (this.addProducedKnowledgeNode(nodeInfo, childMap)) {
                        addedCount += 1;
                    }
                });
                if (addedCount === 0) {
                    addedCount = this.syncProducedKnowledgeFromDom();
                }
                if (addedCount === 0 && groupId && allowGroupFallback) {
                    this.loadProducedKnowledgeNodes('', false);
                    return;
                }
                this.applySourceFilter(this.activeSourceTypes);
                this.refreshProducedKnowledgeView(addedCount);
            },
            error: (xhr, status, error) => {
                console.warn('knowledge_tree 読み込み失敗', status, error);
                const addedCount = this.syncProducedKnowledgeFromDom();
                if (addedCount === 0 && groupId && allowGroupFallback) {
                    this.loadProducedKnowledgeNodes('', false);
                }
                this.applySourceFilter(this.activeSourceTypes);
            }
        });
    }

    addReloadTriggerNode(flag, trigger_id, edge_id, from_node, to_node, activity_id, t_label, t_type, t_time, node_x, node_y){
        let color = '#82ae46'; // ノードの背景色
        let node_shape = 'circularImage';     // ノードの形状
        var DIR_img = "../image/triggers/"; //ノードのアイコンとなる画像のパス
        let image = "thinking.png"; 
        const t_title = document.createElement("div");  // titleのHTML

        if(!activity_id){
            color = '#60bfa3'; // ノードの背景色
        }

        t_title.innerHTML = "<div id='" + trigger_id + "' class='trigger_title' timestamp='" + t_time + "'><b>"+t_type+"</b></br>" + t_label + " </div>";
        if(!node_x){
            node_x = (defaultOrganizational.nodes.get(from_node).x + defaultOrganizational.nodes.get(to_node).x ) /2; //ノードがversionの間に来るように
        }
        if(!node_y){
            node_y = Math.floor(Math.random()*200)-100;
        }

        //triggerとなった活動ごとにアイコンを変更
        switch(t_type) {
            case "自己内対話": //自己内対話
                break;
            case "議論資料作成": //議論資料作成
                image = "writing.png";
                break;
            case "議論内省": //議論内省
                image = "meeting.png";
                break;
            case "論文読解": //論文読解
                image = "reading.png";
                break;
            case "論文執筆": //論文執筆
                image = "writing-scholar.png";
                break;
            default: // その他
                break;
        }

        // trigger_fromの設定
        if(defaultOrganizational.edges.get(edge_id).group == "trigger_from"){
            // エッジの先がtrigger_nodeならその先のversion_nodeに繋ぐ
            //　trigge自身を指す新しいエッジを追加
            const newEdge = {
                from: from_node,
                to: trigger_id,
                arrows: 'dynamic',
                color: color,
                group: "trigger_from",
                smooth: true,
                fixed: true,
            };
            defaultOrganizational.edges.add(newEdge);

        }else{
            // 既存のversionEdgeをtrigger自身を指すようにエッジを繋ぎかえ
            const update_edge = defaultOrganizational.edges.get(edge_id);
            update_edge.arrows = 'dynamic';
            update_edge.color = color;
            update_edge.to = trigger_id;
            update_edge.group = "trigger_from";
            update_edge.smooth = true;
            defaultOrganizational.edges.update(update_edge);
        }

        //　trigger_toの設定
        const newEdge = {
            from: trigger_id,
            to: to_node,
            arrows: 'dynamic',
            color: color,
            group: "trigger_to",
            smooth: true,
            fixed: true,
        };
        defaultOrganizational.edges.add(newEdge);

        //triggerとなるノードを追加
        const newNode = {
            id: trigger_id,
            label: t_time,
            title: t_title,
            group: "trigger",
            type: t_type,
            color: color,
            shape: node_shape,
            image: DIR_img + image,
            imagePadding: 7,
            fixed: false,
            x: node_x, y: node_y,
        };
        defaultOrganizational.nodes.add(newNode);

        if(flag == "New"){
            defaultRecordOrganizational.record_trigger(trigger_id, activity_id, from_node, to_node, t_time, t_type, t_label, node_x, node_y);
        }

        return defaultOrganizational.edges, defaultOrganizational.nodes;
    }

    addReloadEdge(edge_id, edge_start, edge_end, edge_label) {
        this.edges.add({id: edge_id, from: edge_start, to: edge_end ,label: edge_label});
    }

    //未完成　ノード追加
    addNewNode() {
        this.addNode(this.generateUniqueNumberText(), "newNode", "process", this.latest_selected_node_info.x, this.latest_selected_node_info.y);
    }

    //ノードのラベル編集(完了)
    editNode(node_id, node_content) {
        //ノードのラベルの編集
        const node = this.nodes.get(node_id);
        if (node) { // IDに相当するノードがある場合の中身を編集
            let result_label = '';
            for (let i = 0; i < node_content.length; i += 10) {
                result_label += node_content.substr(i, 10) + '\n';
            }
            result_label = result_label.trim(); // 末尾の不要な改行を除去
            node.label = result_label;
            // 編集を反映
            this.nodes.update(node);
            defaultRecordOrganizational.update_Node("label", node_id, node_content, "");
        }
    }

    //ダブルクリック時編集(完了)
    doubleclick (params) {
        const clickedNodeId = params.nodes[0];
        if (clickedNodeId !== undefined) {
            // ユーザーに新しいラベルを尋ね、それをノードの中身に設定
            const newLabel = prompt('新しいラベルを入力してください:', this.nodes.get(clickedNodeId).label.split('\n').join(''));
            // 編集したラベルを反映
            if (newLabel !== null) {
                this.editNode(clickedNodeId, newLabel);
            }
        }
    }

    // ノード削除(完了)
    deleteNode (){
        const selectNodeId = this.ownNetwork.getSelection().nodes[0];
        if(selectNodeId !== undefined){
            console.log(defaultOrganizational.nodes.get(selectNodeId));
            const node_group = defaultOrganizational.nodes.get(selectNodeId).group;
            console.log(node_group);
            if(node_group == "trigger"){
                defaultRecordOrganizational.delete_trigger_Node(selectNodeId);
            }else{
                defaultRecordOrganizational.delete_db_Node(selectNodeId);
            }
            defaultRecordOrganizational.delete_db_Edge(null, selectNodeId, "");
            defaultRecordOrganizational.delete_db_Edge(null, "", selectNodeId);

            this.edges.remove(this.ownNetwork.getConnectedEdges(selectNodeId));
            this.nodes.remove({id: selectNodeId});
            // const ontology_index = this.OntologyConnectNodeId.indexOf(selectNodeId);
            // if(ontology_index !== -1){
            //     this.nodes.remove({ id: this.OntologyNodeId[ontology_index]});
            //     defaultRecordOrganizational.delete_db_Node(this.OntologyNodeId[ontology_index]);
            //     this.OntologyNodeId.splice(ontology_index, 1);
            //     this.OntologyConnectNodeId.splice(ontology_index, 1);
            // }
            // const connect_net_index = [];
            // this.ConnectNetworkNodeId.map((n_id, index) => {
            //     if(n_id === selectNodeId){
            //         connect_net_index.push(index);
            //     }
            // });
            // connect_net_index.sort((a, b) => b - a);
            // connect_net_index.forEach(index => {
            //     this.ConnectNetworkNodeId.splice(index, 1);
            //     this.ConnectMindMapNodeId.splice(index, 1);
            // });
            // defaultRecordOrganizational.delete_connection(selectNodeId);
        }
    }

    // 右クリック時
    onContext(params) {
        this.nodeConnectEnabled = false;
        if (params.nodes.length == 1) {
            $('#jsmind_container').css('height','50%');
            const NetworkMenu = document.getElementById('t_Organizational_conmenu');
            this.selectId = params.nodes[0];
            this.selectedOrganizationalNode = this.nodes.get(this.selectId);
            this.updateDeleteExperienceKnowledgeMenu();
            const pointerX = params.pointer.DOM.x;
            const pointerY = params.pointer.DOM.y;
            const mynetPosition = document.getElementById("myOrganizationalnetwork").getBoundingClientRect();
            this.BoxDisplay.x = pointerX + mynetPosition.left + 20;
            this.BoxDisplay.y = pointerY + mynetPosition.top + 20;
            NetworkMenu.style.left = this.BoxDisplay.x;
            NetworkMenu.style.top = this.BoxDisplay.y;
            NetworkMenu.style.display = "block";//ここようわからん未完成かも
        }
    }

    isOwnExperienceKnowledgeNode(node) {
        return node
            && node.experience_knowledge_id
            && organizational_current_user_id !== null
            && String(node.user_id) === String(organizational_current_user_id);
    }

    updateDeleteExperienceKnowledgeMenu() {
        const deleteMenu = document.getElementById('organizational_conmenu5');
        const editMenu = document.getElementById('organizational_conmenu6');
        const canEdit = this.isOwnExperienceKnowledgeNode(this.selectedOrganizationalNode);

        if (deleteMenu) deleteMenu.style.display = canEdit ? "" : "none";
        if (editMenu) editMenu.style.display = canEdit ? "" : "none";
    }

    //ラベルの選択（完了）
    show_select (){
        document.getElementById('t_Organizational_conmenu').style.display = "none";
        if(this.OntologyConnectNodeId.indexOf(this.selectId) !== -1){
            alert('このノードにはすでに概念がつけられているため概念付けできません');
            return;
        }
        const labelselect = document.getElementById("labelselect");
        labelselect.style.display = "block";
        labelselect.style.left = this.BoxDisplay.x;
        labelselect.style.top = this.BoxDisplay.y;
    }

    //概念をマップに追加（完了）
    view_otherprocessmap (){
        document.getElementById('t_Organizational_conmenu').style.display = "none";
        // 右クリックで選択されたノードIDを優先して取得
        let selectNodeId = this.selectId;
        if (!selectNodeId) {
            const sel = this.ownNetwork.getSelection();
            selectNodeId = (sel && sel.nodes && sel.nodes.length) ? sel.nodes[0] : null;
        }
        if (!selectNodeId) return;

        const others_node = defaultOrganizational.nodes.get(selectNodeId);
        if (!others_node) return;

        console.log('view_otherprocessmap -> node:', others_node);

        // process_others_network_container を表示（存在すれば）
        const procContainer = document.getElementById('process_others_network_container');
        if (procContainer) {
            procContainer.style.display = 'block';
        }

        // 他者の思考過程マップを開く（user_id が null の場合は既定動作に従う）
        try {
            showThinkingProcessMap(others_node);
        } catch (e) {
            console.error('showThinkingProcessMap error:', e);
        }
    }

    // Recruit_Idea (){
    //     document.getElementById('t_Organizational_conmenu').style.display = "none";
    //     if(this.RecruitNodeId.indexOf(this.selectId) !== -1){
    //         alert('このノードにはすでに採用不採用がつけられています');
    //         document.getElementById("organizational_conmenu3").style.display = "none";
    //         return;
    //     }
    //     const t_Organizational_recruitselect = document.getElementById("t_Organizational_recruitselect");
    //     t_Organizational_recruitselect.style.display = "block";
    //     t_Organizational_recruitselect.style.left = this.BoxDisplay.x;
    //     t_Organizational_recruitselect.style.top = this.BoxDisplay.y;
    // }

    // Selected_Recruit_Idea (){
    //     const FeedBackReflectionText = [];
    //     const FeedBackReflection = [];
    //     document.getElementById("t_Organizational_recruitselect").style.display = "none";
    //     const selectionlist = document.getElementById('t_Organizational_recruitselectionlist');
    //     const Ontology_Node_Id = this.OntologyNodeId[this.OntologyConnectNodeId.indexOf(this.selectId)];
    //     this.RecruitNodeId.push(this.selectId);
    //     this.Feedback.push(this.selectId);
    //     this.Recruit.push(selectionlist.value);
    //     if (selectionlist.value === "採用") { // IDに相当するノードがある場合の中身を編集
    //         this.nodes.update({
    //             id : Ontology_Node_Id,
    //             borderWidth: 5,
    //             color: {
    //                 border: "green",
    //             },
    //         });
    //     }else if(selectionlist.value === "棄却"){
    //         this.nodes.update({
    //             id : Ontology_Node_Id,
    //             borderWidth: 5,
    //             color: {
    //                 border: "red",
    //             },
    //         });
    //     }
    //     for(var i=0; i<this.RecruitNodeId.length-1; i++){
    //         FeedBackReflectionText.push("text"+this.RecruitNodeId[i]);
    //         FeedBackReflection.push(document.getElementById("text"+this.RecruitNodeId[i]).value);
    //     }
    //     const node_info = this.nodes.get(this.selectId);
    //     document.getElementById("accordion_discussion").innerHTML += "<div id='"+this.selectId+"' class='accordion-item'><div class='accordion-header' style='font-size:10px'>なぜ「"+node_info.label+"」は"+selectionlist.value+"されたのですか？</div><div class='accordion-content'><textarea id='text"+ this.selectId +"' class='accordion-input'></textarea></div></div>";
    //     const accordionHeaders = document.querySelectorAll('#accordion_discussion .accordion-header');
    //     accordionHeaders.forEach(header => {
    //       header.addEventListener('click', function () {
    //         const accordionItem = this.parentElement;
    //         accordionItem.classList.toggle('active');
    //       });
    //     });
    //     //追加したら消えてしまうからおいておく
    //     for(var i=0; i<this.RecruitNodeId.length-1; i++){
    //         document.getElementById("text"+this.RecruitNodeId[i]).innerHTML = FeedBackReflection[FeedBackReflectionText.indexOf("text"+this.RecruitNodeId[i])];
    //     }
    //     defaultRecordOrganizational.record_recruit(this.selectId, Ontology_Node_Id, selectionlist.value);
    //     document.getElementById("organizational_conmenu3").style.display = "none";
    // }

    ContentmenuCancel(){
        document.getElementById('t_Organizational_conmenu').style.display = "none";
    }

    deleteExperienceKnowledge(){
        const selectedNode = this.selectedOrganizationalNode || this.nodes.get(this.selectId);
        if (!this.isOwnExperienceKnowledgeNode(selectedNode)) {
            return;
        }

        document.getElementById('t_Organizational_conmenu').style.display = "none";
        if (!confirm('選択した組織知を削除していいですか')) {
            return;
        }

        $.ajax({
            url: "php/delete_experience_knowledge.php",
            type: "POST",
            dataType: "json",
            data: {
                experience_knowledge_id: selectedNode.experience_knowledge_id
            }
        }).done((response) => {
            if (response && response.status === "ok") {
                this.edges.remove(this.ownNetwork.getConnectedEdges(selectedNode.id));
                this.nodes.remove({ id: selectedNode.id });
                this.selectId = null;
                this.selectedOrganizationalNode = null;
            } else {
                alert((response && response.message) ? response.message : '組織知の削除に失敗しました');
            }
        }).fail(() => {
            alert('組織知の削除に失敗しました');
        });
    }

    openExperienceKnowledgeEdit(){
        const selectedNode = this.selectedOrganizationalNode || this.nodes.get(this.selectId);
        if (!this.isOwnExperienceKnowledgeNode(selectedNode)) {
            return;
        }

        document.getElementById('t_Organizational_conmenu').style.display = "none";

        const trigger = document.getElementById('trigger_display');
        if (trigger) trigger.style.display = 'none';

        if (typeof showLessonDisplayOverlay === 'function') {
            showLessonDisplayOverlay();
        } else {
            const lesson = document.getElementById('lesson_display');
            if (lesson) lesson.style.display = 'block';
        }

        const tooltipData = selectedNode.tooltip_data || {};
        if (typeof renderLessonForm === 'function') {
            renderLessonForm({
                knowledge_fragment_title: selectedNode.label || '',
                stage1: tooltipData.stage1 || '',
                stage2: tooltipData.stage2 || '',
                stage3: tooltipData.stage3 || ''
            });
        }

        if (typeof setLessonActionButton === 'function') {
            setLessonActionButton('編集を保存', this.saveExperienceKnowledgeEdit.bind(this));
        }
    }

    saveExperienceKnowledgeEdit(){
        const selectedNode = this.selectedOrganizationalNode || this.nodes.get(this.selectId);
        if (!this.isOwnExperienceKnowledgeNode(selectedNode)) {
            return;
        }
        if (typeof collectLessonFormData !== 'function') {
            alert('編集フォームを読み込めませんでした。ページをリロードしてください。');
            return;
        }

        const lessonData = collectLessonFormData();
        const stageData = {};
        lessonData.contents.forEach((item) => {
            stageData[item.type] = item.content;
        });

        $.ajax({
            url: "php/update_experience_knowledge.php",
            type: "POST",
            dataType: "json",
            data: {
                experience_knowledge_id: selectedNode.experience_knowledge_id,
                knowledge_fragment_title: lessonData.knowledge_fragment_title,
                contents: JSON.stringify(lessonData.contents)
            }
        }).done((response) => {
            if (response && response.status === "ok") {
                const tooltipData = selectedNode.tooltip_data || {};
                const updatedNode = Object.assign({}, selectedNode, {
                    label: lessonData.knowledge_fragment_title,
                    tooltip_data: Object.assign({}, tooltipData, {
                        stage1: stageData.stage1 || '',
                        stage2: stageData.stage2 || '',
                        stage3: stageData.stage3 || ''
                    })
                });
                this.nodes.update(updatedNode);
                this.selectedOrganizationalNode = this.nodes.get(selectedNode.id);
                alert('組織知を更新しました。');
            } else {
                alert((response && response.message) ? response.message : '組織知の更新に失敗しました');
            }
        }).fail(() => {
            alert('組織知の更新に失敗しました');
        });
    }

    //マインドマップとネットワークつなげる(今後動作確認はいる多分行けた)，(複雑なので何してるか聞きたいなら大槻まで)
    // connect_network (){
    //     document.getElementById('t_Organizational_conmenu').style.display = "none";
    //     this.nodeConnectEnabled = true;
    // }

    // マインドマップのノードがクリックされたときの処理
    // connect_mindmap (e) {
    //     const Jsmind = new jsMind({container:'jsmind_container',
    //                             editable: false});
    //     if (!this.nodeConnectEnabled) {
    //         return;
    //     }else{
    //         const mm_nodeid = Jsmind.view.get_binded_nodeid(e.target);
    //         if(mm_nodeid == null){
    //             alert('ノードのクリックがうまくできませんでした．もう一度試してみてください');
    //             return;
    //         }else{
    //             if(this.ConnectNetworkNodeId.indexOf(this.selectId) !== -1 && this.ConnectMindMapNodeId.indexOf(mm_nodeid) !== -1){
    //                 alert('このノードはすでに選択されています');
    //                 return;
    //             }
    //             defaultRecordOrganizational.record_connection(this.selectId,mm_nodeid);
    //             this.ConnectNetworkNodeId.push(this.selectId);
    //             this.ConnectMindMapNodeId.push(mm_nodeid);
    //             this.nodeConnectEnabled = false;
    //         }
    //     }
    // }

    //ノードがクリックされたときの処理
    networkClick (params){
        //他のところクリックしたら色直す
        document.getElementById("ontology_feedback").innerHTML = "";
        const feedbackarea = document.getElementsByClassName("accordion-item");
        for(var i=0; i<feedbackarea.length; i++){
            feedbackarea[i].style.display = "none";
        }
        if(this.jmindex != []){
            const jmnode = document.getElementsByTagName("jmnode");
            this.jmindex.map((n) => {
                if(jmnode[n].getAttribute("type") == "answer"){
                    jmnode[n].style.backgroundColor = "#ffffff";
                }else{
                    jmnode[n].style.backgroundColor = "#87cefa";
                }
            })
            this.jmindex.length = 0;
        }
        // if(this.RecruitNodeId.indexOf(params.nodes[0]) !== -1){
        //     this.FeedbackNodeId = params.nodes[0];
        //     document.getElementById(this.FeedbackNodeId).style.display = "block";
        // }
        // if(params.nodes.length == 1){
        //     const net_index = this.ConnectNetworkNodeId.map((n_id, index) => {
        //         return n_id === params.nodes[0] ? index : null;
        //     }).filter(n => n !== null);
        //     if(net_index == ""){
        //         return;
        //     }
        //     const jmnode = document.getElementsByTagName("jmnode");
        //     net_index.map((m_id) => {
        //         for(var i = 0; i < jmnode.length; i++){
        //             if(jmnode[i].getAttribute("nodeid") == this.ConnectMindMapNodeId[m_id]){
        //                 //ここいろかえる必要あるかも
        //                 jmnode[i].style.backgroundColor = "white";
        //                 this.jmindex.push(i);
        //                 break;
        //             }
        //         }
        //     });
        // }
    }

    addNewEdge(E_start, E_end){
        let edge_id = this.generateUniqueNumberText();
        this.edges.add({ id: edge_id ,from: E_start, to: E_end });
        defaultRecordOrganizational.record_Edge(edge_id, E_start, E_end);
    }

    //ドラッグ開始(完成)
    dragstart (params) {
        if(!this.edgeEditMode){
            params.event.preventDefault();
        }else{
            this.dragStartNodeId = this.ownNetwork.getNodeAt(params.pointer.DOM);
        }
    }

    //ドラッグ終了(完成)
    dragend (params) {
        if(this.edgeEditMode){
            this.dragEndNodeId = this.ownNetwork.getNodeAt(params.pointer.DOM);
            if(this.dragStartNodeId !== null && this.dragEndNodeId !== null && this.dragEndNodeId !== this.dragStartNodeId && this.dragEndNodeId !== undefined && this.nodes.get(this.dragStartNodeId).shape != "ellipse" && this.nodes.get(this.dragEndNodeId).shape != "ellipse"){
                let notable = true;
                const ConnectSelectNode = [];
                const connectedEdges = this.ownNetwork.getConnectedEdges(this.dragStartNodeId);
                connectedEdges.map((n)=>{
                    ConnectSelectNode.push(this.ownNetwork.getConnectedNodes(n).filter(n => n !== this.dragStartNodeId)[0]);
                });
                ConnectSelectNode.map((n)=>{
                    if(n === this.dragEndNodeId){
                        notable = false;
                    }
                });
                if(notable == false){
                    return;
                }
                let edge_id = this.generateUniqueNumberText();
                this.edges.add({id: edge_id, from: this.dragStartNodeId, to: this.dragEndNodeId });
                defaultRecordOrganizational.record_Edge(edge_id, this.dragStartNodeId, this.dragEndNodeId);
            }
            this.dragStartNodeId = null;
            this.dragEndNodeId = null;
        }else{
            const movedNodeId = params.nodes[0];
            if (movedNodeId !== undefined) {
                //なぜか更新したら色変わってしまうから一時的に
                let node_color = this.nodes.get(movedNodeId).color;
                // let border_color = '#ffdb4f'; 
                // switch(this.nodes.get(movedNodeId).group) {
                //     case "process": // 自分で考えた要約に関するノードの場合
                //         node_color = '#ffdb4f';
                //         break;
                //     case "versions": // 議論内での発言ノードの場合
                //         node_color = '#ffbaa1';
                //         break;
                //     case "topic-tag": // 議論内省マップのノードがどんなトピックに対応しているかを表すタグノードの場合
                //         node_color = '#ffdb4f';
                //         break;
                //     default: // その他
                //         break;
                // }
                this.nodes.update({ id: movedNodeId, color: node_color, x: params.pointer.x, y: params.pointer.y });
                const nodeBoundingBox = this.ownNetwork.getBoundingBox(movedNodeId);
                //次に追加したノードの座標指定
                this.latest_selected_node_info.x = (nodeBoundingBox.right + nodeBoundingBox.left)/2;
                this.latest_selected_node_info.y = nodeBoundingBox.bottom + 10;
                
                defaultRecordOrganizational.update_Node("point" ,movedNodeId, (nodeBoundingBox.right + nodeBoundingBox.left)/2, (nodeBoundingBox.bottom + nodeBoundingBox.top)/2)
                // const ontology_index = this.OntologyConnectNodeId.indexOf(movedNodeId);
                // if(ontology_index !== -1){
                //     const nodeBoundingBox = this.ownNetwork.getBoundingBox(movedNodeId);
                //     const ontology_x = nodeBoundingBox.left;
                //     const ontology_y = nodeBoundingBox.top;
                //     console.log(this.Recruit[this.RecruitNodeId.indexOf(this.OntologyConnectNodeId[this.OntologyNodeId.indexOf(this.OntologyNodeId[ontology_index])])]);
                //     if(this.Recruit[this.RecruitNodeId.indexOf(this.OntologyConnectNodeId[this.OntologyNodeId.indexOf(this.OntologyNodeId[ontology_index])])]==="採用"){
                //         border_color = 'green'; 
                //     }else if(this.Recruit[this.RecruitNodeId.indexOf(this.OntologyConnectNodeId[this.OntologyNodeId.indexOf(this.OntologyNodeId[ontology_index])])]==="棄却"){
                //         border_color = 'red'; 
                //     }
                //     this.nodes.update({ id: this.OntologyNodeId[ontology_index], color: { background: 'blue', border: border_color}, x: ontology_x, y: ontology_y });
                //     defaultRecordOrganizational.update_Node("point" ,this.OntologyNodeId[ontology_index], ontology_x, ontology_y);
                // }
            }
        }
    }
    
    // エッジの削除（完了）
    deleteEdge() {
        const selectEdgeId = this.ownNetwork.getSelection().edges[0];
        const startid = this.edges.get(selectEdgeId).from;
        const endid = this.edges.get(selectEdgeId).to;
        if(selectEdgeId !== undefined){
            this.edges.remove({id: selectEdgeId});
            defaultRecordOrganizational.delete_db_Edge(selectEdgeId, startid, endid);
            // const Edge_index = this.OntologyConnectNodeId.indexOf(startid);
            // if(Edge_index !== -1){
            //     this.EdgeStartId.splice(Edge_index, 1);
            //     this.EdgeEndId.splice(Edge_index, 1);
            // }
        }
    }

    // Nodeblinking() {
    //     this.Feedback.map((n) => {
    //         const feedbacknode = this.nodes.get(n);
    //         let node_color = '#ffdb4f'; // ノードの背景色
    //         switch(feedbacknode.group) {
    //             case "material-content": // 議論資料に書かれた内容に関するノードの場合
    //                 break;
    //             case "process": // 自分で考えた要約に関するノードの場合
    //                 node_color = 'green';
    //                 break;
    //             case "utterance": // 議論内での発言ノードの場合
    //                 node_color = 'pink';
    //                 break;
    //             case "topic-tag": // 議論内省マップのノードがどんなトピックに対応しているかを表すタグノードの場合
    //                 node_color = 'blue';
    //                 break;
    //             default: // その他
    //                 break;
    //         }
    //         const borderWidth = feedbacknode.borderWidth === 0 ? 5 : 0;
    //         this.nodes.update({ id: n, color: { background: node_color, border: "red"}, borderWidth: borderWidth });
    //     })
    // }

    addMaterialOntology(material_id, concept_id){
        $.ajax({
            url:'js/hozo.xml',
            type:'get',
            dataType:'xml',
            timeout:1000,
            success: (xml,status) => {
                if(status!='success')return;
                const XML = $(xml).find('W_CONCEPTS');
                const concept = Array.from(XML[0].getElementsByTagName('CONCEPT'));
                //hozo.xmlファイルのタグを検索して変数に格納（たぶん，全てのタグが配列で格納されている），thisはhozo.xmlのことかな
                concept.map((content)=>{
                    if(content.getAttribute('id') === concept_id){
                        const slot_content = Array.from(content.getElementsByTagName("SLOT"));
                        slot_content.map((content_slot) => {
                            if(content_slot.getAttribute("role") === "出力"){
                                const nodeBoundingBox = defaultOrganizational.ownNetwork.getBoundingBox(material_id);
                                this.addNode(concept_id, content_slot.getAttribute("class_constraint"), "topic-tag", nodeBoundingBox.left, nodeBoundingBox.top);
                                this.OntologyConnectNodeId.push(material_id);
                                this.OntologyNodeId.push('topic-tag_'+concept_id);
                                defaultRecordOrganizational.record_ontology(material_id, 'topic-tag_'+concept_id);
                            }
                        });
                    }
                });
            }
        });
    }

    zoomIn() {
        const scale = this.ownNetwork.getScale() * 1.1; // Increase scale by 10%
        this.scale = scale;
        this.ownNetwork.moveTo({ scale: scale });
    }
      
    zoomOut() {
        const scale = this.ownNetwork.getScale() * 0.9; // Decrease scale by 10%
        this.scale = scale;
        this.ownNetwork.moveTo({ scale: scale });
    }

}

// ネットワーク関係の記録
class RecordOrganizational{
    //ノードの記録(完了)
    record_Node (id, label, node_type, x, y){
        let selected_node_id = document.getElementById('others_conceptdisplay').getAttribute('nodeId');
        $.ajax({
            url: "php/organizational_edit_map_maneger.php",
            type: "POST",
            data: {node_id : id,
                label : label,
                node_type: node_type,
                x : x,
                y : y,
                selected_node_id: selected_node_id,
                purpose : 'record',
                record_thing: 'node'},
        });
    }

    //エッジの記録(完了)
    record_Edge (edge_id, edge_start, edge_end){
        $.ajax({
            url: "php/organizational_edit_map_maneger.php",
            type: "POST",
            data: {edge_id: edge_id,
                edge_start : edge_start,
                edge_end : edge_end,
                purpose : 'record',
                record_thing: 'edge'},
        });
    }

    //ノードの更新(完了)
    update_Node (select_update, id, node_update_thing1, node_update_thing2){
        $.ajax({
            url: "php/organizational_edit_map_maneger.php",
            type: "POST",
            data: {select_update : select_update,
                node_id : id,
                purpose : 'update',
                update_thing : 'node',
                node_update_thing1 : node_update_thing1,
                node_update_thing2: node_update_thing2},
            success:function(e){
                if(e){
                    console.log(e);
                }
            }
        });
    }

    //ノードの削除(完了)
    delete_db_Node (id){
        $.ajax({
            url: "php/organizational_edit_map_maneger.php",
            type: "POST",
            data: {node_id : id,
                purpose : 'delete',
                delete_thing : 'node'},
        });
    }

    //triggerの削除
    delete_trigger_Node (id){
        $.ajax({
            url: "php/organizational_edit_map_maneger.php",
            type: "POST",
            data: {trigger_id : id,
                purpose : 'delete',
                delete_thing : 'trigger'},
            success: function(e){
                if(e){
                    console.log(e);
                }
            }
        });
    }
    
    //エッジの削除(完了)
    delete_db_Edge (edge_id, edge_start,edge_end){
        $.ajax({
            url: "php/organizational_edit_map_maneger.php",
            type: "POST",
            data: {edge_id: edge_id,
                edge_start : edge_start,
                edge_end : edge_end,
                purpose : 'delete',
                delete_thing : 'edge'},
                success: function(e){
                    if(e){
                        console.log(e);
                    }
                }
        });
    }

    // delete_connection (id){
    //     $.ajax({
    //         url: "../php/organizational_edit_map_maneger.php",
    //         type: "POST",
    //         data: {node_id : id,
    //             purpose : 'delete',
    //             delete_thing : 'connection'},
    //     });
    // }

    // //繋げたものをDBに記録
    // record_connection (NetworkNodeId,MindMapNodeId){
    //     $.ajax({
    //         url: "../php/organizational_edit_map_maneger.php",
    //         type: "POST",
    //         data: {networknodeid : NetworkNodeId,
    //             mindmapnodeid : MindMapNodeId,
    //             purpose : 'record',
    //             record_thing : 'connection'},
    //     });
    // }

    // // オントロジーの対応付けの記録
    // record_ontology (node_id, ontology_node_id){
    //     $.ajax({
    //         url: "../php/organizational_edit_map_maneger.php",
    //         type: "POST",
    //         data: {node_id : node_id,
    //             ontology_node_id : ontology_node_id,
    //             purpose : 'record',
    //             record_thing : 'ontology'},
    //     });
    // }

    // // オントロジーの対応付けの記録
    // record_recruit (node_id, ontology_node, result){
    //     $.ajax({
    //         url: "../php/organizational_edit_map_maneger.php",
    //         type: "POST",
    //         data: {node_id : node_id,
    //             ontology_node : ontology_node,
    //             result_recruit : result,
    //             purpose : 'record',
    //             record_thing : 'recruit'},
    //     });
    // }

    record_trigger(trigger_id, activity_id, from, to, time, activity_type, content, x, y){
        
        $.ajax({
            url: "php/organizational_edit_map_maneger.php",
            type: "POST",
            data: {trigger_id : trigger_id,
                activity_id : activity_id,
                node_version_from : from,
                node_version_to : to,
                activity_time : time,
                activity_type : activity_type,
                content : content,
                x : x,
                y : y,
                purpose: 'record',
                record_thing : 'trigger'},
            success: function(e){
                if(e){
                    console.log(e);
                }else{
                    if(activity_id){
                        document.getElementById(activity_id).trigger_on = 1;
                        document.getElementById(activity_id).style.background = "#979997";
                        document.getElementById(activity_id).style.borderWidth = 3;
                    }
                }
            }
        });
    }
}


/*
 * データベースからの読み込み
 */
let organizational_mode;
let organizational_group_id;
let organizational_list;
let organizational_current_user_id = null;

function dispatchOrganizationalGroupChanged(groupId) {
    try {
        const detail = { group_id: groupId ? String(groupId) : '' };
        let event;
        if (typeof CustomEvent === 'function') {
            event = new CustomEvent('organizationalGroupChanged', { detail: detail });
        } else {
            event = document.createEvent('CustomEvent');
            event.initCustomEvent('organizationalGroupChanged', false, false, detail);
        }
        document.dispatchEvent(event);
    } catch (e) {
        console.warn('organizationalGroupChanged dispatch failed', e);
    }
}

const getOrganizationalMapDataFromDB = (callback) => {
    console.log(organizational_group_id);
    //選択されているノードIDとconcept_id
    return new Promise((resolve, reject) => {
        try{
            return $.ajax({
                url: "php/organizational_map_manager.php",
                type: "POST",
                data: {
                    mode: organizational_mode,
                    group_id: organizational_group_id,
                },
            }).success((r) => {
                // console.log(r);
                organizational_list = JSON.parse(r);
                organizational_current_user_id = organizational_list.current_user_id || null;
                if (organizational_list.selected_group_id !== undefined && organizational_list.selected_group_id !== null) {
                    organizational_group_id = organizational_list.selected_group_id;
                }
                console.log(organizational_list);
                callback(organizational_list);
            });
        } catch (error){
            reject(error);
        }
    });
}

// 組織知マップを表示
const displayOrganizationalData = (mode, selected_group_id) => {
    organizational_mode = mode;
    organizational_group_id = selected_group_id;

    if(mode=="all"){
        getOrganizationalMapDataFromDB ((organizational_list_info) => {
            const effectiveSelectedGroupId = organizational_list_info.selected_group_id || selected_group_id || '';
            // group_selectのoptionを動的に生成
            const groupSelect = document.getElementById('group_select');
            if (groupSelect && organizational_list_info.groups) {
                groupSelect.innerHTML = '';
                // プレースホルダ（デフォルトは何も選択されていない表示）
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = 'ー組織を選択ー';
                placeholder.disabled = true;
                // selected_group_id が渡されていなければプレースホルダを選択状態にする
                if (!effectiveSelectedGroupId) {
                    placeholder.selected = true;
                }
                groupSelect.appendChild(placeholder);
                organizational_list_info.groups.forEach((group) => {
                    const option = document.createElement('option');
                    option.value = group.group_id;
                    option.textContent = group.name ? group.name : group.group_id;
                    // selected_group_id が指定されていればその値を選択状態にする
                    if (effectiveSelectedGroupId && String(group.group_id) === String(effectiveSelectedGroupId)) {
                        option.selected = true;
                    }
                    groupSelect.appendChild(option);
                });
                if (effectiveSelectedGroupId) {
                    groupSelect.value = effectiveSelectedGroupId;
                }
                dispatchOrganizationalGroupChanged(effectiveSelectedGroupId);
            }
            let j = 0;
            // ユーザーのアイコンを表示
            organizational_list_info.users.forEach((v) => {
                defaultOrganizational.addUserNode(v.user_id, v.name, "users");
            });
            // ユーザーごとの思考過程ノードを表示
            organizational_list_info.enode.map((n) => {
                defaultOrganizational.addReloadProcessNode(
                    n.user_id,
                    n.display_node_id || defaultOrganizational.makeFragmentNodeId(n.source_type, n.source_id || n.experience_knowledge_id || n.externalized_contents_id),
                    n.knowledge_fragment_content,
                    n.experience_type,
                    n.concept_id,
                    n.thought_experience_node_id,
                    n.selected_contents,
                    n.stage1,
                    n.stage2,
                    n.stage3,
                    n.source_type,
                    n.source_id || n.experience_knowledge_id || n.externalized_contents_id
                );
            });
            defaultOrganizational.applyNodeDisplayStyles();
            try{ defaultOrganizational.ownNetwork.redraw(); }catch(_){}
            // ユーザーごとのTriggerノードを表示
            // organizational_list_info.tnode.map((t) => {
            //     defaultOrganizational.addReloadTriggerNode(t.user_id, t.trigger_node_id, t.content, t.trigger_node_type);
            // });
            defaultOrganizational.loadProducedKnowledgeNodes(effectiveSelectedGroupId);
        });
    }
    

    const nodes = this.nodes;
    const edges = this.edges;

    addeventdisplayOrganizationalData();
       
    
}

// group_selectのchangeイベントでgroupごとにマップを再表示
document.addEventListener('DOMContentLoaded', function() {
    const groupSelect = document.getElementById('group_select');
    if (groupSelect) {

        groupSelect.addEventListener('change', function() {
            const selectedGroupId = this.value;
            // 初期化
            defaultOrganizational = new Organizational("myOrganizationalnetwork", "load");
            displayOrganizationalData("all", selectedGroupId);
        });
    }
});

const addeventdisplayOrganizationalData = () => {
    let mousedownId = null;
    const accordionHeaders = document.querySelectorAll('#accordion_discussion .accordion-header');
    accordionHeaders.forEach(header => {
        header.addEventListener('click', function () {
            const accordionItem = this.parentElement;
            accordionItem.classList.toggle('active');
        });
    });
    const feedbackarea = document.getElementsByClassName("accordion-item");
    for(var i=0; i<feedbackarea.length; i++){
        feedbackarea[i].style.display = "none";
    }
}

function showOrganizationalMap(){
  
    defaultOrganizational = new Organizational("myOrganizationalnetwork", "load");
    displayOrganizationalData("all", null);
  
}

function closeOthersThinkingProcessMap(){
  
    $('#process_others_network_container').css('display','none');
    // restore previous height if we pinned it during split view
    var prevH = null;
    try{
        var oc = document.getElementById('organizational_container');
        if(oc && oc.dataset && oc.dataset.prevHeight){
            prevH = parseFloat(oc.dataset.prevHeight);
            delete oc.dataset.prevHeight;
        }
    }catch(_){ prevH = null; }
    $('#organizational_container').css({
        'display':'block',
        'width':'calc(100vw - 350px)',
        'height': (prevH && !isNaN(prevH) && prevH > 0) ? (Math.round(prevH) + 'px') : '100%',
        'flex':'none'
    });
    $('#myOrganizationalnetwork_area').css({
        'height':'100%',
        'flex':'none'
    });
    // スプリッターがあれば削除
    const splitter = document.querySelector('.organizational-splitter');
    if(splitter) splitter.remove();

    // vis.js needs a redraw after container resize changes, otherwise the canvas can go blank.
    try{
        if(typeof defaultOrganizational !== 'undefined' && defaultOrganizational && defaultOrganizational.ownNetwork){
            setTimeout(function(){
                try{ defaultOrganizational.ownNetwork.redraw(); }catch(_){}
                try{ defaultOrganizational.ownNetwork.fit({animation:false}); }catch(_){}
            }, 0);
        }
    }catch(_){ }
}

// ロードした際の関数
window.addEventListener('load', () => {
    organizational_mode = "all";

    // 初期表示時点でいくつかのオブジェクトを非表示にする
    defaultOrganizational = new Organizational("myOrganizationalnetwork", "load");
    // マップ編集ボタンにイベント付与
    $(`#organizational_addNode`).on("click", e => {
        defaultOrganizational.addNewNode();
    });
    $(`#organizational_removeNode`).on("click", e => {
        defaultOrganizational.deleteNode();
    });
    $(`#organizational_startEditEdge`).on("click", e => {
        defaultOrganizational.SelectEditEdge();
    });
    $(`#organizational_removeEdge`).on("click", e => {
        defaultOrganizational.deleteEdge();
    });
    $(`#organizational_ZoomIn`).on("click", e => {
        defaultOrganizational.zoomIn();
    });
    $(`#organizational_ZoomOut`).on("click", e => {
        defaultOrganizational.zoomOut();
    });

    displayOrganizationalData("all", null);
    console.log("Organizational Map Loaded");

    const accordionHeaders = document.querySelectorAll('#accordion_discussion .accordion-header');
    accordionHeaders.forEach(header => {
      header.addEventListener('click', function () {
        const accordionItem = this.parentElement;
        accordionItem.classList.toggle('active');
      });
    });
});
