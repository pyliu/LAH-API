if (Vue) {
    Vue.component("lah-org-chart", {
        template: `<b-card border-variant="white">
            <div class="position-fixed bg-white rounded p-2 border" style="z-index: 1000">
                <b-button-group>
                    <b-form-checkbox v-b-tooltip.hover.top="'切換顯示分類'" inline v-model="role_switch" switch>
                        <span>{{filter_by_text}}</span>
                    </b-form-checkbox>
                    <b-form-checkbox v-b-tooltip.hover.top="'切換圖形方向'" inline v-model="orientation_switch" switch>
                        <span>{{orientation_text}}</span>
                    </b-form-checkbox>
                </b-button-group>
            </div>
            <lah-tree ref="tree" :root="root" :orientation="orientation" :node-margin="margin"></lah-tree>
        </b-card>`,
        data: () => ({
            reload_timer: null,
            config: null,
            depth: 0,
            margin: 15,
            role_switch: true,
            orientation_switch: false,
            root: null,
            canvas_height: 300
        }),
        computed: {
            depth_switch() { return this.depth < 2 ? false : true },
            filter_by_text() { return this.role_switch ? '角色分類' : '職務分類' },
            orientation_text() { return this.orientation_switch ? '左到右' : '上到下' },
            orientation() { return this.orientation_switch ? 'WEST' : 'NORTH' }
        },
        watch: {
            role_switch(val) { this.reload() },
            orientation_switch(val) { this.reload() },
            myinfo() { this.reload() }
        },
        methods: {
            reload() {
                this.getLocalCache('lah-org-chart').then(cached => {
                    if (cached === false) {
                        this.isBusy = true;
                        this.$http.post(CONFIG.API.JSON.USER, {
                            type: "org_data"
                        }).then(res => {
                            console.assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, `取得組織樹狀資料回傳狀態碼有問題【${res.data.status}】`);
                            if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                                this.setLocalCache('lah-org-chart', res.data.raw);
                                this.root = this.nodeChief(res.data.raw);
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
                        this.root = this.nodeChief(cached);
                    }
                });
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
                    HTMLclass: `mynode ${this.myid == staff.id ? 'bg-info text-white font-weight-bold' : 'bg-muted'}`,
                    pseudo: false
                };
            },
            nodePseudo(nodes, staff) {
                // preapre pseudo node by title
                let found = nodes.find((item, idx, array) => {
                    return item.role == (this.role_switch ? staff.title : staff.work);
                });
                if (!found) {
                    found = {
                        // text: this.role_switch ? staff.title : staff.work,
                        pseudo: true,
                        stackChildren: true,
                        connectors: { type: 'curve', stackIndent: this.margin },
                        siblingSeparation: 0,
                        levelSeparation: 0,
                        subTeeSeparation: 0,
                        children: [],
                        role: this.role_switch ? staff.title : staff.work
                    };
                    // add new pseudo node
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
                    let pseudo_nodes = [];
                    raw_obj.staffs.forEach( staff => {
                        // employees under section chief filtered on demand
                        if (this.empty(staff.staffs)) {
                            let found = this.nodePseudo(pseudo_nodes, staff);
                            found.children.push(this.nodeStaff(staff));
                            found.stackChildren = found.children.length > 1;
                        } else {
                            let obj = this.nodeChief(staff);
                            if (obj) {
                                children.push(obj);
                            }
                        }
                    } );
                    children = [...children, ...pseudo_nodes];
                }
                this.depth--;
                let collapsable = this.depth_switch && children.length > 0;
                let inf_chief = (raw_obj.authority & 4 && raw_obj.unit == '資訊課');
                // expand own unit by global myinfo variable
                let isMyUnit = this.myinfo ? raw_obj.unit == this.myinfo['unit'] : false;
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
                    collapsed: collapsable && !isMyUnit,
                    stackChildren: this.depth_switch,
                    HTMLclass: `mynode usercard ${this.myid == raw_obj.id ? 'bg-dark text-white font-weight-bold' : 'bg-muted'}`,
                    pseudo: false
                };
                // children will affect stackChildren ... 
                if (children.length > 0) this_node.children = [...children];
                return this_node;
            }
        },
        created() {
            window.addEventListener("resize", e => this.canvas_height = window.innerHeight - 165 );
        },
        mounted() { this.canvas_height = window.innerHeight - 165 }
    });
} else {
    console.error("vue.js not ready ... lah-org-chart component can not be loaded.");
}