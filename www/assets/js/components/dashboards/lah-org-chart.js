if (Vue) {
    Vue.component("lah-org-chart", {
        template: `<div :id="id" class="w-100 h-100"></div>`,
        data: () => ({
            id: 'treant',
            inst: null,
            reload_timer: null,
            resize_timer: null,
            config: null,
            depth: 0,
            config2: {
                container: `#treant`,
                connectors: {
                    type: 'step' // curve, bCurve, step, straight
                },
                node: {
                    HTMLclass: 'mynode',
                    // collapsable: true,
                    stackChildren: true

                },
                rootOrientation: 'NORTH',
                nodeAlign: 'TOP',
                animateOnInit: false
            },
            treant_config: []
        }),
        methods: {
            reload() {
                this.isBusy = true;
                clearTimeout(this.reload_timer);
                this.$http.post(CONFIG.API.JSON.QUERY, {
                    type: "org_data"
                }).then(res => {
                    console.assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, `取得組織樹狀資料回傳狀態碼有問題【${res.data.status}】`);
                    if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                        /*
                        this.config = {
                            chart: {
                                container: `#${this.id}`,
                                connectors: {
                                    type: 'step' // curve, bCurve, step, straight
                                },
                                node: {
                                    HTMLclass: 'mynode',
                                    // collapsable: true,
                                    stackChildren: true

                                },
                                rootOrientation: 'NORTH',
                                nodeAlign: 'TOP',
                                animateOnInit: false
                            },
                            nodeStructure: this.nodeStructure(res.data.raw)
                        };
                        this.reload_timer = this.delay(() => {
                            this.inst = new Treant(this.config);
                            this.depth = 0;
                        }, 1000);
                        */
                       this.config2.container = `#${this.id}`;
                       this.treant_config.length = 0;
                       this.treant_config.push(this.config2);
                       this.$log(res.data.raw);
                       this.add(res.data.raw);
                       this.$log(this.treant_config);
                       this.reload_timer = this.delay(() => {
                           this.inst = new Treant(this.treant_config);
                           this.depth = 0;
                       }, 1000);
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
            },
            build() {
                this.config = {
                    chart: {
                        container: `#${this.id}`,
                        connectors: {
                            type: 'bCurve' // curve, bCurve, step, straight
                        },
                        node: {
                            HTMLclass: 'nodeExample1'
                        },
                        rootOrientation: 'NORTH',
                        nodeAlign: 'TOP',
                        animateOnInit: false
                    },
                    nodeStructure: {
                        text: {
                            name: "劉瑞德",
                            title: "主任",
                            contact: "03-4917647#100"
                        },
                        image: "assets/img/users/劉瑞德_avatar.jpg",
                        collapsable: true,
                        collapsed: false,
                        children: [{
                            text: {
                                name: "鍾玉美",
                                title: "秘書",
                                contact: "03-4917647#300",
                            },
                            stackChildren: true,
                            image: "assets/img/users/鍾玉美_avatar.jpg",
                            collapsable: true,
                            collapsed: false,
                            connectors: {
                                type: 'step'
                            },
                            children: [{
                                text: {
                                    name: "麥志中",
                                    title: "登記課長",
                                    contact: '#101'
                                },
                                stackChildren: true,
                                image: "assets/img/users/麥志中_avatar.jpg",
                                collapsable: true,
                                collapsed: false,
                                pseudo: false,
                                children: [{
                                        text: {
                                            name: "曾奕融",
                                            title: "課員",
                                            contact: {
                                                val: "1184.b@mail.tyland.gov.tw",
                                                href: "mailto:1184.b@mail.tyland.gov.tw"
                                            }
                                        },
                                        image: "assets/img/users/曾奕融_avatar.jpg"
                                    },
                                    {
                                        text: {
                                            name: "王甄臻",
                                            title: "課員",
                                            contact: {
                                                val: "1185.b@mail.tyland.gov.tw",
                                                href: "mailto:1185.b@mail.tyland.gov.tw"
                                            }
                                        },
                                        image: "assets/img/users/王甄臻_avatar.jpg"
                                    }
                                ]
                            }, {
                                text: {
                                    name: "張明智",
                                    title: "測量課長",
                                    contact: '#201'
                                },
                                stackChildren: true,
                                image: "assets/img/users/張明智_avatar.jpg",
                                collapsable: true,
                                collapsed: false,
                                pseudo: false
                            }, {
                                text: {
                                    name: "郭美蘭",
                                    title: "地價課長",
                                    contact: '#301'
                                },
                                stackChildren: true,
                                image: "assets/img/users/郭美蘭_avatar.jpg",
                                collapsable: true,
                                collapsed: false,
                                pseudo: false
                            }, {
                                text: {
                                    name: "劉家欣",
                                    title: "行政課長",
                                    contact: '#401'
                                },
                                stackChildren: true,
                                image: "assets/img/users/劉家欣_avatar.jpg",
                                collapsable: true,
                                collapsed: false,
                                pseudo: false
                            }, {
                                text: {
                                    name: "陳允成",
                                    title: "資訊課長",
                                    contact: '#501'
                                },
                                stackChildren: true,
                                image: "assets/img/users/陳允成_avatar.jpg",
                                collapsable: true,
                                collapsed: false,
                                pseudo: false,
                                children: [{
                                    text: {
                                        name: "劉邦渝",
                                        title: "管理師",
                                        desc: "Awesome!",
                                        "data-foo": " data Attribute for node",
                                        contact: {
                                            val: "#503",
                                            href: "https://www.google.com/",
                                            target: "_blank"
                                        }
                                    },
                                    image: "assets/img/users/劉邦渝_avatar.jpg",
                                    HTMLclass: 'bg-primary font-weight-bold text-white'
                                }, {
                                    text: {
                                        name: "賴俋儒",
                                        title: "管理師",
                                        contact: '#502'
                                    },
                                    image: "assets/img/users/賴俋儒_avatar.jpg"
                                }, {
                                    text: {
                                        name: "葉宇敦",
                                        title: "管理師",
                                        contact: '#506'
                                    },
                                    image: "assets/img/users/葉宇敦_avatar.jpg"
                                }]
                            }]
                        }]
                    }
                };
                this.inst = new Treant(this.config);
                console.log(this.inst);
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
                return {
                    text: {
                        name: `${raw_obj.id}:${raw_obj.name}`,
                        title: raw_obj.title,
                        //contact: raw_obj.tel
                    },
                    image: `assets/img/users/${raw_obj.name}_avatar.jpg`,
                    collapsable: this.depth_switch && children.length > 0,
                    collapsed: this.depth_switch && children.length > 0,
                    stackChildren: this.depth_switch,
                    HTMLclass: "mynode bg-muted",
                    pseudo: false,
                    children: [...children]
                };
            },
            add(raw_obj, parent) {
                let father = {
                    parent: parent,
                    text: {
                        name: `${raw_obj.id}:${raw_obj.name}`,
                        title: raw_obj.title,
                        //contact: raw_obj.tel
                    },
                    image: `assets/img/users/${raw_obj.name}_avatar.jpg`,
                    collapsable: true,
                    collapsed: true,
                    stackChildren: true,
                    HTMLclass: "mynode bg-muted"
                };
                this.treant_config.push(father);
                if (Array.isArray(raw_obj.staffs)) {
                    let len = raw_obj.staffs.length;
                    for ( let i = 0; i < len; i++ ) {
                        this.add(raw_obj.staffs[i], father);
                    }
                }
            }
        },
        computed: {
            depth_switch() { return this.depth < 2 ? false : true }
        },
        created() {
            this.id = this.uuid();
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