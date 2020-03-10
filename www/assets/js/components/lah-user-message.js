if (Vue) {
    Vue.component('lah-user-message', {
        template: `<div>
            <b-card-group v-if="ready" :columns="columns" :deck="!columns">
                <b-card
                    v-for="(message, index) in raws"
                    class="overflow-hidden bg-light"
                    :border-variant="border(index)"
                >
                    <b-card-title title-tag="h5">
                        <i v-if="index == 0" class="fas fa-eye"></i>
                        <i v-else-if="index == 1" class="far fa-eye"></i>
                        <span v-else> {{index+1}}.</span> {{message['xname']}}
                    </b-card-title>
                    <b-card-sub-title sub-title-tag="small"><div class="text-right">{{message['sendtime']['date'].substring(0, 19)}}</div></b-card-sub-title>
                    <b-card-text v-html="format(message['xcontent'])" class="small"></b-card-text>
                </b-card>
            </b-card-group>
            <lah-exclamation v-else>{{not_found}}</lah-exclamation>
        </div>`,
        props: ['id', 'name', 'ip', 'count'],
        data: () => { return {
            raws: undefined,
            pattern: /((http|https|ftp):\/\/[\w?=&.\/-;#~%-]+(?![\w\s?&.\/;#~%"=-]*>))/ig
        } },
        computed: {
            ready: function() { return !this.empty(this.raws) },
            not_found: function() { return `「${this.name || this.id || this.ip}」找不到信差訊息！` },
            columns: function() { return this.count > 3 }
        },
        methods: {
            format: function(content) {
                return content
                    .replace(this.pattern, "<a href='$1' target='_blank' title='點擊前往'>$1</a>")
                    .replace(/\r\n/g,"<br />");
            },
            border: function(index) { return index == 0 ? 'danger' : index == 1 ? 'primary' : '' }
        },
        async created() {
            try {
                this.count = this.count || 3;
                const raws = await this.getLocalCache("my-messeages");
                if (raws !== false) {
                    this.raws = raws;
                } else {
                    this.$http.post(CONFIG.JSON_API_EP, {
                        type: "user_message",
                        id: this.id,
                        name: this.name,
                        ip: this.ip,
                        count: this.count
                    }).then(res => {
                        if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
                            this.raws = res.data.raw
                            this.setLocalCache("my-messeages", this.raws, 60000);   // 1 min
                        } else {
                            addNotification({
                                title: "查詢信差訊息",
                                message: res.data.message,
                                type: "warning"
                            });
                        }
                    }).catch(err => {
                        console.error(err);
                        showAlert({
                            title: "查詢信差訊息",
                            message: err.message,
                            type: "danger"
                        });
                    });
                }
            } catch(err) {
                console.error(err);
            }
        },
        mounted() {}
    });
} else {
    console.error("vue.js not ready ... lah-user-message component can not be loaded.");
}
