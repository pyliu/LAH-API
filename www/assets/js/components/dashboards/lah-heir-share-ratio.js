if (Vue) {
  // Utility: Greatest Common Divisor
  function lahGcd(a, b) {
    a = Math.abs(parseInt(a) || 0);
    b = Math.abs(parseInt(b) || 0);
    while (b) {
      let t = b;
      b = a % b;
      a = t;
    }
    return a || 1;
  }

  // Utility: Fraction simplification
  function lahSimplifyFraction(num, den) {
    num = parseInt(num) || 0;
    den = parseInt(den) || 1;
    if (den === 0) return { num: 0, den: 1, text: '0', percent: '0%' };
    if (num === 0) return { num: 0, den: 1, text: '0', percent: '0%' };
    let g = lahGcd(num, den);
    let sNum = num / g;
    let sDen = den / g;
    let pct = ((sNum / sDen) * 100).toFixed(2).replace(/\.00$/, '') + '%';
    let text = sDen === 1 ? `${sNum}` : `${sDen} 分之 ${sNum}`;
    return { num: sNum, den: sDen, text: text, percent: pct, decimal: sNum / sDen };
  }

  Vue.component('lah-heir-share-ratio', {
    template: `<div class="lah-heir-share-container">
      <!-- 列印專用抬頭 (一般瀏覽隱藏，列印時顯示) -->
      <div class="print-only mb-3">
        <div class="text-center">
          <h3 class="font-weight-bold mb-1">桃園市桃園地政事務所</h3>
          <h4 class="font-weight-bold mb-2">繼承應繼分與特留分試算結果證明表</h4>
          <div class="d-flex justify-content-between small text-muted border-bottom pb-1 mb-2">
            <span>被繼承人：{{name}} ({{id || '未填寫身分證號'}})</span>
            <span>原持有持分：{{heirShareText}}</span>
            <span>列印日期：{{printDateString}}</span>
          </div>
        </div>
      </div>

      <!-- 頁面主體卡片 -->
      <b-card no-body class="shadow-sm border-0 mb-4">
        <!-- 卡片頂部：被繼承人資訊與快捷列 -->
        <b-card-header class="bg-primary text-white py-2 no-print">
          <div class="d-flex flex-wrap align-items-center justify-content-between">
            <h5 class="m-0 font-weight-bold d-flex align-items-center">
              <lah-fa-icon icon="calculator" class="mr-2"></lah-fa-icon>
              繼承應繼分試算系統
            </h5>
            <div class="d-flex align-items-center">
              <b-button
                size="sm"
                variant="outline-light"
                class="mr-2"
                @click="reset"
                title="清除所有輸入並重新開始"
              >
                <lah-fa-icon icon="undo" class="mr-1"></lah-fa-icon>重新開始
              </b-button>
              <b-button
                size="sm"
                variant="warning"
                class="font-weight-bold"
                @click="printReport"
                title="列印或另存為 A4 試算表 PDF"
              >
                <lah-fa-icon icon="print" class="mr-1"></lah-fa-icon>列印試算表
              </b-button>
            </div>
          </div>
        </b-card-header>

        <b-card-body class="p-3">
          <!-- 區塊 1：被繼承人基本資料與原持分 (no-print) -->
          <div class="bg-light p-3 rounded border mb-3 no-print">
            <div class="row align-items-center">
              <!-- 身分證號 -->
              <div class="col-12 col-md-3 mb-2 mb-md-0">
                <b-input-group size="sm" :prepend="name">
                  <b-form-input
                    ref="pid"
                    v-model="id"
                    placeholder="請輸入身分證號"
                    :state="validID"
                    title="身分證號"
                    trim
                  ></b-form-input>
                </b-input-group>
              </div>

              <!-- 死亡狀態 -->
              <div class="col-12 col-md-2 mb-2 mb-md-0">
                <b-form-checkbox v-model="dead" switch size="sm" class="font-weight-bold">
                  <span :class="dead ? 'text-danger' : 'text-secondary'">
                    {{ dead ? '已死亡 (發生繼承)' : '尚未死亡' }}
                  </span>
                </b-form-checkbox>
              </div>

              <!-- 被繼承人原持分：分母 分之 分子 -->
              <div class="col-12 col-md-4 mb-2 mb-md-0">
                <b-input-group size="sm" class="align-items-stretch">
                  <b-input-group-prepend is-text>
                    <span class="text-danger font-weight-bold mr-1">*</span>原持分
                  </b-input-group-prepend>
                  <b-form-input
                    type="number"
                    min="1"
                    step="1"
                    v-model.number="heir_denominator"
                    style="max-width: 65px; height: 31px;"
                    class="text-center px-1"
                    placeholder="分母"
                    title="持分分母"
                  ></b-form-input>
                  <b-input-group-prepend is-text class="px-2">分之</b-input-group-prepend>
                  <b-form-input
                    type="number"
                    min="1"
                    step="1"
                    v-model.number="heir_numerator"
                    style="max-width: 65px; height: 31px;"
                    class="text-center px-1"
                    placeholder="分子"
                    title="持分分子"
                  ></b-form-input>
                  <b-input-group-append is-text class="font-weight-bold text-primary px-2" v-if="heir_denominator != heir_numerator && heirShareText !== (heir_denominator + ' 分之 ' + heir_numerator)">
                    (約 {{ heirShareText }})
                  </b-input-group-append>
                </b-input-group>
              </div>

              <!-- 常用情境一鍵套用 -->
              <div class="col-12 col-md-3 text-md-right">
                <b-dropdown size="sm" variant="outline-success" text="💡 常用情境套用" right>
                  <b-dropdown-item @click="applyPreset('spouse_2children')">
                    <lah-fa-icon icon="users" class="mr-1 text-primary"></lah-fa-icon>配偶 + 2名子女均分
                  </b-dropdown-item>
                  <b-dropdown-item @click="applyPreset('only_3children')">
                    <lah-fa-icon icon="child" class="mr-1 text-info"></lah-fa-icon>單純 3名子女均分
                  </b-dropdown-item>
                  <b-dropdown-item @click="applyPreset('spouse_parents')">
                    <lah-fa-icon icon="user-friends" class="mr-1 text-success"></lah-fa-icon>配偶 + 父母2人
                  </b-dropdown-item>
                  <b-dropdown-item @click="applyPreset('spouse_brothers')">
                    <lah-fa-icon icon="user-friends" class="mr-1 text-warning"></lah-fa-icon>配偶 + 兄弟姊妹2人
                  </b-dropdown-item>
                  <b-dropdown-item @click="applyPreset('spouse_only')">
                    <lah-fa-icon icon="user" class="mr-1 text-secondary"></lah-fa-icon>僅配偶1人單獨繼承
                  </b-dropdown-item>
                </b-dropdown>
              </div>
            </div>
          </div>

          <!-- 提示未死亡狀態 (no-print) -->
          <b-alert show variant="warning" class="no-print py-2 mb-3" v-if="!dead">
            <lah-fa-icon icon="exclamation-circle" class="mr-2"></lah-fa-icon>
            目前被繼承人標記為「尚未死亡」。請先勾選上方<strong>「已死亡」</strong>或點選<strong>「常用情境套用」</strong>以開始試算。
          </b-alert>

          <!-- 主操作引導與試算內容 (dead 為 true 時顯示) -->
          <div v-show="dead">
            <!-- 步驟條指示器 (Stepper) (no-print) -->
            <div class="stepper-bar d-flex justify-content-around mb-3 p-2 bg-light rounded border no-print">
              <div
                class="stepper-step text-center cursor-pointer flex-fill"
                :class="{ 'active text-primary font-weight-bold': activeStep === 0 }"
                @click="activeStep = 0"
              >
                <span class="badge badge-pill mr-1" :class="activeStep === 0 ? 'badge-primary' : 'badge-secondary'">1</span>
                發生時點：{{ wizard.s0.value === '' ? '選擇區間' : (wizard.s0.value === -1 ? '光復前' : '光復後') }}
              </div>
              <div class="text-muted d-flex align-items-center">➔</div>
              <div
                class="stepper-step text-center cursor-pointer flex-fill"
                :class="{ 'active text-primary font-weight-bold': activeStep === 1 }"
                @click="activeStep = 1"
              >
                <span class="badge badge-pill mr-1" :class="activeStep === 1 ? 'badge-primary' : 'badge-secondary'">2</span>
                繼承身分與人數配置
              </div>
              <div class="text-muted d-flex align-items-center">➔</div>
              <div
                class="stepper-step text-center cursor-pointer flex-fill"
                :class="{ 'active text-primary font-weight-bold': activeStep === 2 }"
                @click="activeStep = 2"
              >
                <span class="badge badge-pill mr-1" :class="activeStep === 2 ? 'badge-primary' : 'badge-secondary'">3</span>
                試算明細與圖表 ({{ totalHeirCount }}人)
              </div>
            </div>

            <!-- 雙欄佈局：左側引導配置，右側圖表與明細總表 -->
            <div class="row">
              <!-- 左欄：法規引導與人數設定 (no-print) -->
              <div class="col-12 col-lg-5 mb-3 no-print">
                <b-card no-body class="border h-100 shadow-sm">
                  <b-card-header class="bg-light py-2 font-weight-bold d-flex justify-content-between align-items-center">
                    <span>
                      <lah-fa-icon icon="sliders-h" class="text-primary mr-1"></lah-fa-icon>
                      {{ activeStepTitle }}
                    </span>
                    <b-button size="sm" variant="outline-secondary" @click="resetStepCurrent" title="重設本階段">
                      <lah-fa-icon icon="sync-alt"></lah-fa-icon>
                    </b-button>
                  </b-card-header>

                  <b-card-body class="p-3">
                    <!-- Step 0: 選擇發生區間 -->
                    <fieldset class="border p-2 rounded mb-3">
                      <legend class="w-auto px-2 font-weight-bold h6 text-primary mb-1">
                        <lah-fa-icon icon="calendar-alt" class="mr-1"></lah-fa-icon>第一步：死亡時間點
                      </legend>
                      <div class="row text-center py-1">
                        <label
                          class="col-6 mb-0 cursor-pointer"
                          v-b-popover.hover.bottom="{ customClass: 'my-popover', content: '民國34年10月24日以前' }"
                        >
                          <input type="radio" v-model.number="wizard.s0.value" :value="-1" @change="s0ValueSelected" />
                          <span class="ml-1 font-weight-bold">光復前</span>
                        </label>
                        <label
                          class="col-6 mb-0 cursor-pointer"
                          v-b-popover.hover.bottom="{ customClass: 'my-popover', content: '民國34年10月25日以後' }"
                        >
                          <input type="radio" v-model.number="wizard.s0.value" :value="0" @change="s0ValueSelected" />
                          <span class="ml-1 font-weight-bold">光復後</span>
                        </label>
                      </div>
                    </fieldset>

                    <!-- Step 1: 光復前 -->
                    <lah-transition fade>
                      <fieldset class="border p-2 rounded mb-3" v-if="wizard.s0.value === -1">
                        <legend class="w-auto px-2 font-weight-bold h6 text-primary mb-1">
                          <lah-fa-icon icon="landmark" class="mr-1"></lah-fa-icon>第二步：光復前財產種類
                        </legend>
                        <div class="row text-center py-1 mb-2">
                          <label class="col-6 mb-0 cursor-pointer">
                            <input type="radio" v-model="wizard.s1.value" value="public" @change="s1ValueSelected" />
                            <span class="ml-1 font-weight-bold">家產</span>
                          </label>
                          <label class="col-6 mb-0 cursor-pointer">
                            <input type="radio" v-model="wizard.s1.value" value="private" @change="s1ValueSelected" />
                            <span class="ml-1 font-weight-bold">私產</span>
                          </label>
                        </div>

                        <!-- 家產 -->
                        <div v-if="wizard.s1.value === 'public'" class="border-top pt-2">
                          <b-alert show variant="info" class="p-2 small mb-2">
                            法定推定財產繼承人為<strong>男子直系卑親屬</strong>，以親等近者為優先。親等相同之男子數人均分。
                          </b-alert>
                          <div class="d-flex align-items-center justify-content-between p-2 bg-light rounded">
                            <span>男子直系卑親屬人數：</span>
                            <b-form-spinbutton v-model="wizard.s1.public.count" min="0" size="sm" inline></b-form-spinbutton>
                          </div>
                        </div>

                        <!-- 私產 -->
                        <div v-if="wizard.s1.value === 'private'" class="border-top pt-2">
                          <b-alert show variant="info" class="p-2 small mb-2">
                            依順序繼承：①直系卑親屬(男) ➔ ②配偶 ➔ ③直系尊親屬 ➔ ④戶主。前順序有人時，後順序無繼承權。
                          </b-alert>
                          <div class="list-group list-group-flush small">
                            <div class="list-group-item d-flex justify-content-between align-items-center px-1 py-2">
                              <span>1. 直系卑親屬人數：</span>
                              <b-form-spinbutton v-model="wizard.s1.private.child" min="0" size="sm" inline :disabled="!seen_s1_private_1"></b-form-spinbutton>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-1 py-2">
                              <span>2. 配偶 (存活人數)：</span>
                              <b-form-spinbutton v-model="wizard.s1.private.spouse" min="0" max="1" size="sm" inline :disabled="!seen_s1_private_2"></b-form-spinbutton>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-1 py-2">
                              <span>3. 直系尊親屬人數：</span>
                              <b-form-spinbutton v-model="wizard.s1.private.parent" min="0" size="sm" inline :disabled="!seen_s1_private_3"></b-form-spinbutton>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-1 py-2">
                              <span>4. 戶主：</span>
                              <b-form-spinbutton v-model="wizard.s1.private.household" min="0" max="1" size="sm" inline :disabled="!seen_s1_private_4"></b-form-spinbutton>
                            </div>
                          </div>
                        </div>
                      </fieldset>
                    </lah-transition>

                    <!-- Step 2: 光復後 -->
                    <lah-transition fade>
                      <fieldset class="border p-2 rounded mb-3" v-if="wizard.s0.value === 0">
                        <legend class="w-auto px-2 font-weight-bold h6 text-primary mb-1">
                          <lah-fa-icon icon="balance-scale" class="mr-1"></lah-fa-icon>第二步：光復後法規時段
                        </legend>
                        <div class="row text-center py-1 mb-2">
                          <label class="col-6 mb-0 cursor-pointer">
                            <input type="radio" v-model="wizard.s2.value" value="7464" @change="s2ValueSelected" />
                            <span class="ml-1 small font-weight-bold">74/06/04 以前</span>
                          </label>
                          <label class="col-6 mb-0 cursor-pointer">
                            <input type="radio" v-model="wizard.s2.value" value="7465" @change="s2ValueSelected" />
                            <span class="ml-1 small font-weight-bold">74/06/05 以後</span>
                          </label>
                        </div>

                        <div v-if="wizard.s2.value" class="border-top pt-2">
                          <div class="d-flex justify-content-between align-items-center mb-2">
                            <b-form-checkbox
                              v-model="wizard.s2.spouse"
                              :value="1"
                              :unchecked-value="0"
                              size="sm"
                              switch
                              class="font-weight-bold"
                            >
                              配偶存活（當然繼承人）
                            </b-form-checkbox>
                            <b-link href="#" class="small text-danger" @click.prevent="resetS2Counter">清空人數</b-link>
                          </div>

                          <div class="list-group list-group-flush small">
                            <!-- 第一順位：直系卑親屬 -->
                            <div class="list-group-item px-1 py-2">
                              <div class="d-flex justify-content-between align-items-center">
                                <span>第一順序：直系卑親屬人數</span>
                                <b-form-spinbutton v-model="wizard.s2.children" min="0" size="sm" inline :disabled="!seen_s2_children"></b-form-spinbutton>
                              </div>
                              <div v-if="wizard.s2.value === '7464'" class="mt-1 pl-3 d-flex justify-content-between align-items-center text-muted">
                                <span>↳ 養子女 (應繼分為婚生子女之半)：</span>
                                <b-form-spinbutton v-model="wizard.s2.raising_children" min="0" size="sm" inline :disabled="!seen_s2_children"></b-form-spinbutton>
                              </div>
                            </div>

                            <!-- 第二順位：父母 -->
                            <div class="list-group-item d-flex justify-content-between align-items-center px-1 py-2">
                              <span>第二順序：父母人數</span>
                              <b-form-spinbutton v-model="wizard.s2.parents" min="0" max="2" size="sm" inline :disabled="!seen_s2_parents"></b-form-spinbutton>
                            </div>

                            <!-- 第三順位：兄弟姊妹 -->
                            <div class="list-group-item d-flex justify-content-between align-items-center px-1 py-2">
                              <span>第三順序：兄弟姊妹人數</span>
                              <b-form-spinbutton v-model="wizard.s2.brothers" min="0" size="sm" inline :disabled="!seen_s2_brothers"></b-form-spinbutton>
                            </div>

                            <!-- 第四順位：祖父母 -->
                            <div class="list-group-item d-flex justify-content-between align-items-center px-1 py-2">
                              <span>第四順序：祖父母人數</span>
                              <b-form-spinbutton v-model="wizard.s2.grandparents" min="0" max="4" size="sm" inline :disabled="!seen_s2_grandparents"></b-form-spinbutton>
                            </div>
                          </div>
                        </div>
                      </fieldset>
                    </lah-transition>
                  </b-card-body>
                </b-card>
              </div>

              <!-- 右欄：試算總表、名冊明細與圖表 -->
              <div class="col-12 col-lg-7 mb-3">
                <b-card no-body class="border shadow-sm h-100">
                  <b-card-header class="bg-light py-2 d-flex flex-wrap justify-content-between align-items-center">
                    <span class="font-weight-bold text-dark mb-1 mb-md-0">
                      <lah-fa-icon icon="table" class="text-success mr-1"></lah-fa-icon>
                      繼承應繼分與特留分試算分配表
                    </span>
                    <div class="d-flex align-items-center no-print">
                      <b-button
                        size="sm"
                        :variant="show_individual_list ? 'primary' : 'outline-primary'"
                        class="mr-2 py-0"
                        @click="toggleIndividualMode"
                        title="切換身分別總表或個別姓名名單"
                      >
                        <lah-fa-icon :icon="show_individual_list ? 'users' : 'user-tag'" class="mr-1"></lah-fa-icon>
                        {{ show_individual_list ? '身分別總表' : '個別名單模式' }}
                      </b-button>
                      <b-button size="sm" variant="outline-dark" class="mr-1 py-0" @click="copySummary" title="複製試算文字摘要至剪貼簿">
                        <lah-fa-icon icon="copy" class="mr-1"></lah-fa-icon>複製
                      </b-button>
                      <b-button size="sm" variant="outline-success" class="py-0" @click="exportCsv" title="匯出 CSV 試算表">
                        <lah-fa-icon icon="file-csv" class="mr-1"></lah-fa-icon>CSV
                      </b-button>
                    </div>
                  </b-card-header>

                  <b-card-body class="p-3">
                    <!-- 圓餅圖展示 (有繼承人時呈現) -->
                    <div v-show="totalHeirCount > 0" class="mb-3 text-center">
                      <div style="max-height: 240px; position: relative;">
                        <lah-chart ref="pie" type="pie"></lah-chart>
                      </div>
                    </div>

                    <!-- 無繼承人提示 -->
                    <div v-if="totalHeirCount === 0" class="text-center py-4 text-muted">
                      <lah-fa-icon icon="user-slash" size="2x" class="mb-2 text-secondary"></lah-fa-icon>
                      <p class="m-0 font-weight-bold">尚未配置繼承人人數</p>
                      <small class="text-muted">請於左側設定身分人數，或點選上方「💡 常用情境套用」快速產生試算。</small>
                    </div>

                    <!-- 試算分配明細表格 (身分別總表模式) -->
                    <div v-if="totalHeirCount > 0 && !show_individual_list" class="table-responsive mb-3">
                      <table class="table table-sm table-bordered table-hover text-center align-middle m-0 small">
                        <thead class="thead-light">
                          <tr>
                            <th>繼承身分別</th>
                            <th style="width: 60px">人數</th>
                            <th>法定應繼分比例</th>
                            <th>每人取得持分</th>
                            <th>全體合計持分</th>
                            <th>每人特留分持分</th>
                            <th style="width: 70px">佔比</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="(item, idx) in calculatedShares" :key="idx">
                            <td class="font-weight-bold text-left pl-2">
                              <span class="badge mr-1" :style="{ backgroundColor: item.color, color: '#fff' }">●</span>
                              {{ item.role }}
                            </td>
                            <td>{{ item.count }}</td>
                            <td><b-badge variant="info">{{ item.shareRatioText }}</b-badge></td>
                            <td class="font-weight-bold text-primary">{{ item.singleShareText }}</td>
                            <td>{{ item.totalShareText }}</td>
                            <td class="text-danger">{{ item.singleReserveText }}</td>
                            <td>{{ item.percent }}</td>
                          </tr>
                        </tbody>
                        <tfoot class="bg-light font-weight-bold">
                          <tr>
                            <td class="text-right">合計：</td>
                            <td>{{ totalHeirCount }} 人</td>
                            <td>-</td>
                            <td>-</td>
                            <td class="text-success">{{ totalAllocatedFraction.text }}</td>
                            <td>-</td>
                            <td>100%</td>
                          </tr>
                        </tfoot>
                      </table>
                    </div>

                    <!-- 試算分配明細表格 (個別繼承人名單模式) -->
                    <div v-if="totalHeirCount > 0 && show_individual_list" class="table-responsive mb-3">
                      <div class="d-flex justify-content-between align-items-center mb-1 no-print">
                        <small class="text-muted">💡 可於下方直接自訂或修改每位繼承人之姓名／稱謂：</small>
                        <b-button size="sm" variant="link" class="p-0 text-secondary small" @click="resetIndividualNames">還原預設稱謂</b-button>
                      </div>
                      <table class="table table-sm table-bordered table-hover text-center align-middle m-0 small">
                        <thead class="thead-light">
                          <tr>
                            <th style="width: 40px">#</th>
                            <th>自訂稱謂／姓名</th>
                            <th>身分類別</th>
                            <th>應繼分比例</th>
                            <th>取得持分</th>
                            <th>特留分持分</th>
                            <th style="width: 70px">佔比</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="(heir, hIdx) in individualHeirs" :key="heir.id">
                            <td>{{ hIdx + 1 }}</td>
                            <td class="text-left px-2">
                              <b-form-input
                                size="sm"
                                v-model="heir.name"
                                class="py-0 px-1 border-0 bg-transparent font-weight-bold no-print"
                                style="height: 24px"
                                placeholder="稱謂/姓名"
                              ></b-form-input>
                              <span class="print-only font-weight-bold">{{ heir.name }}</span>
                            </td>
                            <td><span class="badge badge-light border">{{ heir.category }}</span></td>
                            <td><b-badge variant="info">{{ heir.shareRatioText }}</b-badge></td>
                            <td class="font-weight-bold text-primary">{{ heir.shareText }}</td>
                            <td class="text-danger">{{ heir.reserveText }}</td>
                            <td>{{ heir.percent }}</td>
                          </tr>
                        </tbody>
                        <tfoot class="bg-light font-weight-bold">
                          <tr>
                            <td colspan="3" class="text-right">全體合計：{{ totalHeirCount }} 人</td>
                            <td>-</td>
                            <td class="text-success">{{ totalAllocatedFraction.text }}</td>
                            <td>-</td>
                            <td>100%</td>
                          </tr>
                        </tfoot>
                      </table>
                    </div>

                    <!-- 法規依據說明與備註 -->
                    <div class="border rounded p-2 bg-light text-muted small mt-2">
                      <div class="font-weight-bold text-dark mb-1">
                        <lah-fa-icon icon="info-circle" class="mr-1 text-primary"></lah-fa-icon>
                        試算依據與法規說明：
                      </div>
                      <ul class="pl-3 m-0">
                        <li><strong>法定應繼分：</strong>民法第 1138 條、第 1144 條。各順序繼承人與配偶共同繼承時，依人數與法定比例分配。</li>
                        <li><strong>法定特留分：</strong>民法第 1223 條。直系卑親屬為其應繼分之 1/2；父母為 1/2；配偶為 1/2；兄弟姊妹為 1/3；祖父母為 1/3。</li>
                        <li><strong>被繼承人原持分：</strong>目前設定為 {{ heirShareText }}。繼承人取得持分皆已自動進行精確分數乘法與最簡約分計算。</li>
                      </ul>
                    </div>
                  </b-card-body>
                </b-card>
              </div>
            </div>
          </div>
        </b-card-body>
      </b-card>
    </div>`,
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
      heir_numerator: 1,
      heir_denominator: 1,
      activeStep: 0,
      show_individual_list: false,
      individual_names_custom: {},
      wizard: {
        s0: {
          title: "步驟1，選擇發生區間",
          legend: "被繼承人死亡時間",
          seen: true,
          value: ""
        },
        s1: {
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
      now_step: null,
      color_codes: [
        "2, 117, 216",
        "92, 184, 92",
        "91, 192, 222",
        "240, 173, 78",
        "217, 83, 79",
        "111, 66, 193",
        "32, 201, 151",
        "253, 126, 20",
        "102, 16, 242",
        "232, 62, 140",
        "40, 167, 69",
        "23, 162, 184"
      ],
      color_codes_next: 0,
      vueChartData: {
        labels: [],
        datasets: [{
          label: '繼承分配表',
          data: [],
          backgroundColor: [],
          borderColor: [],
          borderWidth: 1
        }]
      },
      parent_width: 0,
      isBusy: false
    }),
    computed: {
      heirShareText() {
        let num = parseInt(this.heir_numerator) || 1;
        let den = parseInt(this.heir_denominator) || 1;
        return lahSimplifyFraction(num, den).text;
      },
      printDateString() {
        let now = new Date();
        return `${now.getFullYear()}年${now.getMonth() + 1}月${now.getDate()}日`;
      },
      activeStepTitle() {
        if (this.activeStep === 0) return '第一步：被繼承人資訊與時間點';
        if (this.activeStep === 1) return '第二步：繼承身分與人數配置';
        return '第三步：試算結果與名冊分配';
      },
      // 身分別試算結果核心陣列
      calculatedShares() {
        let list = [];
        let num0 = parseInt(this.heir_numerator) || 1;
        let den0 = parseInt(this.heir_denominator) || 1;

        if (this.wizard.s0.value === -1) {
          // 光復前
          if (this.wizard.s1.value === 'public') {
            let count = parseInt(this.wizard.s1.public.count) || 0;
            if (count > 0) {
              let shareRatio = lahSimplifyFraction(1, count);
              let singleShare = lahSimplifyFraction(num0, den0 * count);
              let totalShare = lahSimplifyFraction(num0, den0);
              let singleReserve = lahSimplifyFraction(num0, den0 * count * 2); // 卑親屬特留分 1/2
              list.push({
                role: '直系卑親屬(男)',
                category: '直系卑親屬',
                count: count,
                shareRatioText: shareRatio.text,
                singleShareText: singleShare.text,
                totalShareText: totalShare.text,
                singleReserveText: singleReserve.text,
                percent: singleShare.percent,
                servings: 1,
                color: 'rgba(2, 117, 216, 0.85)'
              });
            }
          } else if (this.wizard.s1.value === 'private') {
            // 私產：依順序繼承
            if (this.wizard.s1.private.child > 0) {
              let count = parseInt(this.wizard.s1.private.child);
              let shareRatio = lahSimplifyFraction(1, count);
              let singleShare = lahSimplifyFraction(num0, den0 * count);
              let totalShare = lahSimplifyFraction(num0, den0);
              let singleReserve = lahSimplifyFraction(num0, den0 * count * 2);
              list.push({
                role: '直系卑親屬(男)',
                category: '直系卑親屬',
                count: count,
                shareRatioText: shareRatio.text,
                singleShareText: singleShare.text,
                totalShareText: totalShare.text,
                singleReserveText: singleReserve.text,
                percent: singleShare.percent,
                servings: 1,
                color: 'rgba(2, 117, 216, 0.85)'
              });
            } else if (this.wizard.s1.private.spouse > 0) {
              let singleShare = lahSimplifyFraction(num0, den0);
              let singleReserve = lahSimplifyFraction(num0, den0 * 2);
              list.push({
                role: '配偶',
                category: '配偶',
                count: 1,
                shareRatioText: '全部 (1/1)',
                singleShareText: singleShare.text,
                totalShareText: singleShare.text,
                singleReserveText: singleReserve.text,
                percent: '100%',
                servings: 1,
                color: 'rgba(217, 83, 79, 0.85)'
              });
            } else if (this.wizard.s1.private.parent > 0) {
              let count = parseInt(this.wizard.s1.private.parent);
              let shareRatio = lahSimplifyFraction(1, count);
              let singleShare = lahSimplifyFraction(num0, den0 * count);
              let totalShare = lahSimplifyFraction(num0, den0);
              let singleReserve = lahSimplifyFraction(num0, den0 * count * 2);
              list.push({
                role: '直系尊親屬',
                category: '直系尊親屬',
                count: count,
                shareRatioText: shareRatio.text,
                singleShareText: singleShare.text,
                totalShareText: totalShare.text,
                singleReserveText: singleReserve.text,
                percent: singleShare.percent,
                servings: 1,
                color: 'rgba(92, 184, 92, 0.85)'
              });
            } else if (this.wizard.s1.private.household > 0) {
              let singleShare = lahSimplifyFraction(num0, den0);
              list.push({
                role: '戶主',
                category: '戶主',
                count: 1,
                shareRatioText: '全部 (1/1)',
                singleShareText: singleShare.text,
                totalShareText: singleShare.text,
                singleReserveText: '無規定',
                percent: '100%',
                servings: 1,
                color: 'rgba(102, 16, 242, 0.85)'
              });
            }
          }
        } else if (this.wizard.s0.value === 0) {
          // 光復後
          let spouse = parseInt(this.wizard.s2.spouse) || 0;
          let children = parseInt(this.wizard.s2.children) || 0;
          let raising = parseInt(this.wizard.s2.raising_children) || 0;
          let parents = parseInt(this.wizard.s2.parents) || 0;
          let brothers = parseInt(this.wizard.s2.brothers) || 0;
          let grandparents = parseInt(this.wizard.s2.grandparents) || 0;

          if (this.wizard.s2.value === '7464') {
            // 74年6月4日以前：養子女為婚生子女之 1/2
            if (children > 0 || raising > 0) {
              // 配偶2份、親生2份、養子女1份
              let totalParts = spouse * 2 + children * 2 + raising * 1;
              if (totalParts > 0) {
                if (spouse > 0) {
                  let ratio = lahSimplifyFraction(2, totalParts);
                  let single = lahSimplifyFraction(num0 * 2, den0 * totalParts);
                  let reserve = lahSimplifyFraction(num0 * 2, den0 * totalParts * 2);
                  list.push({
                    role: '配偶',
                    category: '配偶',
                    count: 1,
                    shareRatioText: ratio.text,
                    singleShareText: single.text,
                    totalShareText: single.text,
                    singleReserveText: reserve.text,
                    percent: single.percent,
                    servings: 2,
                    color: 'rgba(217, 83, 79, 0.85)'
                  });
                }
                if (children > 0) {
                  let ratio = lahSimplifyFraction(2, totalParts);
                  let single = lahSimplifyFraction(num0 * 2, den0 * totalParts);
                  let total = lahSimplifyFraction(num0 * 2 * children, den0 * totalParts);
                  let reserve = lahSimplifyFraction(num0 * 2, den0 * totalParts * 2);
                  list.push({
                    role: '婚生直系卑親屬',
                    category: '直系卑親屬',
                    count: children,
                    shareRatioText: ratio.text,
                    singleShareText: single.text,
                    totalShareText: total.text,
                    singleReserveText: reserve.text,
                    percent: single.percent,
                    servings: 2,
                    color: 'rgba(2, 117, 216, 0.85)'
                  });
                }
                if (raising > 0) {
                  let ratio = lahSimplifyFraction(1, totalParts);
                  let single = lahSimplifyFraction(num0 * 1, den0 * totalParts);
                  let total = lahSimplifyFraction(num0 * 1 * raising, den0 * totalParts);
                  let reserve = lahSimplifyFraction(num0 * 1, den0 * totalParts * 2);
                  list.push({
                    role: '養子女 (1/2)',
                    category: '直系卑親屬',
                    count: raising,
                    shareRatioText: ratio.text,
                    singleShareText: single.text,
                    totalShareText: total.text,
                    singleReserveText: reserve.text,
                    percent: single.percent,
                    servings: 1,
                    color: 'rgba(91, 192, 222, 0.85)'
                  });
                }
              }
            } else if (parents > 0) {
              // 配偶1/2，父母均分其餘1/2
              if (spouse > 0) {
                let single = lahSimplifyFraction(num0, den0 * 2);
                let reserve = lahSimplifyFraction(num0, den0 * 4);
                list.push({
                  role: '配偶',
                  category: '配偶',
                  count: 1,
                  shareRatioText: '2 分之 1',
                  singleShareText: single.text,
                  totalShareText: single.text,
                  singleReserveText: reserve.text,
                  percent: single.percent,
                  servings: parents,
                  color: 'rgba(217, 83, 79, 0.85)'
                });
                let pRatio = lahSimplifyFraction(1, parents * 2);
                let pSingle = lahSimplifyFraction(num0, den0 * parents * 2);
                let pTotal = lahSimplifyFraction(num0, den0 * 2);
                let pReserve = lahSimplifyFraction(num0, den0 * parents * 4);
                list.push({
                  role: '父母',
                  category: '父母',
                  count: parents,
                  shareRatioText: pRatio.text,
                  singleShareText: pSingle.text,
                  totalShareText: pTotal.text,
                  singleReserveText: pReserve.text,
                  percent: pSingle.percent,
                  servings: 1,
                  color: 'rgba(92, 184, 92, 0.85)'
                });
              } else {
                let pRatio = lahSimplifyFraction(1, parents);
                let pSingle = lahSimplifyFraction(num0, den0 * parents);
                let pTotal = lahSimplifyFraction(num0, den0);
                let pReserve = lahSimplifyFraction(num0, den0 * parents * 2);
                list.push({
                  role: '父母',
                  category: '父母',
                  count: parents,
                  shareRatioText: pRatio.text,
                  singleShareText: pSingle.text,
                  totalShareText: pTotal.text,
                  singleReserveText: pReserve.text,
                  percent: pSingle.percent,
                  servings: 1,
                  color: 'rgba(92, 184, 92, 0.85)'
                });
              }
            } else if (brothers > 0) {
              // 配偶1/2，兄弟姊妹均分其餘1/2
              if (spouse > 0) {
                let single = lahSimplifyFraction(num0, den0 * 2);
                let reserve = lahSimplifyFraction(num0, den0 * 4);
                list.push({
                  role: '配偶',
                  category: '配偶',
                  count: 1,
                  shareRatioText: '2 分之 1',
                  singleShareText: single.text,
                  totalShareText: single.text,
                  singleReserveText: reserve.text,
                  percent: single.percent,
                  servings: brothers,
                  color: 'rgba(217, 83, 79, 0.85)'
                });
                let bRatio = lahSimplifyFraction(1, brothers * 2);
                let bSingle = lahSimplifyFraction(num0, den0 * brothers * 2);
                let bTotal = lahSimplifyFraction(num0, den0 * 2);
                let bReserve = lahSimplifyFraction(num0, den0 * brothers * 6); // 兄弟姊妹特留分 1/3
                list.push({
                  role: '兄弟姊妹',
                  category: '兄弟姊妹',
                  count: brothers,
                  shareRatioText: bRatio.text,
                  singleShareText: bSingle.text,
                  totalShareText: bTotal.text,
                  singleReserveText: bReserve.text,
                  percent: bSingle.percent,
                  servings: 1,
                  color: 'rgba(240, 173, 78, 0.85)'
                });
              } else {
                let bRatio = lahSimplifyFraction(1, brothers);
                let bSingle = lahSimplifyFraction(num0, den0 * brothers);
                let bTotal = lahSimplifyFraction(num0, den0);
                let bReserve = lahSimplifyFraction(num0, den0 * brothers * 3);
                list.push({
                  role: '兄弟姊妹',
                  category: '兄弟姊妹',
                  count: brothers,
                  shareRatioText: bRatio.text,
                  singleShareText: bSingle.text,
                  totalShareText: bTotal.text,
                  singleReserveText: bReserve.text,
                  percent: bSingle.percent,
                  servings: 1,
                  color: 'rgba(240, 173, 78, 0.85)'
                });
              }
            } else if (grandparents > 0) {
              // 配偶2/3，祖父母均分其餘1/3
              if (spouse > 0) {
                let single = lahSimplifyFraction(num0 * 2, den0 * 3);
                let reserve = lahSimplifyFraction(num0 * 2, den0 * 6);
                list.push({
                  role: '配偶',
                  category: '配偶',
                  count: 1,
                  shareRatioText: '3 分之 2',
                  singleShareText: single.text,
                  totalShareText: single.text,
                  singleReserveText: reserve.text,
                  percent: single.percent,
                  servings: grandparents * 2,
                  color: 'rgba(217, 83, 79, 0.85)'
                });
                let gRatio = lahSimplifyFraction(1, grandparents * 3);
                let gSingle = lahSimplifyFraction(num0, den0 * grandparents * 3);
                let gTotal = lahSimplifyFraction(num0, den0 * 3);
                let gReserve = lahSimplifyFraction(num0, den0 * grandparents * 9);
                list.push({
                  role: '祖父母',
                  category: '祖父母',
                  count: grandparents,
                  shareRatioText: gRatio.text,
                  singleShareText: gSingle.text,
                  totalShareText: gTotal.text,
                  singleReserveText: gReserve.text,
                  percent: gSingle.percent,
                  servings: 1,
                  color: 'rgba(111, 66, 193, 0.85)'
                });
              } else {
                let gRatio = lahSimplifyFraction(1, grandparents);
                let gSingle = lahSimplifyFraction(num0, den0 * grandparents);
                let gTotal = lahSimplifyFraction(num0, den0);
                let gReserve = lahSimplifyFraction(num0, den0 * grandparents * 3);
                list.push({
                  role: '祖父母',
                  category: '祖父母',
                  count: grandparents,
                  shareRatioText: gRatio.text,
                  singleShareText: gSingle.text,
                  totalShareText: gTotal.text,
                  singleReserveText: gReserve.text,
                  percent: gSingle.percent,
                  servings: 1,
                  color: 'rgba(111, 66, 193, 0.85)'
                });
              }
            } else if (spouse > 0) {
              let single = lahSimplifyFraction(num0, den0);
              let reserve = lahSimplifyFraction(num0, den0 * 2);
              list.push({
                role: '配偶',
                category: '配偶',
                count: 1,
                shareRatioText: '全部 (1/1)',
                singleShareText: single.text,
                totalShareText: single.text,
                singleReserveText: reserve.text,
                percent: '100%',
                servings: 1,
                color: 'rgba(217, 83, 79, 0.85)'
              });
            }
          } else if (this.wizard.s2.value === '7465') {
            // 74年6月5日以後：民法現代化
            if (children > 0) {
              let totalCount = spouse + children;
              if (spouse > 0) {
                let ratio = lahSimplifyFraction(1, totalCount);
                let single = lahSimplifyFraction(num0, den0 * totalCount);
                let reserve = lahSimplifyFraction(num0, den0 * totalCount * 2);
                list.push({
                  role: '配偶',
                  category: '配偶',
                  count: 1,
                  shareRatioText: ratio.text,
                  singleShareText: single.text,
                  totalShareText: single.text,
                  singleReserveText: reserve.text,
                  percent: single.percent,
                  servings: 1,
                  color: 'rgba(217, 83, 79, 0.85)'
                });
              }
              let cRatio = lahSimplifyFraction(1, totalCount);
              let cSingle = lahSimplifyFraction(num0, den0 * totalCount);
              let cTotal = lahSimplifyFraction(num0 * children, den0 * totalCount);
              let cReserve = lahSimplifyFraction(num0, den0 * totalCount * 2);
              list.push({
                role: '直系卑親屬',
                category: '直系卑親屬',
                count: children,
                shareRatioText: cRatio.text,
                singleShareText: cSingle.text,
                totalShareText: cTotal.text,
                singleReserveText: cReserve.text,
                percent: cSingle.percent,
                servings: 1,
                color: 'rgba(2, 117, 216, 0.85)'
              });
            } else if (parents > 0) {
              if (spouse > 0) {
                let single = lahSimplifyFraction(num0, den0 * 2);
                let reserve = lahSimplifyFraction(num0, den0 * 4);
                list.push({
                  role: '配偶',
                  category: '配偶',
                  count: 1,
                  shareRatioText: '2 分之 1',
                  singleShareText: single.text,
                  totalShareText: single.text,
                  singleReserveText: reserve.text,
                  percent: single.percent,
                  servings: parents,
                  color: 'rgba(217, 83, 79, 0.85)'
                });
                let pRatio = lahSimplifyFraction(1, parents * 2);
                let pSingle = lahSimplifyFraction(num0, den0 * parents * 2);
                let pTotal = lahSimplifyFraction(num0, den0 * 2);
                let pReserve = lahSimplifyFraction(num0, den0 * parents * 4);
                list.push({
                  role: '父母',
                  category: '父母',
                  count: parents,
                  shareRatioText: pRatio.text,
                  singleShareText: pSingle.text,
                  totalShareText: pTotal.text,
                  singleReserveText: pReserve.text,
                  percent: pSingle.percent,
                  servings: 1,
                  color: 'rgba(92, 184, 92, 0.85)'
                });
              } else {
                let pRatio = lahSimplifyFraction(1, parents);
                let pSingle = lahSimplifyFraction(num0, den0 * parents);
                let pTotal = lahSimplifyFraction(num0, den0);
                let pReserve = lahSimplifyFraction(num0, den0 * parents * 2);
                list.push({
                  role: '父母',
                  category: '父母',
                  count: parents,
                  shareRatioText: pRatio.text,
                  singleShareText: pSingle.text,
                  totalShareText: pTotal.text,
                  singleReserveText: pReserve.text,
                  percent: pSingle.percent,
                  servings: 1,
                  color: 'rgba(92, 184, 92, 0.85)'
                });
              }
            } else if (brothers > 0) {
              if (spouse > 0) {
                let single = lahSimplifyFraction(num0, den0 * 2);
                let reserve = lahSimplifyFraction(num0, den0 * 4);
                list.push({
                  role: '配偶',
                  category: '配偶',
                  count: 1,
                  shareRatioText: '2 分之 1',
                  singleShareText: single.text,
                  totalShareText: single.text,
                  singleReserveText: reserve.text,
                  percent: single.percent,
                  servings: brothers,
                  color: 'rgba(217, 83, 79, 0.85)'
                });
                let bRatio = lahSimplifyFraction(1, brothers * 2);
                let bSingle = lahSimplifyFraction(num0, den0 * brothers * 2);
                let bTotal = lahSimplifyFraction(num0, den0 * 2);
                let bReserve = lahSimplifyFraction(num0, den0 * brothers * 6);
                list.push({
                  role: '兄弟姊妹',
                  category: '兄弟姊妹',
                  count: brothers,
                  shareRatioText: bRatio.text,
                  singleShareText: bSingle.text,
                  totalShareText: bTotal.text,
                  singleReserveText: bReserve.text,
                  percent: bSingle.percent,
                  servings: 1,
                  color: 'rgba(240, 173, 78, 0.85)'
                });
              } else {
                let bRatio = lahSimplifyFraction(1, brothers);
                let bSingle = lahSimplifyFraction(num0, den0 * brothers);
                let bTotal = lahSimplifyFraction(num0, den0);
                let bReserve = lahSimplifyFraction(num0, den0 * brothers * 3);
                list.push({
                  role: '兄弟姊妹',
                  category: '兄弟姊妹',
                  count: brothers,
                  shareRatioText: bRatio.text,
                  singleShareText: bSingle.text,
                  totalShareText: bTotal.text,
                  singleReserveText: bReserve.text,
                  percent: bSingle.percent,
                  servings: 1,
                  color: 'rgba(240, 173, 78, 0.85)'
                });
              }
            } else if (grandparents > 0) {
              if (spouse > 0) {
                let single = lahSimplifyFraction(num0 * 2, den0 * 3);
                let reserve = lahSimplifyFraction(num0 * 2, den0 * 6);
                list.push({
                  role: '配偶',
                  category: '配偶',
                  count: 1,
                  shareRatioText: '3 分之 2',
                  singleShareText: single.text,
                  totalShareText: single.text,
                  singleReserveText: reserve.text,
                  percent: single.percent,
                  servings: grandparents * 2,
                  color: 'rgba(217, 83, 79, 0.85)'
                });
                let gRatio = lahSimplifyFraction(1, grandparents * 3);
                let gSingle = lahSimplifyFraction(num0, den0 * grandparents * 3);
                let gTotal = lahSimplifyFraction(num0, den0 * 3);
                let gReserve = lahSimplifyFraction(num0, den0 * grandparents * 9);
                list.push({
                  role: '祖父母',
                  category: '祖父母',
                  count: grandparents,
                  shareRatioText: gRatio.text,
                  singleShareText: gSingle.text,
                  totalShareText: gTotal.text,
                  singleReserveText: gReserve.text,
                  percent: gSingle.percent,
                  servings: 1,
                  color: 'rgba(111, 66, 193, 0.85)'
                });
              } else {
                let gRatio = lahSimplifyFraction(1, grandparents);
                let gSingle = lahSimplifyFraction(num0, den0 * grandparents);
                let gTotal = lahSimplifyFraction(num0, den0);
                let gReserve = lahSimplifyFraction(num0, den0 * grandparents * 3);
                list.push({
                  role: '祖父母',
                  category: '祖父母',
                  count: grandparents,
                  shareRatioText: gRatio.text,
                  singleShareText: gSingle.text,
                  totalShareText: gTotal.text,
                  singleReserveText: gReserve.text,
                  percent: gSingle.percent,
                  servings: 1,
                  color: 'rgba(111, 66, 193, 0.85)'
                });
              }
            } else if (spouse > 0) {
              let single = lahSimplifyFraction(num0, den0);
              let reserve = lahSimplifyFraction(num0, den0 * 2);
              list.push({
                role: '配偶',
                category: '配偶',
                count: 1,
                shareRatioText: '全部 (1/1)',
                singleShareText: single.text,
                totalShareText: single.text,
                singleReserveText: reserve.text,
                percent: '100%',
                servings: 1,
                color: 'rgba(217, 83, 79, 0.85)'
              });
            }
          }
        }
        return list;
      },
      // 總繼承人數
      totalHeirCount() {
        return this.calculatedShares.reduce((acc, cur) => acc + cur.count, 0);
      },
      // 分配持分總和 (應等於原持分)
      totalAllocatedFraction() {
        let num0 = parseInt(this.heir_numerator) || 1;
        let den0 = parseInt(this.heir_denominator) || 1;
        if (this.totalHeirCount === 0) return { text: '0', num: 0, den: 1 };
        return lahSimplifyFraction(num0, den0);
      },
      // 個別繼承人清單
      individualHeirs() {
        let list = [];
        let counter = 1;
        this.calculatedShares.forEach(group => {
          for (let i = 1; i <= group.count; i++) {
            let key = `${group.category}_${i}`;
            let defaultName = group.count === 1 ? group.role : `${group.role} ${i}`;
            let customName = this.individual_names_custom[key] || defaultName;
            list.push({
              id: key,
              name: customName,
              category: group.category,
              role: group.role,
              servings: group.servings,
              shareRatioText: group.shareRatioText,
              shareText: group.singleShareText,
              reserveText: group.singleReserveText,
              percent: group.percent,
              color: group.color
            });
            counter++;
          }
        });
        return list;
      },
      // 向後相容屬性
      seen_s1_public() { return this.wizard.s1.value === 'public'; },
      seen_s1_private() { return this.wizard.s1.value === 'private'; },
      seen_s1_private_1() {
        return this.wizard.s1.private.spouse == 0 &&
               this.wizard.s1.private.parent == 0 &&
               this.wizard.s1.private.household == 0;
      },
      seen_s1_private_2() {
        return this.wizard.s1.private.child == 0 &&
               this.wizard.s1.private.parent == 0 &&
               this.wizard.s1.private.household == 0;
      },
      seen_s1_private_3() {
        return this.wizard.s1.private.spouse == 0 &&
               this.wizard.s1.private.child == 0 &&
               this.wizard.s1.private.household == 0;
      },
      seen_s1_private_4() {
        return this.wizard.s1.private.spouse == 0 &&
               this.wizard.s1.private.parent == 0 &&
               this.wizard.s1.private.child == 0;
      },
      seen_s2_children() {
        return this.wizard.s2.parents == 0 &&
               this.wizard.s2.brothers == 0 &&
               this.wizard.s2.grandparents == 0;
      },
      seen_s2_parents() {
        return this.wizard.s2.children == 0 &&
               this.wizard.s2.raising_children == 0 &&
               this.wizard.s2.brothers == 0 &&
               this.wizard.s2.grandparents == 0;
      },
      seen_s2_brothers() {
        return this.wizard.s2.parents == 0 &&
               this.wizard.s2.raising_children == 0 &&
               this.wizard.s2.children == 0 &&
               this.wizard.s2.grandparents == 0;
      },
      seen_s2_grandparents() {
        return this.wizard.s2.parents == 0 &&
               this.wizard.s2.raising_children == 0 &&
               this.wizard.s2.brothers == 0 &&
               this.wizard.s2.children == 0;
      },
      validID() {
        if (!this.id) return null;
        return this.checkID(this.id);
      }
    },
    methods: {
      reset(e) {
        this.wizard.s0.value = "";
        this.activeStep = 0;
        this.heir_numerator = 1;
        this.heir_denominator = 1;
        this.dead = false;
        this.id = '';
        this.name = '權利人';
        this.show_individual_list = false;
        this.individual_names_custom = {};
        this.resetS1();
        this.resetS2();
        this.updateChart();
      },
      resetStepCurrent() {
        if (this.wizard.s0.value === -1) {
          this.resetS1();
        } else if (this.wizard.s0.value === 0) {
          this.resetS2();
        }
      },
      resetS1() {
        this.wizard.s1.value = "";
        this.wizard.s1.public.count = 0;
        this.wizard.s1.private.child = 0;
        this.wizard.s1.private.spouse = 0;
        this.wizard.s1.private.parent = 0;
        this.wizard.s1.private.household = 0;
      },
      resetS2() {
        this.wizard.s2.value = "";
        this.resetS2Counter();
      },
      resetS2Counter() {
        this.wizard.s2.children = 0;
        this.wizard.s2.raising_children = 0;
        this.wizard.s2.spouse = 0;
        this.wizard.s2.parents = 0;
        this.wizard.s2.brothers = 0;
        this.wizard.s2.grandparents = 0;
      },
      s0ValueSelected() {
        if (this.wizard.s0.value === -1) {
          this.resetS2();
        } else if (this.wizard.s0.value === 0) {
          this.resetS1();
        }
        this.activeStep = 1;
      },
      s1ValueSelected() {
        this.wizard.s1.public.count = 0;
        this.wizard.s1.private.child = 0;
        this.wizard.s1.private.spouse = 0;
        this.wizard.s1.private.parent = 0;
        this.wizard.s1.private.household = 0;
      },
      s2ValueSelected() {
        this.resetS2Counter();
      },
      toggleIndividualMode() {
        this.show_individual_list = !this.show_individual_list;
      },
      resetIndividualNames() {
        this.individual_names_custom = {};
      },
      // 常用情境套用
      applyPreset(key) {
        this.dead = true;
        this.wizard.s0.value = 0;
        this.wizard.s2.value = "7465"; // 現代法規
        this.resetS2Counter();
        this.show_individual_list = false;

        switch (key) {
          case 'spouse_2children':
            this.wizard.s2.spouse = 1;
            this.wizard.s2.children = 2;
            break;
          case 'only_3children':
            this.wizard.s2.spouse = 0;
            this.wizard.s2.children = 3;
            break;
          case 'spouse_parents':
            this.wizard.s2.spouse = 1;
            this.wizard.s2.parents = 2;
            break;
          case 'spouse_brothers':
            this.wizard.s2.spouse = 1;
            this.wizard.s2.brothers = 2;
            break;
          case 'spouse_only':
            this.wizard.s2.spouse = 1;
            break;
        }
        this.activeStep = 2;
        this.$nextTick(() => {
          this.updateChart();
        });
      },
      // 更新圓餅圖
      updateChart() {
        this.vueChartData.labels = [];
        this.vueChartData.datasets[0].data = [];
        this.vueChartData.datasets[0].backgroundColor = [];
        this.vueChartData.datasets[0].borderColor = [];

        if (this.totalHeirCount > 0) {
          if (this.show_individual_list) {
            this.individualHeirs.forEach((heir, idx) => {
              this.vueChartData.labels.push(heir.name);
              this.vueChartData.datasets[0].data.push(heir.servings || 1);
              let color = this.color_codes[idx % this.color_codes.length];
              this.vueChartData.datasets[0].backgroundColor.push(`rgba(${color}, 0.85)`);
              this.vueChartData.datasets[0].borderColor.push(`rgba(${color}, 1)`);
            });
          } else {
            let colorIdx = 0;
            this.calculatedShares.forEach((item, idx) => {
              if (item.count > 1) {
                for (let i = 1; i <= item.count; i++) {
                  this.vueChartData.labels.push(`${item.role} ${i}`);
                  this.vueChartData.datasets[0].data.push(item.servings);
                  let color = this.color_codes[colorIdx++ % this.color_codes.length];
                  this.vueChartData.datasets[0].backgroundColor.push(`rgba(${color}, 0.85)`);
                  this.vueChartData.datasets[0].borderColor.push(`rgba(${color}, 1)`);
                }
              } else {
                this.vueChartData.labels.push(item.role);
                this.vueChartData.datasets[0].data.push(item.servings);
                let color = this.color_codes[colorIdx++ % this.color_codes.length];
                this.vueChartData.datasets[0].backgroundColor.push(item.color || `rgba(${color}, 0.85)`);
                this.vueChartData.datasets[0].borderColor.push(`rgba(${color}, 1)`);
              }
            });
          }
        }

        if (this.$refs.pie && typeof this.$refs.pie.buildChart === 'function') {
          this.$refs.pie.chartData = this.vueChartData;
          if (this.totalHeirCount > 0) {
            this.$nextTick(() => {
              if (this.$refs.pie && typeof this.$refs.pie.buildChart === 'function') {
                this.$refs.pie.buildChart({
                  legend_pos: 'bottom',
                  plugins: {}
                });
              }
            });
          } else if (this.$refs.pie.inst) {
            this.$refs.pie.inst.destroy();
            this.$refs.pie.inst = null;
          }
        }
      },
      // 複製文字摘要至剪貼簿
      copySummary() {
        if (this.totalHeirCount === 0) {
          alert('目前尚未配置繼承人人數，無法產生試算摘要。');
          return;
        }

        let timeDesc = this.wizard.s0.value === -1 ? '光復前 (民國34年10月24日以前)' :
          (this.wizard.s2.value === '7464' ? '光復後 (民國74年6月4日以前)' : '光復後 (民國74年6月5日以後)');

        let text = `【桃園地政事務所 - 繼承應繼分試算結果】\n` +
          `被繼承人：${this.name} (${this.id || '未填身分證號'})\n` +
          `被繼承人原持分：${this.heirShareText}\n` +
          `適用法規時點：${timeDesc}\n` +
          `----------------------------------------\n` +
          `【繼承人持分分配清單】\n`;

        this.calculatedShares.forEach((item, idx) => {
          text += `${idx + 1}. ${item.role} (${item.count}人)\n` +
                  `   • 法定應繼分比例：${item.shareRatioText} (${item.percent})\n` +
                  `   • 每人取得持分：${item.singleShareText}\n` +
                  `   • 每人法定特留分：${item.singleReserveText}\n`;
        });

        text += `----------------------------------------\n` +
          `合計繼承人數：${this.totalHeirCount} 人\n` +
          `全體分配持分：${this.totalAllocatedFraction.text} (100% 完全分配)\n` +
          `試算日期：${this.printDateString}\n`;

        if (navigator && navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(() => {
            if (window.vueApp && window.vueApp.makeToast) {
              window.vueApp.makeToast('已將試算結果複製至剪貼簿！', { variant: 'success', title: '複製成功' });
            } else {
              alert('已成功複製試算結果！');
            }
          }).catch(() => {
            this.fallbackCopyText(text);
          });
        } else {
          this.fallbackCopyText(text);
        }
      },
      fallbackCopyText(text) {
        let ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        try {
          document.execCommand('copy');
          alert('已成功複製試算結果！');
        } catch (e) {
          prompt('請手動複製下列內容：', text);
        }
        document.body.removeChild(ta);
      },
      // 匯出 CSV
      exportCsv() {
        if (this.totalHeirCount === 0) {
          alert('目前尚未配置繼承人人數，無法匯出 CSV。');
          return;
        }

        let csvContent = '\uFEFF'; // UTF-8 BOM
        csvContent += '繼承身分類別,稱謂/角色,人數,法定應繼分比例,單人取得持分,全體合計持分,法定特留分持分,佔比\r\n';

        this.calculatedShares.forEach(item => {
          csvContent += `"${item.category}","${item.role}",${item.count},"${item.shareRatioText}","${item.singleShareText}","${item.totalShareText}","${item.singleReserveText}","${item.percent}"\r\n`;
        });

        csvContent += `\r\n被繼承人,"${this.name} (${this.id || ''})"\r\n`;
        csvContent += `被繼承人原持分,"${this.heirShareText}"\r\n`;
        csvContent += `總繼承人數,${this.totalHeirCount} 人\r\n`;
        csvContent += `全體分配持分,"${this.totalAllocatedFraction.text}"\r\n`;
        csvContent += `試算日期,"${this.printDateString}"\r\n`;

        let blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        let url = URL.createObjectURL(blob);
        let link = document.createElement('a');
        link.setAttribute('href', url);
        let safeName = (this.name || '被繼承人').replace(/[\\/:*?"<>|]/g, '');
        link.setAttribute('download', `繼承應繼分試算表_${safeName}_${new Date().toISOString().slice(0, 10)}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
      },
      // 列印試算單
      printReport() {
        if (this.totalHeirCount === 0) {
          alert('請先配置繼承人人數後再進行列印。');
          return;
        }
        window.print();
      },
      // 身分證驗證
      checkID(id) {
        let tab = "ABCDEFGHJKLMNPQRSTUVXYWZIO";
        let A1 = [1,1,1,1,1,1,1,1,1,1,2,2,2,2,2,2,2,2,2,2,3,3,3,3,3,3];
        let A2 = [0,1,2,3,4,5,6,7,8,9,0,1,2,3,4,5,6,7,8,9,0,1,2,3,4,5];
        let Mx = [9,8,7,6,5,4,3,2,1,1];

        if (!id || id.length !== 10) return false;
        let i = tab.indexOf(id.charAt(0));
        if (i === -1) return false;
        let sum = A1[i] + A2[i] * 9;

        for (let j = 1; j < 10; j++) {
          let v = parseInt(id.charAt(j));
          if (isNaN(v)) return false;
          sum = sum + v * Mx[j];
        }
        return sum % 10 === 0;
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
            if (res.data.status == XHR_STATUS_CODE.SUCCESS_NORMAL && res.data.raw && res.data.raw.length > 0) {
              this.name = res.data.raw[0]['LNAM'] || '權利人';
            } else {
              this.name = '權利人';
            }
          }).catch(err => {
            this.name = '權利人';
          }).finally(() => {
            this.isBusy = false;
          });
        }
      },
      calculatedShares: {
        deep: true,
        handler() {
          this.$nextTick(() => {
            this.updateChart();
          });
        }
      },
      show_individual_list() {
        this.$nextTick(() => {
          this.updateChart();
        });
      }
    },
    created() {
      this.now_step = this.wizard.s0;
    },
    mounted() {
      if (this.$refs.pie) {
        this.$refs.pie.chartData = this.vueChartData;
        this.$refs.pie.type = 'pie';
      }
      this.updateChart();
    }
  });
}