if (Vue) {
  Vue.component('lah-heir-share-ratio', {
    template: `<b-card-group deck>
        <b-card>
        <h5>
          <lah-fa-icon icon="chevron-circle-right" variant="danger">被繼承人資訊</lah-fa-icon>
        </h5>
          <div class="d-flex">
            <b-input-group :prepend="name" size="sm">
              <b-form-input
                  ref="pid"
                  v-model="id"
                  placeholder="A123456789"
                  :state="validID"
                  title="身分證號"
              ></b-form-input>
            </b-input-group>
            <b-input-group class="my-auto ml-1" size="sm">
              <b-form-checkbox v-model="dead" inline>死亡</b-form-checkbox>
            </b-input-group>
            <b-input-group size="sm">
              <b-input-group-prepend is-text><span class="text-danger font-weight-bold">＊</span>持分</b-input-group-prepend>
              <b-form-input
                inline
                type="number"
                min="1"
                step="1"
                pattern="\d+"
                v-model="heir_denominator"
              ></b-form-input>
              <span class="pt-1 mx-1 my-auto"> 分之 1</span>
            </b-input-group>
          </div>
          <lah-transition>
            <div v-show="seen_heir_inputs">
              <h5 class="my-3">
                <lah-fa-icon icon="chevron-circle-right" variant="info"> {{now_step.title}}</lah-fa-icon>
                <b-button
                  :disabled="now_step == wizard.s0"
                  :variant="now_step == wizard.s0 ? 'outline-primary' : 'success'"
                  title="重新開始"
                  class="float-right shadow-sm"
                  size="sm"
                  @click="reset"
                  @mouseenter="addLDAnimation('#spin-icon', 'ld-cycle-alt')"
                  @mouseleave="clearLDAnimation('#spin-icon')"
                >
                  <lah-fa-icon :id="'spin-icon'" icon="undo"></lah-fa-icon>
                </b-button>
              </h5>

              <section class="s-95">
                <lah-transition>

                  <!-- step 0 選擇繼承事實發生時間點 -->
                  <fieldset key=s0 class="border p-2" v-if="wizard.s0.seen">
                    <legend class="w-auto">{{wizard.s0.legend}}</legend>
                    <div class="row text-center">
                      <label
                        class="col"
                        v-b-popover.hover.bottom="{ customClass: 'my-popover', content: '民國34年10月24日以前' }"
                      >
                        <input type="radio" v-model.number="wizard.s0.value" value="-1" @change="s0ValueSelected" /> 光復前
                      </label>
                      <label
                        class="col"
                        v-b-popover.hover.bottom="{ customClass: 'my-popover', content: '民國34年10月25日以後' }"
                      >
                        <input type="radio" v-model.number="wizard.s0.value" value="0" @change="s0ValueSelected" /> 光復後
                      </label>
                    </div>
                  </fieldset>
              
                  <!-- step 1 光復前 -->
                  <fieldset key="s1" class="border p-2" v-if="wizard.s1.seen">
                    <legend class="w-auto">{{wizard.s1.legend}}</legend>
                    <div class="row text-center">
                      <label class="col-6">
                        <input type="radio" v-model="wizard.s1.value" value="public" @change="s1ValueSelected" /> 家產
                      </label>
                      <label class="col-6">
                        <input type="radio" v-model="wizard.s1.value" value="private" @change="s1ValueSelected" /> 私產
                      </label>
                    </div>

                    <lah-transition fade>

                    <div key="seen_s1_public" class="border-top border-dark pt-2" v-if="seen_s1_public">
                      <ol class="d-block">
                        <li>
                          法定推定財產繼承人係
                          <strong class="text-primary">男子</strong>直系卑親屬，以親等近者為優先。親等相同之
                          <strong class="text-primary">男子</strong>有數人時，共同均分繼承。
                        </li>
                        <li>無法定之推定戶主繼承人時，指定及選定之財產繼承人繼承。</li>
                      </ol>
                      
                      <div class="ml-4">
                        人數：
                        <b-form-spinbutton v-model="wizard.s1.public.count" min="0" size="sm" inline></b-form-spinbutton>
                        <h5 class="d-inline">
                          <b-badge v-show="seen_s1_pub_msg" variant="warning">
                            每人之應繼份為
                            <b-badge variant="light">{{Math.abs(wizard.s1.public.count * heir_denominator)}} 分之 1</b-badge>
                          </b-badge>
                        </h5>
                      </div>
                    </div>

                    <div key="seen_s1_private" class="border-top border-dark pt-2" v-if="seen_s1_private">
                      <h6 class="d-inline">* 僅有法定繼承人，順序如下：</h6>
                      <b-link href="#" @click="resetS1PrivateCounter">重設</b-link>
                      
                      <ol class="d-block">
                        <li>
                          人數：<b-form-spinbutton v-model="wizard.s1.private.child" min="0" size="sm" inline :disabled="!seen_s1_private_1"></b-form-spinbutton>
                          <label
                            v-show="!seen_s1_private_1_msg"
                            v-b-popover.hover.top="{ customClass: 'my-popover', content:'以親等近者為優先。親等相同之男子有數人時，共同均分之'}"
                          >直系卑親屬</label>
                          <h5 class="d-inline">
                            <b-badge v-show="seen_s1_private_1_msg" variant="warning">
                              直系卑親屬每人之應繼份為
                              <b-badge
                                variant="light"
                              >{{Math.abs(wizard.s1.private.child * heir_denominator)}} 分之 1</b-badge>
                            </b-badge>
                          </h5>
                        </li>
                        <li>
                          人數：<b-form-spinbutton v-model="wizard.s1.private.spouse" size="sm" max="1" min="0" inline :disabled="!seen_s1_private_2"></b-form-spinbutton>
                          <label v-show="!seen_s1_private_2_msg">配偶</label>
                          <h5 class="d-inline">
                            <b-badge v-show="seen_s1_private_2_msg" variant="warning">
                              配偶應繼份為
                              <b-badge
                                variant="light"
                              >{{Math.abs(wizard.s1.private.spouse * heir_denominator)}} 分之 1</b-badge>
                            </b-badge>
                          </h5>
                        </li>
                        <li>
                          人數：<b-form-spinbutton v-model="wizard.s1.private.parent" min="0" size="sm" inline :disabled="!seen_s1_private_3"></b-form-spinbutton>
                          <label
                            v-show="!seen_s1_private_3_msg"
                            v-b-popover.hover.bottom="{ customClass: 'my-popover', content: '親等不同以親等近者為先，同一親等有2人以上，共同均分之'}"
                          >直系尊親屬</label>
                          <h5 class="d-inline">
                            <b-badge v-show="seen_s1_private_3_msg" variant="warning">
                              直系尊親屬每人之應繼份為
                              <b-badge
                                variant="light"
                              >{{Math.abs(wizard.s1.private.parent * heir_denominator)}} 分之 1</b-badge>
                            </b-badge>
                          </h5>
                        </li>
                        <li>
                          人數：<b-form-spinbutton v-model="wizard.s1.private.household" size="sm" max="1" min="0" inline :disabled="!seen_s1_private_4"></b-form-spinbutton>
                          <label v-show="!seen_s1_private_4_msg">戶主</label>
                          <h5 class="d-inline">
                            <b-badge v-show="seen_s1_private_4_msg" variant="warning">
                              戶主應繼份為
                              <b-badge
                                variant="light"
                              >{{Math.abs(wizard.s1.private.household * heir_denominator)}} 分之 1</b-badge>
                            </b-badge>
                          </h5>
                        </li>
                      </ol>
                      
                    </div>

                    </lah-transition>

                  </fieldset>
              
                  <!-- step 2 光復後 -->
                  <fieldset key="s2" class="border p-2" v-if="wizard.s2.seen">
                    <legend class="w-auto">{{wizard.s2.legend}}</legend>
                    <div class="row text-center">
                      <label class="col-6">
                        <input type="radio" v-model="wizard.s2.value" value="7464" @change="s2ValueSelected" /> 74年6月4日以前
                      </label>
                      <label class="col-6">
                        <input type="radio" v-model="wizard.s2.value" value="7465" @change="s2ValueSelected" /> 74年6月5日以後
                      </label>
                    </div>

                    <lah-transition fade>

                    <div class="border-top border-dark pt-2" v-if="seen_s2_counters">
                      <ol class="d-block">
                        <li>
                          <b-link href="#" @click="resetS2Counter" class="float-right">重設</b-link>
                          <b-form-checkbox
                            v-model="wizard.s2.spouse"
                            value="1"
                            unchecked-value="0"
                            size="sm"
                            inline
                            switch
                          >
                            <span>有配偶？</span>
                          </b-form-checkbox>
                          <span v-show="seen_s2_spouse_msg" class="h5">
                            <b-badge variant="warning">
                              應繼份為
                              <b-badge variant="light">{{val_s2_spouse_ratio}}</b-badge>
                            </b-badge>
                          </span>
                        </li>
                        <li>
                          <div>
                            <label>
                              直系卑親屬
                              <lah-transition><span v-show="seen_s2_raising_children">(含養子女)</span></lah-transition>
                            </label>人數：
                            <b-form-spinbutton v-model="wizard.s2.children" min="0" size="sm" inline :disabled="!seen_s2_children"></b-form-spinbutton>
                            <span v-show="seen_s2_children_msg" class="h5">
                              <b-badge variant="warning">
                                每人應繼份為
                                <b-badge variant="light">{{val_s2_children_ratio}}</b-badge>
                              </b-badge>
                            </span>
                          </div>
                          <lah-transition fade>
                            <div v-show="!seen_s2_raising_children">
                              <label>養子女</label>人數：
                              <b-form-spinbutton v-model="wizard.s2.raising_children" min="0" size="sm" inline :disabled="!seen_s2_children"></b-form-spinbutton>
                              <span v-show="seen_s2_raising_children_msg" class="h5">
                                <b-badge variant="warning">
                                  每人應繼份為
                                  <b-badge variant="light">{{val_s2_raising_children_ratio}}</b-badge>
                                </b-badge>
                              </span>
                            </div>
                          </lah-transition>
                        </li>
                        <li>
                          <label>父母</label>人數：
                          <b-form-spinbutton v-model="wizard.s2.parents" min="0" size="sm" inline :disabled="!seen_s2_parents"></b-form-spinbutton>
                          <span v-show="seen_s2_parents_msg" class="h5">
                            <b-badge variant="warning">
                              每人應繼份為
                              <b-badge variant="light">{{val_s2_parents_ratio}}</b-badge>
                            </b-badge>
                          </span>
                        </li>
                        <li>
                          <label>兄弟姊妹</label>人數：
                          <b-form-spinbutton v-model="wizard.s2.brothers" min="0" size="sm" inline :disabled="!seen_s2_brothers"></b-form-spinbutton>
                          <span v-show="seen_s2_brothers_msg" class="h5">
                            <b-badge variant="warning">
                              每人應繼份為
                              <b-badge variant="light">{{val_s2_brothers_ratio}}</b-badge>
                            </b-badge>
                          </span>
                        </li>
                        <li>
                          <label>祖父母</label>人數：
                          <b-form-spinbutton v-model="wizard.s2.grandparents" min="0" size="sm" inline :disabled="!seen_s2_grandparents"></b-form-spinbutton>
                          <span v-show="seen_s2_grandparents_msg" class="h5">
                            <b-badge variant="warning">
                              每人應繼份為
                              <b-badge variant="light">{{val_s2_grandparents_ratio}}</b-badge>
                            </b-badge>
                          </span>
                        </li>
                      </ol>
                    </div>

                    </lah-transition>

                  </fieldset>

                </lah-transition>
              </section>
            </div>
          </lah-transition>
        </b-card>
        <b-card v-show="pieChart">
          <b-card-title><lah-fa-icon :prefix="seen_chart ? 'fas' : 'far'" :icon="seen_chart ? 'chart-pie' : 'hand-point-left'">{{seen_chart ? '分配圖' : '請操作左方區塊'}}</lah-fa-icon></b-card-title>
          <lah-transition>
            <lah-chart v-show="seen_chart" ref="pie"></lah-chart>
          </lah-transition>
        </b-card>
    </b-card-group>`,
    props: {
      pieChart: {
        type: Boolean,
        default: false
      }
    },
    data: () => ({
      id: '',
      name: '權利人',
      dead: false,
      wizard: {
        s0: {
          title: "步驟1，選擇發生區間",
          legend: "被繼承人死亡時間",
          seen: true,
          value: ""
        },
        s1: {
          // 光復前
          title: "步驟2，光復前繼承財產分類",
          legend: "被繼承財產種類",
          seen: false,
          value: "",
          public: {
            count: 0
          },
          private: {
            child: 0,
            spouse: 0,
            parent: 0,
            household: 0
          }
        },
        s2: {
          // 光復後
          title: "步驟2，光復後時段區間",
          legend: "時段區間",
          seen: false,
          value: "",
          children: 0,
          raising_children: 0,
          spouse: 0,
          parents: 0,
          brothers: 0,
          grandparents: 0
        }
      },
      heir_denominator: 1,
      now_step: null,
      color_codes: [
        "2, 117, 216",
        "92, 184, 92",
        "91, 192, 222",
        "240, 173, 78",
        "217, 83, 79",
        "41, 43, 44",
        "73, 5, 245",
        "201, 65, 149",
        "148, 88, 200",
        "251, 218, 137",
        "193, 247, 215",
        "252, 116, 4",
        "242, 193, 135",
        "60, 108, 4",
        "150, 140, 107",
        "206, 170, 155"
      ],
      color_codes_next: 0,
      vueChartData: {
        labels: [],
        datasets: [{
          label: '繼承分配表',
          data: [],
          borderWidth: 1
        }]
      },
      parent_width: 0
    }),
    methods: {
      reset: function (e) {
        this.wizard.s0.seen = true;
        this.wizard.s0.value = "";
        this.heir_denominator = 1;
        this.now_step = this.wizard.s0;
        this.resetS1(e);
        this.resetS2(e);
        clearLDAnimation('#spin-icon');
      },
      resetS1: function (e) {
        this.wizard.s1.seen = false;
        this.wizard.s1.value = "";
        this.wizard.s1.public.count = 0;
        this.wizard.s1.private.child = 0;
        this.wizard.s1.private.spouse = 0;
        this.wizard.s1.private.parent = 0;
        this.wizard.s1.private.household = 0;
      },
      resetS2: function (e) {
        this.wizard.s2.seen = false;
        this.wizard.s2.value = "";
        this.wizard.s2.children = 0;
        this.wizard.s2.raising_children = 0;
        this.wizard.s2.spouse = 0;
        this.wizard.s2.parents = 0;
        this.wizard.s2.brothers = 0;
        this.wizard.s2.grandparents = 0;
      },
      resetS1PublicCounter: function (e) {
        this.wizard.s1.public.count = 0;
      },
      resetS1PrivateCounter: function (e) {
        this.wizard.s1.private.child = 0;
        this.wizard.s1.private.spouse = 0;
        this.wizard.s1.private.parent = 0;
        this.wizard.s1.private.household = 0;
      },
      resetS2Counter: function (e) {
        this.wizard.s2.children = 0;
        this.wizard.s2.raising_children = 0;
        this.wizard.s2.spouse = 0;
        this.wizard.s2.parents = 0;
        this.wizard.s2.brothers = 0;
        this.wizard.s2.grandparents = 0;
      },
      recalS2Servings: function () {
        this.resetChartData();
        if (this.wizard.s2.children > 0 || this.wizard.s2.raising_children > 0) {
          this.addChartData("配偶", 2, this.wizard.s2.spouse);
          this.addChartData("直系卑親屬", 2, this.wizard.s2.children);
          this.addChartData("養子女", 1, this.wizard.s2.raising_children);
        } else if (this.wizard.s2.parents > 0) {
          this.addChartData("配偶", this.wizard.s2.parents, this.wizard.s2.spouse);
          this.addChartData("父母", 1, this.wizard.s2.parents);
        } else if (this.wizard.s2.brothers > 0) {
          this.addChartData("配偶", this.wizard.s2.brothers, this.wizard.s2.spouse);
          this.addChartData("兄弟姊妹", 1, this.wizard.s2.brothers);
        } else if (this.wizard.s2.grandparents > 0) {
          this.addChartData("配偶", this.wizard.s2.grandparents * 4, this.wizard.s2.spouse);
          this.addChartData("祖父母", 2, this.wizard.s2.grandparents);
        } else if (this.wizard.s2.spouse > 0) {
          this.addChartData("配偶", 1);
        }
      },
      s0ValueSelected: function (e) {
        switch (this.wizard.s0.value) {
          case -1:
            // 光復前
            this.now_step = this.wizard.s1;
            break;
          case 0:
            // 光復後
            this.now_step = this.wizard.s2;
            break;
          default:
            console.error(`Not supported: ${this.wizard.s0.value}.`);
            return;
        }
        // hide all steps first
        for (let step in this.wizard) {
          this.wizard[step].seen = false;
        }
        this.now_step.seen = true;
      },
      s1ValueSelected: function (e) {
        this.resetS1PublicCounter(e);
        this.resetS1PrivateCounter(e);
      },
      s2ValueSelected: function (e) {
        this.resetS2Counter(e);
      },
      resetChartData: function () {
        this.vueChartData.labels = [];
        this.vueChartData.datasets[0].data = [];
        this.vueChartData.datasets[0].backgroundColor = [];
        this.vueChartData.datasets[0].borderColor = [];
        if (this.pieChart) this.$refs.pie.buildChart({
          legend_pos: 'bottom'
        });
      },
      addChartData: function (name, servings, count = 1) {
        for (let i = 0; i < count; i++) {
          this.vueChartData.labels.push(name);
          this.vueChartData.datasets[0].data.push(parseInt(servings));
          let color = this.color_codes[this.color_codes_next++ % this.color_codes.length];
          this.vueChartData.datasets[0].backgroundColor.push(`rgba(${color}, 0.8)`);
          this.vueChartData.datasets[0].borderColor.push(`rgba(${color}, 1)`);
        }
        if (this.pieChart) this.$refs.pie.buildChart({
          legend_pos: 'bottom'
        });
      },
      parentWidth: function () {
        return this.$parent.$el.offsetWidth;
      },
      checkParentWidth: function () {
        let pw = this.$parent.$el.offsetWidth;
        if (pw == 0) {
          this.timeout(() => this.checkParentWidth(), 100);
        } else {
          this.parent_width = pw;
        }
      },
      checkID: function(id) {
        tab = "ABCDEFGHJKLMNPQRSTUVXYWZIO"                     
        A1 = new Array (1,1,1,1,1,1,1,1,1,1,2,2,2,2,2,2,2,2,2,2,3,3,3,3,3,3 );
        A2 = new Array (0,1,2,3,4,5,6,7,8,9,0,1,2,3,4,5,6,7,8,9,0,1,2,3,4,5 );
        Mx = new Array (9,8,7,6,5,4,3,2,1,1);

        if ( id.length != 10 ) return false;
        i = tab.indexOf( id.charAt(0) );
        if ( i == -1 ) return false;
        sum = A1[i] + A2[i]*9;

        for ( i=1; i<10; i++ ) {
            v = parseInt( id.charAt(i) );
            if ( isNaN(v) ) return false;
            sum = sum + v * Mx[i];
        }
        if ( sum % 10 != 0 ) return false;
        return true;
      }
    },
    watch: {
      id(val) {
        if (this.validID) {
          this.isBusy = true;
          this.$http.post(CONFIG.API.JSON.QUERY, {
            type: 'rlnid',
            id: this.id
          }).then(res => {
            if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL) {
              // res.data.raw always returns array, so I pick the first record.
              this.name = res.data.raw[0]['LNAM'];
            } else {
              this.$warn(res.data.message);
              this.name = '權利人';
            }
          }).catch(err => {
            this.error = err;
          }).finally(() => {
            this.isBusy = false;
          });
        }
      },
      "wizard.s1.public.count": function () {
        this.resetChartData();
        this.addChartData("繼承人", 1, this.wizard.s1.public.count);
      },
      "wizard.s1.private.child": function () {
        this.resetChartData();
        this.addChartData("直系卑親屬(男)", 1, this.wizard.s1.private.child);
      },
      "wizard.s1.private.spouse": function () {
        if (this.wizard.s1.private.spouse > 1) {
          this.wizard.s1.private.spouse = 1;
        }
        this.resetChartData();
        this.addChartData("配偶", 1, this.wizard.s1.private.spouse);
      },
      "wizard.s1.private.parent": function () {
        this.resetChartData();
        this.addChartData("直系尊親屬", 1, this.wizard.s1.private.parent);
      },
      "wizard.s1.private.household": function () {
        if (this.wizard.s1.private.household > 1) {
          this.wizard.s1.private.household = 1;
        }
        this.resetChartData();
        this.addChartData("戶主", 1, this.wizard.s1.private.household);
      },
      "wizard.s2.spouse": function () {
        this.recalS2Servings();
      },
      "wizard.s2.children": function () {
        this.recalS2Servings();
      },
      "wizard.s2.raising_children": function () {
        this.recalS2Servings();
      },
      "wizard.s2.parents": function () {
        this.recalS2Servings();
      },
      "wizard.s2.brothers": function () {
        this.recalS2Servings();
      },
      "wizard.s2.grandparents": function () {
        this.recalS2Servings();
      }
    },
    computed: {
      val_s2_children_spouse_total_deno: function () {
        return (
          parseInt(this.wizard.s2.children * 2) +
          parseInt(this.wizard.s2.raising_children) +
          parseInt(this.wizard.s2.spouse * 2)
        ) * this.heir_denominator;
      },
      val_s2_spouse_ratio: function () {
        if (this.wizard.s2.children > 0 || this.wizard.s2.raising_children > 0) {
          let deno = this.val_s2_children_spouse_total_deno;
          return this.wizard.s2.raising_children > 0 ? `${deno} 分之 2` : `${deno / 2} 分之 1`;
        } else if (this.wizard.s2.parents > 0 || this.wizard.s2.brothers > 0) {
          return `${this.heir_denominator * 2} 分之 1`;
        } else if (this.wizard.s2.grandparents > 0) {
          return `${this.heir_denominator * 3} 分之 2`;
        }
        return `${this.heir_denominator} 分之 1`;
      },
      val_s2_children_ratio: function () {
        let deno = this.val_s2_children_spouse_total_deno;
        return this.wizard.s2.raising_children > 0 ? `${deno} 分之 2` : `${deno / 2} 分之 1`;
      },
      val_s2_raising_children_ratio: function () {
        return `${this.val_s2_children_spouse_total_deno} 分之 1`;
      },
      val_s2_parents_ratio: function () {
        if (this.wizard.s2.spouse == 0) {
          return `${this.wizard.s2.parents * this.heir_denominator} 分之 1`;
        } else {
          return `${this.wizard.s2.parents * 2 * this.heir_denominator} 分之 1`;
        }
      },
      val_s2_brothers_ratio: function () {
        if (this.wizard.s2.spouse == 0) {
          return `${this.wizard.s2.brothers * this.heir_denominator} 分之 1`;
        } else {
          return `${this.wizard.s2.brothers * 2 * this.heir_denominator} 分之 1`;
        }
      },
      val_s2_grandparents_ratio: function () {
        if (this.wizard.s2.spouse == 0) {
          return `${this.wizard.s2.grandparents * this.heir_denominator} 分之 1`;
        } else {
          return `${this.wizard.s2.grandparents * 3 * this.heir_denominator} 分之 1`;
        }
      },
      seen_s1_public: function () {
        return this.wizard.s1.value == "public";
      },
      seen_s1_pub_msg: function () {
        return this.wizard.s1.public.count > 0;
      },
      seen_s1_private: function () {
        return this.wizard.s1.value == "private";
      },
      seen_s1_private_1: function () {
        return (
          this.wizard.s1.private.spouse == 0 &&
          this.wizard.s1.private.parent == 0 &&
          this.wizard.s1.private.household == 0
        );
      },
      seen_s1_private_1_msg: function () {
        return this.wizard.s1.private.child > 0;
      },
      seen_s1_private_2: function () {
        return (
          this.wizard.s1.private.child == 0 &&
          this.wizard.s1.private.parent == 0 &&
          this.wizard.s1.private.household == 0
        );
      },
      seen_s1_private_2_msg: function () {
        return this.wizard.s1.private.spouse > 0;
      },
      seen_s1_private_3: function () {
        return (
          this.wizard.s1.private.spouse == 0 &&
          this.wizard.s1.private.child == 0 &&
          this.wizard.s1.private.household == 0
        );
      },
      seen_s1_private_3_msg: function () {
        return this.wizard.s1.private.parent > 0;
      },
      seen_s1_private_4: function () {
        return (
          this.wizard.s1.private.spouse == 0 &&
          this.wizard.s1.private.parent == 0 &&
          this.wizard.s1.private.child == 0
        );
      },
      seen_s1_private_4_msg: function () {
        return this.wizard.s1.private.household > 0;
      },
      seen_s2_counters: function () {
        return this.wizard.s2.value;
      },
      seen_s2_spouse_msg: function () {
        return this.wizard.s2.spouse > 0;
      },
      seen_s2_children: function () {
        return (
          this.wizard.s2.parents == 0 &&
          this.wizard.s2.brothers == 0 &&
          this.wizard.s2.grandparents == 0
        );
      },
      seen_s2_children_msg: function () {
        return this.wizard.s2.children > 0;
      },
      seen_s2_raising_children: function () {
        return this.wizard.s2.value == "7465";
      },
      seen_s2_raising_children_msg: function () {
        return this.wizard.s2.raising_children > 0;
      },
      seen_s2_parents: function () {
        return (
          this.wizard.s2.children == 0 &&
          this.wizard.s2.raising_children == 0 &&
          this.wizard.s2.brothers == 0 &&
          this.wizard.s2.grandparents == 0
        );
      },
      seen_s2_parents_msg: function () {
        return this.wizard.s2.parents > 0;
      },
      seen_s2_brothers: function () {
        return (
          this.wizard.s2.parents == 0 &&
          this.wizard.s2.raising_children == 0 &&
          this.wizard.s2.children == 0 &&
          this.wizard.s2.grandparents == 0
        );
      },
      seen_s2_brothers_msg: function () {
        return this.wizard.s2.brothers > 0;
      },
      seen_s2_grandparents: function () {
        return (
          this.wizard.s2.parents == 0 &&
          this.wizard.s2.raising_children == 0 &&
          this.wizard.s2.brothers == 0 &&
          this.wizard.s2.children == 0
        );
      },
      seen_s2_grandparents_msg: function () {
        return this.wizard.s2.grandparents > 0;
      },
      seen_chart: function () {
        return this.vueChartData.labels.length > 0;
      },
      seen_hand: function () {
        return !this.seen_chart
      },
      seen_heir_inputs: function () {
        return this.dead
      },
      validID: function() {
        if (this.id == '') return null;
        return this.checkID(this.id);
      }
    },
    created() {
      this.now_step = this.wizard.s0;
    },
    mounted() {
      if (this.pieChart) {
        this.$refs.pie.chartData = this.vueChartData;
        this.$refs.pie.type = 'pie';
      }
      this.checkParentWidth();
    }
  });
}