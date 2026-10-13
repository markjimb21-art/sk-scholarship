/**
 * Data analytics page. The numbers come from the server as JSON (<script type="application/json"> blocks written with
 * js_json()): #profileChartData (applicant profiles, GWA trend, fund use; drawn with SKCharts) and #analyticsChartData
 * (funnel, budget and score bands; drawn here).
 */
(function () {
  'use strict';

  // The blocks are only on the page when there is a program to analyse.
  var data = function (id) { var el = document.getElementById(id); return el ? JSON.parse(el.textContent) : null; };

  document.addEventListener('DOMContentLoaded', function () {
    var d = data('profileChartData');
    if (!window.SKCharts || !d) return;
    // (5) applicants (blue) vs approved (orange) per group — same colours in every profile chart.
    Object.keys(d.profiles).forEach(function (dim) {
      var p = d.profiles[dim];
      if (!p.labels.length) return;
      SKCharts.bar('profile_' + dim, p.labels, [{ label: 'Applicants', data: p.applicants }, { label: 'Approved', data: p.approved }],
        { horizontal: true, integer: true, xTitle: 'Applications' });
    });
    // (6) average GWA per semester; lower is better, so the axis is reversed (better = higher on the chart).
    SKCharts.line('gwaTrendChart', d.gwaTrend.labels, [{ label: 'Average GWA', data: d.gwaTrend.values }],
      { reverse: true, yMin: 1, xTitle: 'Semester', yTitle: 'Average GWA (axis reversed: higher = better)' });
    // (7) fund utilization over time
    SKCharts.bar('releasedMonthChart', d.releasedByMonth.labels, [{ label: 'Released', data: d.releasedByMonth.values }],
      { money: true, xTitle: 'Release month', yTitle: 'Pesos' });
    SKCharts.bar('releasedSemChart', d.releasedBySemester.labels, [{ label: 'Released', data: d.releasedBySemester.values }],
      { money: true, xTitle: 'Semester', yTitle: 'Pesos' });
  });

  document.addEventListener('DOMContentLoaded', function () {
    var d = data('analyticsChartData');
    if (!window.Chart || !d) return;
    // One series per chart: one hue, no legend; thin bars with a rounded data end; recessive grid; value on hover.
    var hue = '#2a78d6', ink = '#52514e', grid = 'rgba(0,0,0,.06)';
    function bar(id, labels, values, money) {
      var fmt = function (v) { return money ? '₱' + Number(v).toLocaleString('en-PH', {minimumFractionDigits: 2}) : String(v); };
      new Chart(document.getElementById(id), {
        type: 'bar',
        data: { labels: labels, datasets: [{ data: values, backgroundColor: hue, maxBarThickness: 24, borderRadius: 4, borderSkipped: 'start' }] },
        options: {
          indexAxis: 'y', maintainAspectRatio: false,
          plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return fmt(c.parsed.x); } } } },
          scales: {
            x: { beginAtZero: true, grid: { color: grid }, ticks: { color: ink, precision: 0, callback: function (v) { return money ? '₱' + Number(v).toLocaleString('en-PH') : v; } } },
            y: { grid: { display: false }, ticks: { color: ink } }
          }
        }
      });
    }
    bar('funnelChart', d.funnel.labels, d.funnel.values, false);
    bar('budgetChart', ['Total budget', 'Released', 'Committed', 'Projected need'], d.budget, true);
    bar('scoreChart', d.scores.labels, d.scores.values, false);
  });
})();
