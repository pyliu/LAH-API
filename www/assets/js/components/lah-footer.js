if (Vue) {
    Vue.component("lah-footer", {
        components: { "lah-transition": VueTransition },
        template: `<lah-transition slide-up appear>
            <small v-if="show" id="lah-copyright" class="text-muted fixed-bottom my-2 mx-3 bg-white border rounded">
                <p id="copyright" class="text-center my-2">
                    <a href="https://github.com/pyliu/Land-Affairs-Helper" target="_blank" title="View project on Github!">
                        <i class="fab fa-github fa-lg text-dark"></i>
                    </a>
                    <strong>&copy; <a href="mailto:pangyu.liu@gmail.com">LIU, PANG-YU</a> ALL RIGHTS RESERVED.</strong>
                    <i class="text-success fab fa-vuejs fa-lg"></i>
                </p>
            </small>
        </lah-transition>`,
        data: function() {
            return {
                show: true,
                leave_time: 10000
            }
        },
        mounted() {
            let that = this;
            setTimeout(() => that.show = false, this.leave_time);
        }
    });
    $(document).ready(() => {
        $("body").append($.parseHTML(`<div id="lah-footer"><lah-footer></lah-footer></div>`));
        new Vue({ el: "#lah-footer" });
    });
}