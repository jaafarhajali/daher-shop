/**
 * Dashboard charts. Data arrives via window.DASH (built server-side).
 * Charts rebuild on theme change so grid/ink colors always match the mode.
 */
(function () {
  'use strict';

  if (!window.DASH || typeof Chart === 'undefined') return;

  var charts = [];

  function baseOptions(ink) {
    return {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: ink.ink,
          titleColor: ink.grid,
          bodyColor: ink.grid,
          padding: 10,
          displayColors: false,
          callbacks: {
            label: function (ctx) {
              var v = ctx.parsed.y !== undefined ? ctx.parsed.y : ctx.parsed.x;
              return (ctx.dataset.label ? ctx.dataset.label + ': ' : '')
                   + DASH.currency + DS.money(v);
            }
          }
        }
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: { color: ink.muted, maxRotation: 0, autoSkip: true, font: { size: 11 } },
          border: { color: ink.grid }
        },
        y: {
          beginAtZero: true,
          grid: { color: ink.grid, drawTicks: false },
          ticks: {
            color: ink.muted,
            font: { size: 11 },
            callback: function (v) { return DASH.currency + Number(v).toLocaleString(); }
          },
          border: { display: false }
        }
      }
    };
  }

  function build() {
    charts.forEach(function (c) { c.destroy(); });
    charts = [];
    var ink = DS.chartInk();

    // --- 14-day sales trend (single series → no legend) ---------------------
    var elTrend = document.getElementById('chartTrend');
    if (elTrend) {
      charts.push(new Chart(elTrend, {
        type: 'line',
        data: {
          labels: DASH.trend.labels,
          datasets: [{
            label: 'Sales',
            data: DASH.trend.values,
            borderColor: ink.series1,
            borderWidth: 2,
            pointRadius: 0,
            pointHoverRadius: 5,
            pointHoverBackgroundColor: ink.series1,
            tension: 0.3,
            fill: true,
            backgroundColor: function (ctx) {
              var g = ctx.chart.ctx.createLinearGradient(0, 0, 0, ctx.chart.height);
              g.addColorStop(0, 'rgba(13,148,136,0.18)');
              g.addColorStop(1, 'rgba(13,148,136,0)');
              return g;
            }
          }]
        },
        options: baseOptions(ink)
      }));
    }

    // --- revenue vs expenses (two series → legend, fixed colors) -------------
    var elRevExp = document.getElementById('chartRevExp');
    if (elRevExp) {
      var opts = baseOptions(ink);
      opts.plugins.legend = {
        display: true,
        position: 'bottom',
        labels: { color: ink.muted, boxWidth: 10, boxHeight: 10, usePointStyle: true, pointStyle: 'rectRounded' }
      };
      charts.push(new Chart(elRevExp, {
        type: 'bar',
        data: {
          labels: DASH.revExp.labels,
          datasets: [
            {
              label: 'Revenue',
              data: DASH.revExp.revenue,
              backgroundColor: ink.series1,
              borderRadius: { topLeft: 4, topRight: 4 },
              maxBarThickness: 22
            },
            {
              label: 'Expenses',
              data: DASH.revExp.expenses,
              backgroundColor: ink.series2,
              borderRadius: { topLeft: 4, topRight: 4 },
              maxBarThickness: 22
            }
          ]
        },
        options: opts
      }));
    }

    // --- top products (horizontal, single series) -----------------------------
    var elTop = document.getElementById('chartTop');
    var elTopEmpty = document.getElementById('chartTopEmpty');
    if (elTop) {
      if (!DASH.top.length) {
        elTop.parentElement.classList.add('d-none');
        if (elTopEmpty) elTopEmpty.classList.remove('d-none');
      } else {
        var opts2 = baseOptions(ink);
        opts2.indexAxis = 'y';
        opts2.scales = {
          x: {
            beginAtZero: true,
            grid: { color: ink.grid, drawTicks: false },
            ticks: {
              color: ink.muted, font: { size: 11 },
              callback: function (v) { return DASH.currency + Number(v).toLocaleString(); }
            },
            border: { display: false }
          },
          y: {
            grid: { display: false },
            ticks: { color: ink.ink, font: { size: 11 }, autoSkip: false,
              callback: function (v) {
                var label = this.getLabelForValue(v);
                return label.length > 18 ? label.slice(0, 17) + '…' : label;
              }
            },
            border: { color: ink.grid }
          }
        };
        opts2.plugins.tooltip.callbacks.label = function (ctx) {
          var item = DASH.top[ctx.dataIndex];
          return DASH.currency + DS.money(item.revenue) + ' · ' + item.qty + ' sold';
        };
        charts.push(new Chart(elTop, {
          type: 'bar',
          data: {
            labels: DASH.top.map(function (t) { return t.name; }),
            datasets: [{
              label: 'Revenue',
              data: DASH.top.map(function (t) { return t.revenue; }),
              backgroundColor: ink.series1,
              borderRadius: { topRight: 4, bottomRight: 4 },
              maxBarThickness: 18
            }]
          },
          options: opts2
        }));
      }
    }
  }

  build();
  document.addEventListener('ds:theme-changed', build);
})();
