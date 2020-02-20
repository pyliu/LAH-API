if (Vue) {
    Vue.component("lah-header", {
        components: { "lah-transition": VueTransition },
        template: `<lah-transition slide-down appear>
            <nav v-if="show" class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
                <i class="my-auto fas fa-2x text-light" :class="icon"></i>&ensp;
                <a class="navbar-brand my-auto" :href="location.href">地政輔助系統 <span class="small">(β)</span></a>
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarsExampleDefault" aria-controls="navbarsExampleDefault" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
            
                <div class="collapse navbar-collapse" id="navbarsExampleDefault">
                    <ul class="navbar-nav mr-auto">
                        <li v-for="link in links" :class="['nav-item', 'my-auto', active(link.url)]" v-show="link.need_admin ? is_admin : true">
                            <a class="nav-link" :href="link.url">{{link.text}}</a>
                        </li>
                    </ul>
                </div>
            </nav>
        </lah-transition>`,
        data: function() {
            return {
                show: true,
                icon: "fa-question",
                is_admin: false,
                links: [{
                    text: "案件追蹤",
                    url: "index.php",
                    icon: "fa-list-alt",
                    need_admin: true
                }, {
                    text: "資料查詢",
                    url: "query.php",
                    icon: "fa-file-alt",
                    need_admin: true
                }, {
                    text: "監控修正",
                    url: "watch_dog.php",
                    icon: "fa-user-secret",
                    need_admin: true
                }, {
                    text: "逾期案件",
                    url: "overdue_reg_cases.html",
                    icon: "fa-th-list",
                    need_admin: false
                }, {
                    text: "記錄檔",
                    url: "watchdog.html",
                    icon: "fa-dog",
                    need_admin: true
                }, {
                    text: "測試頁",
                    url: "test.html",
                    icon: "fa-charging-station",
                    need_admin: true
                }]
            }
        },
        methods: {
            active: function(url) {
                return location.href.indexOf(url) > 0 ? 'active' : '';
            }
        },
        created() {
            // TODO: add query to get authority token
            let that = this;
            this.$http.post(CONFIG.JSON_API_EP, {
                type: 'authentication'
            }).then(res => {
                that.is_admin = res.data.is_admin || false;
            }).catch(err => {
                console.error(err.toJSON());
                showAlert({
                    title: '認證失敗',
                    message: err.message,
                    type: 'danger'
                });
            });
        },
        mounted() {
            let that = this;
            this.links.forEach(function(link, index) {
                if (location.href.indexOf(link.url) > 0) {
                    that.icon = link.icon;
                }
            });
        }
    });
    $(document).ready(() => {
        $("body").prepend($.parseHTML(`<div id="lah-header"><lah-header ref="header"></lah-header></div>`));
        window.vueLahHeader = new Vue({ el: "#lah-header" });
    });
}
