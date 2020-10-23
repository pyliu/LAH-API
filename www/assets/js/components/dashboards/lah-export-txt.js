if (Vue) {
  Vue.component('lah-export-txt', {
    template: `<b-card>
        <template v-slot:header>
            <div class="d-flex w-100 justify-content-between mb-0">
                <h6 class="my-auto font-weight-bolder"><lah-fa-icon icon="road" size="lg"> 輸出地籍資料</lah-fa-icon></h6>
                <b-button-group size="sm" class="align-middle" v-if="!working" variant="outline-primary">
                    <lah-button icon="train" @click="tags = ['0200', '0202', '0205', '0210']" v-b-popover.top.hover.focus="'A21站'"></lah-button>
                    <lah-button icon="warehouse" @click="tags = ['0213', '0222']" v-b-popover.top.hover.focus="'中原營區'"></lah-button>
                    <lah-button icon="map-signs" @click="tags = ['0255', '0275', '0277', '0278', '0377']" v-b-popover.top.hover.focus="'草漯'"></lah-button>
                    <lah-button icon="undo" action="cycle-alt" variant="outline-secondary" @click="clean" title="重設" :disabled="tags.length == 0"></lah-button>
                    <lah-button icon="question" variant="outline-success" @click="popup" title="說明"></lah-button>
                </b-button-group>
            </div>
        </template>
        <b-progress v-if="working" :max="max" show-progress animated class="my-1">
            <b-progress-bar :value="iteration" :label="((iteration / max) * 100).toFixed(2)+'%'"></b-progress-bar>
        </b-progress>
        <b-input-group size="sm" prepend="段代碼" v-if="!working">
            <template v-slot:append>
                <lah-button icon="file-export" action="move-fade-ltr" variant="outline-primary" @click="go" title="執行" :disabled="disabled"></lah-button>
            </template>
            <b-form-tags
                input-id="tags-basic"
                v-model="tags"
                separator=" ,;"
                class="no-cache"
                remove-on-delete
                tag-variant="primary"
                tag-pills
                :tag-validator="validator"
            ></b-form-tags>
        </b-input-group>
        <div class="text-left">
            <lah-button v-for="link in links" icon="download" action="move-fade-ttb" class="s-75 truncate text-secondary" variant="link" @click="download(link)" :title="'下載 '+link.filename">{{link.filename}}</lah-button>
        </div>
    </b-card>`,
    computed: {
        disabled() { return this.tags.length == 0 },
        show_progress() { return this.iteration < 12 }
    },
    data: () => ({
        tags: [],
        links: [],
        max: 11,
        iteration: 11,
        working: false
    }),
    methods: {
        validator(tag) {
            return (/^\d{3,4}$/ig).test(tag);
        },
        clean() { this.tags = [] ; this.links = []; },
        download(link) {
            // second param usage => e.target.title to get the title
            this.open(`${CONFIG.API.FILE.DATA}?code=${link.code}`, {
                target: {
                    title: '下載產製資料'
                }
            });
            this.timeout(() => closeModal(() => this.notify({
                title: '下載產製資料',
                message: `<i class="fas fa-check ld ld-pulse"></i> ${link.filename} 下載完成`,
                type: "success"
            })), 2000);
        },
        go() {
            if (this.working) {
                this.$warn("Data generating in progress ... please wait a monment.");
                return;
            }
            this.$confirm(`請確認以輸入的段代碼產生地籍資料？`, async () => {
                this.links = [];
                this.working = true;
                this.iteration = 1;
                await this.query('AI00301'); this.iteration++;
                await this.query('AI00401'); this.iteration++;
                await this.query('AI00601_B'); this.iteration++;
                await this.query('AI00601_E'); this.iteration++;
                await this.query('AI00701'); this.iteration++;
                await this.query('AI00801'); this.iteration++;
                await this.query('AI00901'); this.iteration++;
                await this.query('AI01001'); this.iteration++;
                await this.query('AI01101'); this.iteration++;
                await this.query('AI02901_B'); this.iteration++;
                await this.query('AI02901_E');
                this.working = false;
            });
        },
        async query(code) {
            await this.$http.post(CONFIG.API.FILE.EXPORT, {
                type: 'file_data_export',
                code: code,
                section: this.tags
            }).then(res => {
                this.$assert(res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL, this.responseMessage(res.data.status));
                this.links.push({ code: code, filename: res.data.filename });
            }).catch(err => {
                this.error = err;
            }).finally(() => {
                this.isBusy = false;
            });
        },
        popup() {
            this.msgbox({
                title: '地籍資料匯出功能提示',
                message: `
                    <h5>地政局索取地籍資料備註</h5>
                    <span class="text-danger mt-2">※</span> 系統管理子系統/資料轉入轉出 (共14個txt檔案，地/建號範圍從 00000000 ~ 99999999) <br/>
                    　- <small class="mt-2 mb-2"> 除下面標示為黃色部分須至地政系統WEB版作業，其餘皆本看板產出下載。</small> <br/>
                    　AI001-10 <br/>
                    　　AI00301 - 土地標示部 <br/>
                    　　AI00401 - 土地所有權部 <br/>
                    　　AI00601 - 管理者資料【土地、建物各做一次】 <br/>
                    　　AI00701 - 建物標示部 <br/>
                    　　AI00801 - 基地坐落 <br/>
                    　　AI00901 - 建物分層及附屬 <br/>
                    　　AI01001 - 主建物與共同使用部分 <br/>
                    　AI011-20 <br/>
                    　　AI01101 - 建物所有權部 <br/>
                    　　<span class="bg-warning p-1">AI01901 - 土地各部別</span> <br/>
                    　AI021-40 <br/>
                    　　<span class="bg-warning p-1">AI02101 - 土地他項權利部</span> <br/>
                    　　<span class="bg-warning p-1">AI02201 - 建物他項權利部</span> <br/>
                    　　AI02901 - 各部別之其他登記事項【土地、建物各做一次】 <br/><br/>

                    <span class="text-danger">※</span> 測量子系統/測量資料管理/資料輸出入 【請至地政系統WEB版產出】<br/>
                    　地籍圖轉出(數值地籍) <br/>
                    　　* 輸出DXF圖檔【含控制點】及 NEC重測輸出檔 <br/>
                    　地籍圖轉出(圖解數化) <br/>
                    　　* 同上兩種類皆輸出，並將【分幅管理者先接合】下選項皆勾選 <br/>
                    　　* 如無法產出DXF資料請選擇【整段輸出】(如0210忠福段) <br/><br/>
                        
                    <span class="text-danger">※</span> 登記子系統/列印/清冊報表/土地建物地籍整理清冊【土地、建物各產一次存PDF，請至地政系統WEB版產出】 <br/>
                `,
                size: 'lg'
            });
        }
    },
    watch: { },
    created() { },
    mounted() { }
  });
} else {
    console.error("vue.js not ready ... lah-export-txt component can not be loaded.");
}