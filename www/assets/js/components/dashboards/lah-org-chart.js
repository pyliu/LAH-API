if (Vue) {
    Vue.component("lah-org-chart", {
        template: `<b-card>
            <div class="d-flex justify-content-between">
                <h4 class="align-middle my-auto font-weight-bolder">組織架構圖</h4>
                <b-button-group>
                    <b-form-checkbox v-b-tooltip.hover.top="'切換顯示分類'" inline v-model="filter_switch" switch>
                        <span>{{filter_by_text}}</span>
                    </b-form-checkbox>
                    <b-form-checkbox v-b-tooltip.hover.top="'切換圖形方向'" inline v-model="orientation_switch" switch>
                        <span>{{orientation_text}}</span>
                    </b-form-checkbox>
                </b-button-group>
            </div>
            <div id="e6e03333-5899-4cad-b934-83189668a148" class="w-100 h-100"></div>
        </b-card>`,
        data: () => ({
            inst: null,
            reload_timer: null,
            resize_timer: null,
            config: null,
            depth: 0,
            margin: 15,
            filter_switch: false,
            orientation_switch: false,
            orientation: 'NORTH'
        }),
        watch: {
            orientation(val) { this.reload() },
            filter_switch(val) { this.reload() },
            orientation_switch(val) {
                this.orientation = val ? 'WEST' : 'NORTH';
                this.reload();
            }
        },
        methods: {
            reload() {
                this.getLocalCache('lah-org-chart').then(cached => {
                    if (cached === false) {
                        this.isBusy = true;
                        this.$http.post(CONFIG.API.JSON.QUERY, {
                            type: "org_data"
                        }).then(res => {
                            console.assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, `取得組織樹狀資料回傳狀態碼有問題【${res.data.status}】`);
                            if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                                this.setLocalCache('lah-org-chart', res.data.raw);
                                this.build(res.data.raw);
                            } else {
                                this.alert({
                                    title: `取得組織樹狀資料`,
                                    message: `取得組織樹狀資料回傳狀態碼有問題【${res.data.status}】`,
                                    variant: "warning"
                                });
                            }
                        }).catch(err => {
                            this.error = err;
                        }).finally(() => {
                            this.isBusy = false;
                        });
                    } else {
                        this.build(cached);
                    }
                });
            },
            build(raw) {
                clearTimeout(this.reload_timer);
                this.reload_timer = this.delay(() => {
                    this.isBusy = true;
                    this.prepareConfig(raw);
                    this.inst = new Treant(this.config, () => {
                        this.depth = 0;
                        this.isBusy = false;
                    }, $);
                    // this.$log(this.inst);
                }, 1000);
            },
            prepareConfig(raw) {
                this.config = {
                    chart: {
                        container: `#e6e03333-5899-4cad-b934-83189668a148`,
                        connectors: {
                            type: 'step' // curve, bCurve, step, straight
                        },
                        node: {
                            HTMLclass: 'mynode',
                            // collapsable: true,
                            // stackChildren: true
                        },
                        rootOrientation: this.orientation || 'WEST',
                        // animateOnInit: false,
                        // nodeAlign: 'TOP',
                        siblingSeparation: this.margin,
                        levelSeparation: this.margin,
                        subTeeSeparation: this.margin,
                        callback: {
                            onCreateNode:  function(e) {},
                            onCreateNodeCollapseSwitch:  function(e) {},
                            onAfterAddNode:  function(e) {},
                            onBeforeAddNode:  function(e) {},
                            onAfterPositionNode:  function(e) {},
                            onBeforePositionNode:  function(e) {},
                            onToggleCollapseFinished:  function(e) {},
                            onBeforeClickCollapseSwitch:  function(e) {},
                            onTreeLoaded: function(e) {},
                            onAfterClickCollapseSwitch: (node) => {}
                        }
                    },
                    nodeStructure: this.nodeChief(raw)
                };
            },
            nodeStaff(staff) {
                return {
                    text: {
                        name: { val: `${staff.id}:${staff.name}`, href: `javascript:vueApp.popUsercard('${staff.id}')` },
                        title: staff.title,
                        contact: `#${staff.ext} ${staff.work}`,
                        desc: ``,
                        "data-id": staff.id,
                        "data-name": staff.name
                    },
                    image: `assets/img/users/${staff.name}_avatar.jpg`,
                    HTMLclass: `mynode ${this.myid == staff.id ? 'bg-dark text-white font-weight-bold' : 'bg-muted'}`,
                    pseudo: false
                };
            },
            getPersudoNode(nodes, staff) {
                // preapre persudo node by title
                let found = nodes.find((item, idx, array) => {
                    return item.text == (this.filter_by_title ? staff.title : staff.work);
                });
                if (!found) {
                    found = {
                        text: this.filter_by_title ? staff.title : staff.work,
                        pseudo: true,
                        stackChildren: true,
                        connectors: { type: 'curve' },
                        siblingSeparation: 0,
                        levelSeparation: 0,
                        subTeeSeparation: 0,
                        children: []
                    };
                    // add new title persudo node
                    nodes.push(found);
                }
                return found;
            },
            nodeChief(raw_obj) {
                this.depth++;
                if (!raw_obj.id) {
                    return false
                };
                let children = [];
                if (!this.empty(raw_obj.staffs)) {
                    let persudo_nodes = [];
                    raw_obj.staffs.forEach( staff => {
                        // employees under section chief filtered on demand
                        if (this.empty(staff.staffs)) {
                            let found = this.getPersudoNode(persudo_nodes, staff);
                            found.children.push(this.nodeStaff(staff));
                            found.stackChildren = found.children.length > 1;
                        } else {
                            let obj = this.nodeChief(staff);
                            if (obj) {
                                children.push(obj);
                            }
                        }
                    } );
                    children = [...children, ...persudo_nodes];
                }
                this.depth--;
                let collapsable = this.depth_switch && children.length > 0;
                let inf_chief = (raw_obj.authority & 4 && raw_obj.unit == '資訊課');
                let this_node =  {
                    text: {
                        name: { val: `${raw_obj.id}:${raw_obj.name}`, href: `javascript:vueApp.popUsercard('${raw_obj.id}')` },
                        title: raw_obj.title,
                        contact: `#${raw_obj.ext} ${raw_obj.work}`,
                        desc: ``,
                        "data-id": raw_obj.id,
                        "data-name": raw_obj.name
                    },
                    image: `assets/img/users/${raw_obj.name}_avatar.jpg`,
                    collapsable: this.depth_switch && children.length > 0,
                    connectors: { type: collapsable ? 'bCurve' : 'step' },
                    collapsed: collapsable && !inf_chief,
                    stackChildren: this.depth_switch && children.length > 1,
                    HTMLclass: `mynode ${this.myid == raw_obj.id ? 'bg-dark text-white font-weight-bold' : 'bg-muted'}`,
                    pseudo: false
                };
                // children will affect stackChildren ... 
                if (children.length > 0) this_node.children = [...children];
                return this_node;
            }
        },
        computed: {
            depth_switch() { return this.depth < 2 ? false : true },
            filter_by_title() { return this.filter_switch },
            filter_by_text() { return this.filter_by_title ? '角色分類' : '職務分類' },
            orientation_text() { return this.orientation_switch ? '左到右' : '上到下' }
        },
        created() {
            window.addEventListener("resize", e => {
                clearTimeout(this.resize_timer);
                this.resize_timer = this.delay(() => {
                    this.inst.tree.reload();
                }, 500);
            });
        },
        mounted() { this.reload() }
    });
} else {
    console.error("vue.js not ready ... lah-org-chart component can not be loaded.");
}