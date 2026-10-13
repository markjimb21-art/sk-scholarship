/**
 * Staff dashboard charts. The numbers come from the server as JSON (#dashboardChartData, written with js_json()); the
 * block is only on the page when there is a program to chart.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('dashboardChartData');
    if (!window.SKCharts || !el) return;
    var d = JSON.parse(el.textContent);
    SKCharts.bar('dashStatus', d.status.labels, [{ label: 'Applications', data: d.status.values }], { horizontal: true, integer: true, xTitle: 'Applications' });
    SKCharts.bar('dashBudget', d.budget.labels, [{ label: 'Amount', data: d.budget.values }],
      { horizontal: true, money: true, xTitle: 'Pesos' });
    SKCharts.bar('dashMonths', d.months.labels, [{ label: 'Released', data: d.months.values }], { money: true, xTitle: 'Release month', yTitle: 'Pesos' });
    SKCharts.bar('dashGwa', d.gwa.labels, [{ label: 'Scholars', data: d.gwa.values }],
      { integer: true, xTitle: 'GWA band', yTitle: 'Active scholars' });
  });
})();
