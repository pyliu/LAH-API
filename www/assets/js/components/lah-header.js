if (Vue) {
    Vue.component("lah-header", {
        components: { "lah-transition": VueTransition },
        template: `<lah-transition slide-down appear>
            <nav v-if="show" class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
                <i class="my-auto fas fa-user-secret fa-2x text-light"></i>　
                <a class="navbar-brand my-auto" href="watch_dog.php">地政輔助系統 <span class="small">(α)</span></a>
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarsExampleDefault" aria-controls="navbarsExampleDefault" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
            
                <div class="collapse navbar-collapse" id="navbarsExampleDefault">
                <ul class="navbar-nav mr-auto">
                    <li v-for="link in links" class="nav-item my-auto">
                        <a class="nav-link" :href="link.url">{{link.text}}</a>
                    </li>
                </ul>
                </div>
            </nav>
        </lah-transition>`,
        data: function() {
            return {
                show: true,
                is_admin: false,
                links: [{
                    text: "案件追蹤",
                    url: "/index.php",
                    admin: true
                }, {
                    text: "資料查詢",
                    url: "/query.php",
                    admin: true
                }, {
                    text: "系統監控",
                    url: "/watch_dog.php",
                    admin: true
                }, {
                    text: "逾期案件",
                    url: "/overdue_reg_cases.html",
                    admin: false
                }, {
                    text: "記錄檔",
                    url: "/watchdog.html",
                    admin: false
                }]
            }
        },
        mounted() {
            
        }
    });
    $(document).ready(() => {
        $("body").append($.parseHTML(`<div id="lah-header"><lah-header></lah-header></div>`));
        new Vue({ el: "#lah-header" });
    });
}
