import ApexCharts from 'apexcharts';

// Make ApexCharts available globally on the window object
if (typeof window !== 'undefined') {
    window.ApexCharts = window.ApexCharts || ApexCharts;
}
