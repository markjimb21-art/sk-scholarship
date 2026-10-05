/*
 * Small wrappers around the local Chart.js (public/assets/vendor/chart.js) so every chart looks and reads the same:
 * labelled axes, recessive grid, validated categorical colours in fixed order (blue, orange, aqua — checked for colour-
 * vision deficiency; every chart also has its numbers in a table next to it), a legend only for 2+ series, value tooltips.
 * Views call SKCharts.bar(...) / SKCharts.line(...) after DOMContentLoaded.
 */
window.SKCharts = (function () {
  var COLORS = ['#2a78d6', '#eb6834', '#1baf7a'];
  var INK = '#52514e', GRID = '#e1e0d9';

  function fmt(v, money) {
    if (v === null || v === undefined) return '—';
    return money ? '₱' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : String(v);
  }
  function axisTitle(text) { return { display: !!text, text: text || '', color: INK, font: { size: 15 } }; }

  /**
   * bar(id, labels, series, opts)  series: [{label, data}] (one series = one hue, no legend)
   * opts: { money, horizontal, xTitle, yTitle, integer }
   */
  function bar(id, labels, series, opts) {
    var el = document.getElementById(id);
    if (!el || !window.Chart) return null;
    opts = opts || {};
    var horizontal = !!opts.horizontal, valueAxis = horizontal ? 'x' : 'y', catAxis = horizontal ? 'y' : 'x';
    var scales = {};
    scales[valueAxis] = {
      beginAtZero: true, grid: { color: GRID }, title: axisTitle(horizontal ? opts.xTitle : opts.yTitle),
      ticks: { color: INK, precision: opts.integer ? 0 : undefined, callback: function (v) { return opts.money ? '₱' + Number(v).toLocaleString('en-PH') : v; } }
    };
    scales[catAxis] = { grid: { display: false }, title: axisTitle(horizontal ? opts.yTitle : opts.xTitle), ticks: { color: INK } };
    return new Chart(el, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: series.map(function (s, i) {
          return { label: s.label, data: s.data, backgroundColor: COLORS[i % COLORS.length], maxBarThickness: 26, borderRadius: 4, borderSkipped: 'start' };
        })
      },
      options: {
        indexAxis: horizontal ? 'y' : 'x', maintainAspectRatio: false,
        plugins: {
          legend: { display: series.length > 1, position: 'top', labels: { color: INK, boxWidth: 12 } },
          tooltip: { callbacks: { label: function (c) { return (series.length > 1 ? c.dataset.label + ': ' : '') + fmt(c.parsed[valueAxis], opts.money); } } }
        },
        scales: scales
      }
    });
  }

  /** line(id, labels, series, opts)  opts: { reverse (lower = better, e.g. GWA), yMin, yMax, xTitle, yTitle, decimals } */
  function line(id, labels, series, opts) {
    var el = document.getElementById(id);
    if (!el || !window.Chart) return null;
    opts = opts || {};
    return new Chart(el, {
      type: 'line',
      data: {
        labels: labels,
        datasets: series.map(function (s, i) {
          return { label: s.label, data: s.data, borderColor: COLORS[i % COLORS.length], backgroundColor: COLORS[i % COLORS.length],
                   borderWidth: 2, pointRadius: 4, pointHoverRadius: 6, tension: 0 };
        })
      },
      options: {
        maintainAspectRatio: false,
        plugins: {
          legend: { display: series.length > 1, position: 'top', labels: { color: INK, boxWidth: 12 } },
          tooltip: { callbacks: { label: function (c) { return (series.length > 1 ? c.dataset.label + ': ' : '') + Number(c.parsed.y).toFixed(opts.decimals || 2); } } }
        },
        scales: {
          y: { reverse: !!opts.reverse, min: opts.yMin, max: opts.yMax, grid: { color: GRID }, title: axisTitle(opts.yTitle), ticks: { color: INK } },
          x: { grid: { display: false }, title: axisTitle(opts.xTitle), ticks: { color: INK } }
        }
      }
    });
  }

  return { bar: bar, line: line, colors: COLORS };
})();
