if (Vue) {
    // usage:<chart-bar :chart-data="vueData" :options="vueOpts"></chart-bar>
    /** empty dataset:
        this.chartData = {
            labels:[],
            datasets:[{
                label: "The Chart Title",
                backgroundColor:[],
                data: [],
                borderColor:[],
                fill: true,
                type: "bar",
                order: 1
            }]
        };
     */
    Vue.component("chart-bar", {
        /*
          Bar: (...)
          HorizontalBar: (...)
          Doughnut: (...)
          Line: (...)
          Pie: (...)
          PolarArea: (...)
          Radar: (...)
          Bubble: (...)
          Scatter: (...)
        */
        extends: VueChartJs.Bar,
        mixins: [VueChartJs.mixins.reactiveProp], // auto use chartData prop and reactively update
        data: function () {
            return {
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    showLines: true,
                    legend: {
                        display: true,
                        position: "top",
                        reverse: false
                    },
                    title: {
                        display: false
                    }
                }
            }
        }
    });
} else {
    console.error("vue.js not ready ... chart-bar component can not be loaded.");
}
