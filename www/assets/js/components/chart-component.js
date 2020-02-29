if (Vue) {
    // need to include Chart.min.js (chart.js) first.
    Vue.component("chart-component", {
        template: `<div>
            <canvas :id="id">圖形初始化失敗</canvas>
        </div>`,
        data: function () { return {
            id: "__canvas__0",
            type: "bar",
            inst: null,
            chartData: null,
            label: "統計圖表",
            items: []
        } },
        created: function() {
            this.id = this.uuid();
        },
        watch: {
            type: function (val) {
                this.buildChart();
            },
            chartData: function(newObj) {
                this.buildChart();
            },
            items: function(newItems) {
                this.setData(newItems);
            }
        },
        methods: {
            uuid: function() {
                var d = Date.now();
                if (typeof performance !== 'undefined' && typeof performance.now === 'function'){
                    d += performance.now(); //use high-precision timer if available
                }
                return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                    var r = (d + Math.random() * 16) % 16 | 0;
                    d = Math.floor(d / 16);
                    return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
                });
            },
            resetData: function() {
                this.chartData = {
                    labels:[],
                    legend: {
                        display: true
                    },
                    datasets:[{
                        label: this.label,
                        backgroundColor:[],
                        data: [],
                        borderColor:`rgb(22, 22, 22)`,
                        order: 1,
                        opacity: 0.6,
                        snapGaps: true
                    }]
                };
            },
            setData: function(items) {
                this.resetData();
                let opacity = this.chartData.datasets[0].opacity;
                let that = this;
                items.forEach(function(item) {
                    that.chartData.labels.push(item[0]);            // first element is label
                    that.chartData.datasets[0].data.push(item[1]);  // second element is data count
                    // randoom color for this item
                    that.chartData.datasets[0].backgroundColor.push(`rgb(${that.rand(255)}, ${that.rand(255)}, ${that.rand(255)}, ${opacity})`);
                });
                return true;
            },
            buildChart: function (opts = {}) {
                if (this.inst) {
                    // reset the chart
                    this.inst.destroy();
                    this.inst = null;
                }
                // keep only one dataset inside
                if (this.chartData.datasets.length > 1) {
                    this.chartData.datasets = this.chartData.datasets.slice(0, 1);
                }
                this.chartData.datasets[0].label = this.label;
                switch(this.type) {
                    case "pie":
                    case "polarArea":
                    case "doughnut":
                        // put legend to the right for some chart type
                        opts.legend = {
                            display: true,
                            position: 'right'
                        };
                        break;
                    case "radar":
                        break;
                    default:
                        opts.scales = {
                            yAxes: [{
                                display: true,
                                ticks: {
                                    beginAtZero: true
                                }
                            }]
                        };
                }
                // use chart.js directly
                let ctx = $(`#${this.id}`);
                this.inst = new Chart(ctx, {
                    type: this.type,
                    data: this.chartData,
                    options: Object.assign({
                        tooltips: {
                            callbacks: {
                                label: function (tooltipItem, data) {
                                    // add percent ratio to the label
                                    let dataset = data.datasets[tooltipItem.datasetIndex];
                                    let sum = dataset.data.reduce(function (previousValue, currentValue, currentIndex, array) {
                                        return previousValue + currentValue;
                                    });
                                    let currentValue = dataset.data[tooltipItem.index];
                                    let percent = Math.round(((currentValue / sum) * 100));
                                    return ` ${data.labels[tooltipItem.index]} : ${currentValue} [${percent}%]`;
                                }
                            }
                        },
                        title: { display: false, text: "自訂標題", position: "bottom" },
                        onClick: function(e) {
                            let payload = {};
                            payload["point"] = this.inst.getElementAtEvent(evt)[0];
                            if (payload["point"]) {
                                payload["label"] = myChart.data.labels[firstPoint._index];
                                payload["value"] = myChart.data.datasets[firstPoint._datasetIndex].data[firstPoint._index];
                            }
                            // parent uses a handle function to catch the event, e.g. catchClick(e, payload) { ... }
                            this.$emit("click", e, payload);
                        }
                    }, opts)
                });
            },
            toBase64Image: function() { return this.inst.toBase64Image() },
            downloadBase64PNG: function(filename = "download.png") {
                let base64str = this.toBase64Image();
                const link = document.createElement('a');
                link.href = `data:image/png;base64,${base64str}`;
                link.setAttribute("download", filename);
                document.body.appendChild(link);
                link.click();
                //afterwards we remove the element again
                link.remove();            
            },
            rand: (range) => Math.floor(Math.random() * Math.floor(range || 100))
        }
    });
} else {
    console.error("vue.js not ready ... chart-bar component can not be loaded.");
}
