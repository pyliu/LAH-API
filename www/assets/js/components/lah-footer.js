if (Vue) {
    Vue.component("lah-footer", {
        components: { "lah-transition": VueTransition },
        template: `<lah-transition slide-up appear>
            <p v-if="show" :class="classes">
                <a href="https://github.com/pyliu/Land-Affairs-Helper" target="_blank" title="View project on Github!">
                    <i class="fab fa-github fa-lg text-dark"></i>
                </a>
                <strong><i class="far fa-copyright"></i> <a href="mailto:pangyu.liu@gmail.com">LIU, PANG-YU</a> ALL RIGHTS RESERVED.</strong>
                <a href="https://vuejs.org/" target="_blank" title="Learn Vue JS!">
                    <i class="text-success fab fa-vuejs fa-lg"></i>
                </a>
            </p>
        </lah-transition>`,
        data: function() {
            return {
                show: true,
                leave_time: 10000,
                classes: ['text-muted', 'fixed-bottom', 'my-2', 'mx-3', 'bg-white', 'border', 'rounded', 'text-center', 'p-2', 'small']
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