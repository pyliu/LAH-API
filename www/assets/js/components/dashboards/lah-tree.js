if (Vue) {
    Vue.component('lah-tree', {
        template: `<b-card no-body border-variant="white" :id="id" style="min-height: 300px"></b-card>`,
        props: {
            root: {
                type: Object,
                default: {
                    text: {
                        name: { val: `範例`, href: `javascript:(0)` },
                        title: `測試`,
                        contact: `#分機`,
                        desc: ``
                    },
                    image: `assets/img/users/not_found_avatar.jpg`,
                    collapsable: true,
                    collapsed: false,
                    stackChildren: true,
                    pseudo: false
                }
            },
            nodeMargin: { type: Number, default: 15 },
            htmlClass: { type: String, default: 'mynode' },
            orientation: { type: String, default: 'NORTH' },
            height: { type: Number, default: 300 }
        },
        data: () => ({
            id: '',
            inst: null,
            rebuild_timer: null
        }),
        computed: {
            config() {
                return {
                    chart: {
                        container: `#${this.id}`,
                        connectors: {
                            type: 'step', // curve, bCurve, step, straight
                            style: {  
                                "stroke-width": 2,  
                                "stroke": "#000"
                            }
                        },
                        node: {
                            HTMLclass: this.htmlClass,
                            // collapsable: true,
                            // stackChildren: true
                        },
                        rootOrientation: this.orientation,
                        // animateOnInit: false,
                        // nodeAlign: 'TOP',
                        siblingSeparation: this.nodeMargin,
                        levelSeparation: this.nodeMargin,
                        subTeeSeparation: this.nodeMargin,
                        scrollbar: 'native'
                    }
                }
            }
        },
        watch: {
            root() { this.build() },
            nodeMargin() { this.build() },
            htmlClass() { this.build() },
            orientation() { this.build() },
            height(val) {
                $(this.$el).css('height', val + 'px');
                this.build();
            }
        },
        methods: {
            build() {
                if (typeof Treant == "function") {
                    clearTimeout(this.rebuild_timer);
                    this.rebuild_timer = this.timeout(() => {
                        this.isBusy = true;
                        if (this.inst) this.inst.destroy();
                        this.inst = new Treant(Object.assign(this.config, { nodeStructure: this.root }), () => {
                            this.isBusy = false;
                        }, $);
                        // this.$log(this.inst);
                    }, 250);
                } else {
                    this.$error(`Treant not defined. Did you include treant.js?`);
                }
            }
        },
        created() {
            this.busy = true;
            this.id = this.uuid();
            window.addEventListener("resize", e => {
                clearTimeout(this.rebuild_timer);
                this.rebuild_timer = this.timeout(this.build, 250);
            });
        },
        mounted() { this.rebuild_timer = this.timeout(this.build, 750) }
    });
} else {
    console.error("vue.js not ready ... lah-tree component can not be loaded.");
}