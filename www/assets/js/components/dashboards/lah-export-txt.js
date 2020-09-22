if (Vue) {
  Vue.component('lah-export-txt', {
    template: `<b-card>
        <template v-slot:header>
            <div class="d-flex w-100 justify-content-between mb-0">
                <h6 class="my-auto font-weight-bolder"><lah-fa-icon icon="map" prefix="far"> 輸出地籍資料</lah-fa-icon></h6>
                <lah-button icon="question" variant="outline-success" class="border-0" @click="popup"></lah-button>
            </div>
        </template>
        <template v-slot:footer>
            <div class="d-flex justify-content-between">
                <h6 class="my-auto">快速選項</h6>
                <b-button-group size="sm" class="align-middle">
                    <lah-button icon="train" variant="outline-secondary" @click="tags = ['0200', '0202', '0205', '0210']" class="mr-1">A21站</lah-button>
                    <lah-button icon="warehouse" variant="outline-secondary" @click="tags = ['0213', '0222']">中原營區</lah-button>
                </b-button-group>
            </div>
        </template>
        <b-input-group size="sm" prepend="段代碼">
            <template v-slot:append>
                <lah-button icon="cogs" variant="outline-primary" @click="go" title="執行" :disabled="disabled"></lah-button>
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
    </b-card>`,
    computed: {
        disabled() { return this.tags.length == 0 }
    },
    data: () => ({
        tags: []
    }),
    methods: {
        validator(tag) {
            return (/^\d{3,4}$/ig).test(tag);
        },
        go() {
            this.$confirm(`請確認以輸入的段代碼產生地籍資料？`, () => {
                
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