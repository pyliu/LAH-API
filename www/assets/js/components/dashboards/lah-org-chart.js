if (Vue) {
    Vue.component("lah-org-chart", {
        template: `<div id="e6e03333-5899-4cad-b934-83189668a148" class="w-100 h-100"></div>`,
        data: () => ({
            inst: null,
            reload_timer: null,
            resize_timer: null,
            config: null,
            depth: 0,
            margin: 15
        }),
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
                        rootOrientation: 'WEST',
                        // animateOnInit: false,
                        // nodeAlign: 'TOP',
                        siblingSeparation: this.margin,
                        levelSeparation: this.margin,
                        subTeeSeparation: this.margin/*,
                        callback: {
                            onCreateNode: null,
                            onCreateNodeCollapseSwitch: null,
                            onAfterAddNode: null,
                            onBeforeAddNode: null,
                            onAfterPositionNode: null,
                            onBeforePositionNode: null,
                            onToggleCollapseFinished: null,
                            onAfterClickCollapseSwitch: null,
                            onBeforeClickCollapseSwitch: null,
                            onTreeLoaded: null
                        }
                        */
                    },
                    nodeStructure: this.nodeStructure(raw)
                };
            },
            nodeStructure(raw_obj) {
                this.depth++;
                if (!raw_obj.id) {
                    return false
                };
                let children = [];
                if (!this.empty(raw_obj.staffs)) {
                    raw_obj.staffs.forEach( staff => {
                        let obj = this.nodeStructure(staff);
                        if (obj !== false) {
                            children.push(obj);
                        }
                    } );
                }
                this.depth--;
                let collapsable = this.depth_switch && children.length > 0;
                return {
                    text: {
                        name: `${raw_obj.id}:${raw_obj.name}`,
                        title: raw_obj.title,
                        contact: `${raw_obj.ext}`,
                        desc: raw_obj.work
                    },
                    image: `assets/img/users/${raw_obj.name}_avatar.jpg`,
                    collapsable: collapsable,
                    collapsed: collapsable && !(raw_obj.authority & 4 && raw_obj.unit == '資訊課'),
                    stackChildren: this.depth_switch && children.length > 1,
                    HTMLclass: "mynode bg-muted",
                    pseudo: false,
                    children: [...children]
                };
            }
        },
        computed: {
            depth_switch() { return this.depth < 2 ? false : true }
        },
        created() {
            window.addEventListener("resize", e => {
                clearTimeout(this.resize_timer);
                this.resize_timer = this.delay(this.reload, 500);
            });
        },
        mounted() { this.reload() }
    });
} else {
    console.error("vue.js not ready ... lah-org-chart component can not be loaded.");
}