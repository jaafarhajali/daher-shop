/**
 * Reports page: date-range presets + optional single-series chart.
 */
(function () {
  'use strict';

  // --- date presets ---------------------------------------------------------
  var preset = document.getElementById('rangePreset');
  var from = document.getElementById('fromDate');
  var to = document.getElementById('toDate');

  function iso(d) {
    return d.getFullYear() + '-' +
      String(d.getMonth() + 1).padStart(2, '0') + '-' +
      String(d.getDate()).padStart(2, '0');
  }

  if (preset && from && to) {
    preset.addEventListener('change', function () {
      var now = new Date();
      var a = null, b = null;

      switch (preset.value) {
        case 'today':
          a = b = now; break;
        case 'yesterday':
          a = b = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1); break;
        case 'week': {
          var dow = (now.getDay() + 6) % 7;           // Monday-based
          a = new Date(now.getFullYear(), now.getMonth(), now.getDate() - dow);
          b = now; break;
        }
        case 'month':
          a = new Date(now.getFullYear(), now.getMonth(), 1);
          b = now; break;
        case 'last-month':
          a = new Date(now.getFullYear(), now.getMonth() - 1, 1);
          b = new Date(now.getFullYear(), now.getMonth(), 0); break;
        case 'year':
          a = new Date(now.getFullYear(), 0, 1);
          b = now; break;
        default:
          return;
      }
      from.value = iso(a);
      to.value = iso(b);
    });
  }

  // --- chart -----------------------------------------------------------------
  if (!window.REPORT_CHART || typeof Chart === 'undefined') return;

  var chart = null;

  function build() {
    var el = document.getElementById('reportChart');
    if (!el) return;
    if (chart) chart.destroy();

    var ink = DS.chartInk();
    chart = new Chart(el, {
      type: 'bar',
      data: {
        labels: REPORT_CHART.labels,
        datasets: [{
          label: REPORT_CHART.label,
          data: REPORT_CHART.values,
          backgroundColor: ink.series1,
          borderRadius: { topLeft: 4, topRight: 4 },
          maxBarThickness: 26
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: ink.ink,
            titleColor: ink.grid,
            bodyColor: ink.grid,
            displayColors: false,
            callbacks: {
              label: function (ctx) {
                return REPORT_CHART.label + ': ' + REPORT_CHART.currency + DS.money(ctx.parsed.y);
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
              color: ink.muted, font: { size: 11 },
              callback: function (v) { return REPORT_CHART.currency + Number(v).toLocaleString(); }
            },
            border: { display: false }
          }
        }
      }
    });
  }

  build();
  document.addEventListener('ds:theme-changed', build);
})();
