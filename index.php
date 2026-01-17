<?php
include './include/connect.php';
include './include/permissions.php';
checkAccess('thongke');
?>

<!DOCTYPE html>
<html lang="vi" class="scroll-smooth">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard - Báo cáo Thống kê Kho</title>

    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#2563EB',
                        secondary: '#64748B',
                        success: '#10B981',
                        warning: '#F59E0B',
                        danger: '#EF4444',
                        'background-light': '#F1F5F9',
                        'background-dark': '#0F172A',
                        'surface-light': '#FFFFFF',
                        'surface-dark': '#1E293B',
                        'border-light': '#E2E8F0',
                        'border-dark': '#334155',
                    },
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    borderRadius: { DEFAULT: '0.5rem' },
                },
            },
        };
    </script>

    <style>
        body { font-family: 'Inter', sans-serif; }
        .sidebar-item-active {
            background-color: rgba(37,99,235,0.1);
            color: #2563EB;
        }

        /* Drag and drop styles */
        .chart-dragging {
            opacity: 0.8;
            transform: rotate(2deg);
            z-index: 1000;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .chart-container {
            transition: all 0.3s ease;
        }

        .drag-handle {
            user-select: none;
        }

        .dark .sidebar-item-active {
            background-color: rgba(37,99,235,0.2);
            color: #60A5FA;
        }
    </style>
</head>

<body class="bg-background-light dark:bg-background-dark text-slate-800 dark:text-slate-100 min-h-screen transition-colors duration-200">

    <?php include './include/sidebar.php'; ?>

    <div class="flex flex-col flex-1 overflow-hidden">
        <?php include './include/header.php'; ?>

        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-background-light dark:bg-background-dark p-6 transition-colors duration-200">

            <!-- Header & Controls -->
            <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between mb-6 gap-4">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-slate-800 dark:text-white">Báo cáo Thống kê Kho</h1>
                    <p class="text-slate-500 dark:text-slate-400 mt-1">Tổng quan nhập - xuất - tồn kho theo loại kho</p>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center bg-white dark:bg-surface-dark p-3 rounded-xl border border-border-light dark:border-border-dark shadow-sm">
                    <div class="flex items-center gap-2">
                        <div class="bg-slate-100 dark:bg-slate-800 p-1 rounded-lg inline-flex" id="periodButtons">
                            <button class="period-button px-4 py-2 text-sm font-medium rounded-md hover:bg-white dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-all" data-period="day" onclick="setPeriod('day')">Ngày</button>
                            <button class="period-button px-4 py-2 text-sm font-medium rounded-md hover:bg-white dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-all" data-period="week" onclick="setPeriod('week')">Tuần</button>
                            <button class="period-button px-4 py-2 text-sm font-medium rounded-md bg-white dark:bg-slate-700 text-primary dark:text-white shadow-md" data-period="month" onclick="setPeriod('month')">Tháng</button>
                            <button class="period-button px-4 py-2 text-sm font-medium rounded-md hover:bg-white dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-all" data-period="quarter" onclick="setPeriod('quarter')">Quý</button>
                            <button class="period-button px-4 py-2 text-sm font-medium rounded-md hover:bg-white dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-all" data-period="year" onclick="setPeriod('year')">Năm</button>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 flex-wrap">
                        
                        <div class="flex items-center gap-2">
                            <input type="date" id="startDateInput" class="h-10 px-3 rounded-lg border border-border-light dark:border-border-dark bg-white dark:bg-slate-800 text-sm text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-primary outline-none" />
                            <span class="text-slate-500 dark:text-slate-400">-</span>
                            <input type="date" id="endDateInput" class="h-10 px-3 rounded-lg border border-border-light dark:border-border-dark bg-white dark:bg-slate-800 text-sm text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-primary outline-none" />
                            <button onclick="applyCustomRange()" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">Áp dụng</button>
                            <button onclick="resetToPeriod()" class="px-3 py-2 border border-border-light dark:border-border-dark rounded-lg text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">Đặt lại</button>
                        </div>
                    </div>

                    <button onclick="exportExcel()" class="flex items-center gap-2 px-5 py-2 bg-primary hover:bg-blue-700 text-white rounded-lg shadow transition-colors whitespace-nowrap">
                        <span class="material-icons-round">download</span>
                        Xuất Excel
                    </button>
                </div>
            </div>

            <!-- Warehouse Tabs -->
            <div class="mb-8 border-b border-border-light dark:border-border-dark overflow-x-auto">
                <nav class="-mb-px flex space-x-8 min-w-max px-1" id="warehouseTabs">
                    <!-- Load động từ JS -->
                </nav>
            </div>

            <!-- Chart & Summary -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <div id="chartContainer" class="lg:col-span-2 bg-surface-light dark:bg-surface-dark p-6 rounded-xl shadow-sm border border-border-light dark:border-border-dark cursor-move relative group">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <div class="drag-handle opacity-100 group-hover:opacity-100 transition-opacity cursor-move p-1 rounded hover:bg-slate-100 dark:hover:bg-slate-700" title="Kéo để di chuyển biểu đồ">
                                <span class="material-icons-round text-slate-400 text-lg" style="font-size: 18px;">drag_indicator</span>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-slate-800 dark:text-white">Biến động Tổng Nhập - Xuất</h3>
                                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1" id="chartPeriodTitle">Dữ liệu theo tháng</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 text-sm">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-success"></span>
                                <span class="text-slate-600 dark:text-slate-300">Nhập</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-primary"></span>
                                <span class="text-slate-600 dark:text-slate-300">Xuất</span>
                            </div>
                            <button id="resetChartPosition" class="opacity-0 group-hover:opacity-100 transition-opacity p-1 rounded hover:bg-slate-100 dark:hover:bg-slate-700" title="Đặt lại vị trí">
                                <span class="material-icons-round text-slate-400 text-lg">replay</span>
                            </button>
                        </div>
                    </div>
                    <div class="h-80 w-full">
                        <canvas id="fluctuationChart"></canvas>
                    </div>
                </div>

                <div class="bg-surface-light dark:bg-surface-dark p-6 rounded-xl shadow-sm border border-border-light dark:border-border-dark flex flex-col">
                    <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-6">Tổng hợp kỳ báo cáo</h3>
                    <div class="space-y-5 flex-1">
                        <div class="p-5 rounded-xl bg-blue-50/50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800/30">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Tổng lượng Nhập</p>
                                    <p class="text-3xl font-bold text-blue-700 dark:text-blue-400 mt-2" id="summary-import">—</p>
                                </div>
                                <span class="material-icons-round text-4xl text-blue-500 opacity-80">input</span>
                            </div>
                        </div>

                        <div class="p-5 rounded-xl bg-amber-50/50 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-800/30">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Tổng lượng Xuất</p>
                                    <p class="text-3xl font-bold text-amber-700 dark:text-amber-400 mt-2" id="summary-export">—</p>
                                </div>
                                <span class="material-icons-round text-4xl text-amber-600 opacity-80">output</span>
                            </div>
                        </div>

                        <div class="p-5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Số phiếu giao dịch</p>
                                    <p class="text-3xl font-bold text-slate-700 dark:text-slate-200 mt-2" id="summary-count">—</p>
                                </div>
                                <span class="material-icons-round text-4xl text-slate-500 opacity-80">receipt_long</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bảng dữ liệu -->
            <div class="bg-surface-light dark:bg-surface-dark rounded-xl shadow-sm border border-border-light dark:border-border-dark overflow-hidden">
                <div class="p-6 border-b border-border-light dark:border-border-dark flex flex-col md:flex-row justify-between md:items-center gap-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800 dark:text-white">Bảng cân đối Nhập - Xuất - Tồn</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Chi tiết tồn kho theo mặt hàng và kho</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="relative w-full sm:w-64">
                            <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
                            <input type="text" id="searchInput" class="w-full pl-10 pr-4 py-2 text-sm bg-slate-50 dark:bg-slate-800 border border-border-light dark:border-border-dark rounded-lg focus:ring-2 focus:ring-primary outline-none" placeholder="Tìm tên hàng, mã hàng..." />
                        </div>
                        <button class="p-2 bg-white dark:bg-surface-dark border border-border-light dark:border-border-dark rounded-lg text-slate-500 hover:text-primary hover:border-primary transition-colors" title="Bộ lọc nâng cao">
                            <span class="material-icons-round">filter_list</span>
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-800/60 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                <th class="px-6 py-4 sticky left-0 bg-slate-50 dark:bg-slate-800/60 z-10 shadow-[2px_0_8px_-4px_rgba(0,0,0,0.1)]">Mặt hàng</th>
                                <th class="px-6 py-4">Kho</th>
                                <th class="px-6 py-4 text-center">ĐVT</th>
                                <th class="px-6 py-4 text-right bg-blue-50/40 dark:bg-blue-900/20">Tồn đầu</th>
                                <th class="px-6 py-4 text-right bg-green-50/40 dark:bg-green-900/20">Tổng Nhập</th>
                                <th class="px-6 py-4 text-right bg-amber-50/40 dark:bg-amber-900/20">Tổng Xuất</th>
                                <th class="px-6 py-4 text-right font-bold bg-slate-100/60 dark:bg-slate-700/30 border-l border-border-light dark:border-border-dark">Tồn cuối</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-light dark:divide-border-dark text-sm" id="table-body">
                            <tr>
                                <td colspan="7" class="text-center py-12 text-slate-500 dark:text-slate-400">
                                    <div class="flex flex-col items-center gap-3">
                                        <span class="material-icons-round text-5xl animate-spin-slow">hourglass_empty</span>
                                        <p>Đang tải dữ liệu...</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 border-t border-border-light dark:border-border-dark flex flex-col sm:flex-row justify-between items-center gap-4 text-sm">
                    <span class="text-slate-500 dark:text-slate-400">Hiển thị tất cả kết quả</span>
                    <div class="flex gap-1">
                        <button class="px-4 py-2 border border-border-light dark:border-border-dark rounded hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-50" disabled>Trước</button>
                        <button class="px-4 py-2 bg-primary text-white rounded">1</button>
                        <button class="px-4 py-2 border border-border-light dark:border-border-dark rounded hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-50" disabled>Sau</button>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- JavaScript -->
    <script>
        // Dark mode
        if (localStorage.getItem('color-theme') === 'dark' || 
            (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }

        let currentWarehouse = localStorage.getItem('currentWarehouse') || '';
        let currentPeriod = localStorage.getItem('currentPeriod') || 'month';
        let fluctuationChart = null;
        let customStartDate = null;
        let customEndDate = null;

        const chartFontColor = () => document.documentElement.classList.contains('dark') ? '#94a3b8' : '#64748b';
        const chartGridColor = () => document.documentElement.classList.contains('dark') ? '#334155' : '#e2e8f0';

        async function loadWarehouseTypes() {
            try {
                const res = await fetch('get_stats_data.php?action=warehouses');
                const data = await res.json();

                if (data.warehouses?.length > 0) {
                    renderWarehouseTabs(data.warehouses);
                    currentWarehouse = data.warehouses[0].ma_loai_kho;
                    localStorage.setItem('currentWarehouse', currentWarehouse);
                    await loadAndRenderData(currentWarehouse, currentPeriod);
                }
            } catch (err) {
                console.error('Lỗi tải kho:', err);
                document.getElementById('warehouseTabs').innerHTML = '<div class="py-4 text-red-500">Không tải được danh sách kho</div>';
            }
        }

        function getWarehouseIcon(type) {
            const icons = {
                'L001': 'category',
                'L002': 'local_gas_station',
                'L003': 'settings',
                'L004': 'inventory'
            };
            return icons[type] || 'warehouse';
        }

        function renderWarehouseTabs(warehouses) {
            const container = document.getElementById('warehouseTabs');
            container.innerHTML = '';
            warehouses.forEach((w, i) => {
                const btn = document.createElement('button');
                btn.className = `warehouse-tab group inline-flex items-center py-4 px-6 border-b-2 font-medium text-sm transition-colors ${
                    i === 0 ? 'border-primary text-primary' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300'
                }`;
                btn.dataset.tab = w.ma_loai_kho;
                btn.innerHTML = `<span class="material-icons-round mr-2">${getWarehouseIcon(w.ma_loai_kho)}</span>${w.ten_loai_kho}`;
                container.appendChild(btn);
            });
        }

        async function loadAndRenderData(warehouse, period) {
            try {
                const params = new URLSearchParams({ warehouse: warehouse, period: period });
                if (customStartDate && customEndDate) {
                    params.set('start_date', customStartDate);
                    params.set('end_date', customEndDate);
                }
                const res = await fetch(`get_stats_data.php?${params.toString()}`);
                const data = await res.json();

                if (data.error) throw new Error(data.error);

                updateDateRange(data.start_date, data.end_date);
                updateChartTitle(period, data.start_date, data.end_date);

                initChart(data.chart || { labels: [], import: [], export: [] });
                renderSummary(data.summary || {});
                renderTable(data.items || []);

            } catch (err) {
                console.error('Lỗi:', err);
                const tbody = document.getElementById('table-body');
                if (tbody) {
                    tbody.innerHTML = `
                        <tr><td colspan="7" class="text-center py-12 text-red-500">
                            Lỗi tải dữ liệu: ${err.message || 'Không kết nối được server'}
                        </td></tr>`;
                }
            }
        }

        function initChart(chartData) {
            const chartEl = document.getElementById('fluctuationChart');
            if (!chartEl) return; // Exit if element doesn't exist (SPA navigation)

            const ctx = chartEl.getContext('2d');
            if (fluctuationChart) fluctuationChart.destroy();

            fluctuationChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels || ['Không có dữ liệu'],
                    datasets: [
                        { label: 'Tổng Nhập', data: chartData.import || [], borderColor: '#10B981', backgroundColor: 'rgba(16,185,129,0.12)', tension: 0.3, fill: true },
                        { label: 'Tổng Xuất', data: chartData.export || [], borderColor: '#2563EB', backgroundColor: 'rgba(37,99,235,0.12)', tension: 0.3, fill: true }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: ctx => `${ctx.dataset.label}: ${new Intl.NumberFormat('vi-VN').format(ctx.parsed.y)}`
                            }
                        }
                    },
                    scales: {
                        y: { beginAtZero: true, grid: { color: chartGridColor(), borderDash: [6,6] }, ticks: { color: chartFontColor() } },
                        x: { grid: { display: false }, ticks: { color: chartFontColor() } }
                    }
                }
            });
        }

        function renderSummary(summary) {
            const importEl = document.getElementById('summary-import');
            const exportEl = document.getElementById('summary-export');
            const countEl = document.getElementById('summary-count');

            if (importEl) importEl.textContent = summary.import || '—';
            if (exportEl) exportEl.textContent = summary.export || '—';
            if (countEl) countEl.textContent = summary.count || '—';
        }

        function renderTable(items) {
            const tbody = document.getElementById('table-body');
            if (!tbody) return; // Exit if element doesn't exist (SPA navigation)

            tbody.innerHTML = '';

            if (!items?.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-12 text-slate-500">Không có dữ liệu trong kỳ</td></tr>';
                return;
            }

            items.forEach(item => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors';
                const inClass = item.in > 0 ? 'text-success bg-green-50/30 dark:bg-green-900/10' : 'text-slate-400';
                const outClass = item.out > 0 ? 'text-warning bg-amber-50/30 dark:bg-amber-900/10' : 'text-slate-400';

                tr.innerHTML = `
                    <td class="px-6 py-4 sticky left-0 bg-surface-light dark:bg-surface-dark z-10 shadow-[2px_0_8px_-4px_rgba(0,0,0,0.08)]">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-500">
                                <span class="material-icons-round">${item.icon || 'inventory'}</span>
                            </div>
                            <div>
                                <div class="font-medium text-slate-800 dark:text-white">${item.name || '—'}</div>
                                <div class="text-xs text-slate-500">${item.code || '—'}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-slate-600 dark:text-slate-300">${item.store || '—'}</td>
                    <td class="px-6 py-4 text-center text-slate-600 dark:text-slate-300">${item.unit || '—'}</td>
                    <td class="px-6 py-4 text-right font-medium text-blue-600 dark:text-blue-400 bg-blue-50/20 dark:bg-blue-900/10">${item.start?.toLocaleString('vi-VN') || '—'}</td>
                    <td class="px-6 py-4 text-right font-medium ${inClass}">${item.in > 0 ? '+' : ''}${item.in?.toLocaleString('vi-VN') || '0'}</td>
                    <td class="px-6 py-4 text-right font-medium ${outClass}">${item.out > 0 ? '-' : ''}${item.out?.toLocaleString('vi-VN') || '0'}</td>
                    <td class="px-6 py-4 text-right font-bold text-primary dark:text-blue-400 bg-slate-50/30 dark:bg-slate-800/20 border-l border-border-light dark:border-border-dark">${item.end?.toLocaleString('vi-VN') || '—'}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        // Cập nhật khoảng thời gian (dateRange) - chính xác cho 12/01/2026
        function updateDateRange(start, end) {
            const format = d => {
                if (!d) return '—';
                const dateObj = new Date(d);
                if (isNaN(dateObj)) return d;
                return dateObj.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
            };

            // Update dateRange element if exists (for future use)
            const dateRangeEl = document.getElementById('dateRange');
            if (dateRangeEl) {
                dateRangeEl.textContent = `${format(start)} - ${format(end || start)}`;
            }

            // Update input values
            const startInput = document.getElementById('startDateInput');
            const endInput = document.getElementById('endDateInput');
            if (startInput && !customStartDate) startInput.value = start || '';
            if (endInput && !customEndDate) endInput.value = end || '';
        }

        // Cập nhật tiêu đề biểu đồ
        function updateChartTitle(period, startDate = null, endDate = null) {
            let title = 'Dữ liệu theo ';
            switch (period) {
                case 'day':    title += 'ngày'; break;
                case 'week':   title += 'tuần'; break;
                case 'month':  title += 'tháng'; break;
                case 'quarter': title += 'quý'; break;
                case 'year':   title += 'năm'; break;
                case 'custom': 
                    if (startDate && endDate) {
                        const format = d => {
                            if (!d) return '';
                            const dateObj = new Date(d);
                            if (isNaN(dateObj)) return d;
                            return dateObj.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
                        };
                        title = `Dữ liệu từ ${format(startDate)} đến ${format(endDate)}`;
                    } else {
                        title = 'Dữ liệu theo khoảng ngày';
                    }
                    break;
                default:       title += 'kỳ';
            }
            const titleEl = document.getElementById('chartPeriodTitle');
            if (titleEl) titleEl.textContent = title;
        }

        function setPeriod(period) {
            currentPeriod = period;
            localStorage.setItem('currentPeriod', currentPeriod);
            customStartDate = null;
            customEndDate = null;
            const startInput = document.getElementById('startDateInput');
            const endInput = document.getElementById('endDateInput');
            if (startInput) startInput.value = '';
            if (endInput) endInput.value = '';
            document.querySelectorAll('.period-button').forEach(btn => {
                btn.classList.toggle('bg-white', btn.dataset.period === period);
                btn.classList.toggle('dark:bg-slate-700', btn.dataset.period === period);
                btn.classList.toggle('text-primary', btn.dataset.period === period);
                btn.classList.toggle('dark:text-white', btn.dataset.period === period);
                btn.classList.toggle('shadow-md', btn.dataset.period === period);
            });
            loadAndRenderData(currentWarehouse, period);
        }

        function applyCustomRange() {
            const startInput = document.getElementById('startDateInput');
            const endInput = document.getElementById('endDateInput');
            if (!startInput || !endInput) return;

            const startVal = startInput.value;
            const endVal = endInput.value;

            if (!startVal || !endVal) {
                alert('Vui lòng chọn đầy đủ ngày bắt đầu và ngày kết thúc');
                return;
            }

            if (new Date(startVal) > new Date(endVal)) {
                alert('Ngày bắt đầu phải nhỏ hơn hoặc bằng ngày kết thúc');
                return;
            }

            customStartDate = startVal;
            customEndDate = endVal;
            currentPeriod = 'custom';
            localStorage.setItem('currentPeriod', currentPeriod);

            document.querySelectorAll('.period-button').forEach(btn => {
                btn.classList.remove('bg-white', 'dark:bg-slate-700', 'text-primary', 'dark:text-white', 'shadow-md');
            });

            updateChartTitle('custom', startVal, endVal);
            loadAndRenderData(currentWarehouse, 'custom');
        }

        function resetToPeriod() {
            customStartDate = null;
            customEndDate = null;
            const startInput = document.getElementById('startDateInput');
            const endInput = document.getElementById('endDateInput');
            if (startInput) startInput.value = '';
            if (endInput) endInput.value = '';
            setPeriod('month');
        }

        // Khởi tạo và event listeners
        document.addEventListener('DOMContentLoaded', () => {
            initializeDashboard();

            const warehouseTabs = document.getElementById('warehouseTabs');
            if (warehouseTabs) {
                warehouseTabs.addEventListener('click', e => {
                    const tab = e.target.closest('.warehouse-tab');
                    if (!tab) return;
                    document.querySelectorAll('.warehouse-tab').forEach(t => {
                        t.classList.remove('border-primary', 'text-primary');
                        t.classList.add('border-transparent', 'text-slate-500', 'hover:text-slate-700', 'hover:border-slate-300', 'dark:text-slate-400', 'dark:hover:text-slate-300');
                    });
                    tab.classList.add('border-primary', 'text-primary');
                    tab.classList.remove('border-transparent', 'text-slate-500', 'hover:text-slate-700', 'hover:border-slate-300', 'dark:text-slate-400', 'dark:hover:text-slate-300');

                    currentWarehouse = tab.dataset.tab;
                    localStorage.setItem('currentWarehouse', currentWarehouse);
                    loadAndRenderData(currentWarehouse, currentPeriod);
                });
            }

            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('input', e => {
                    const term = e.target.value.toLowerCase();
                    document.querySelectorAll('#table-body tr').forEach(row => {
                        row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
                    });
                });
            }
        });

        // Function to initialize dashboard
        function initializeDashboard() {
            // Load warehouse types if tabs exist
            const warehouseTabs = document.getElementById('warehouseTabs');
            if (warehouseTabs && warehouseTabs.children.length === 0) {
                loadWarehouseTypes();
            }

            // Initialize chart drag and drop
            initializeChartDragDrop();
        }

        // Global variables for drag and drop
        let isDragging = false;
        let currentX = 0;
        let currentY = 0;
        let initialX = 0;
        let initialY = 0;
        let xOffset = 0;
        let yOffset = 0;

        // Function to initialize chart drag and drop
        function initializeChartDragDrop() {
            const chartContainer = document.getElementById('chartContainer');
            if (!chartContainer) {
                console.log('Chart container not found');
                return;
            }

            console.log('Initializing chart drag and drop');

            // Load saved position from localStorage
            const savedPosition = localStorage.getItem('chartPosition');
            if (savedPosition) {
                try {
                    const pos = JSON.parse(savedPosition);
                    chartContainer.style.position = 'absolute';
                    chartContainer.style.left = pos.x + 'px';
                    chartContainer.style.top = pos.y + 'px';
                    chartContainer.style.zIndex = '10';
                    console.log('Loaded saved position:', pos);
                } catch (e) {
                    console.error('Error parsing saved position:', e);
                    localStorage.removeItem('chartPosition');
                }
            }

            function dragStart(e) {
                if (e.target.closest('.drag-handle')) {
                    initialX = e.clientX - xOffset;
                    initialY = e.clientY - yOffset;
                    isDragging = true;

                    // Make chart absolute positioned if not already
                    if (chartContainer.style.position !== 'absolute') {
                        const rect = chartContainer.getBoundingClientRect();
                        chartContainer.style.position = 'absolute';
                        chartContainer.style.left = rect.left + 'px';
                        chartContainer.style.top = rect.top + 'px';
                        chartContainer.style.width = rect.width + 'px';
                        chartContainer.style.zIndex = '10';
                    }

                    chartContainer.classList.add('chart-dragging');
                    document.body.style.cursor = 'grabbing';
                }
            }

            function dragEnd(e) {
                if (isDragging) {
                    initialX = currentX;
                    initialY = currentY;
                    isDragging = false;

                    chartContainer.classList.remove('chart-dragging');
                    document.body.style.cursor = '';

                    // Save position to localStorage
                    const pos = {
                        x: parseInt(chartContainer.style.left),
                        y: parseInt(chartContainer.style.top)
                    };
                    localStorage.setItem('chartPosition', JSON.stringify(pos));
                }
            }

            function drag(e) {
                if (isDragging) {
                    e.preventDefault();
                    currentX = e.clientX - initialX;
                    currentY = e.clientY - initialY;

                    // Constrain to viewport bounds
                    const rect = chartContainer.getBoundingClientRect();
                    const maxX = window.innerWidth - rect.width;
                    const maxY = window.innerHeight - rect.height;

                    currentX = Math.max(0, Math.min(currentX, maxX));
                    currentY = Math.max(0, Math.min(currentY, maxY));

                    xOffset = currentX;
                    yOffset = currentY;

                    setTranslate(currentX, currentY, chartContainer);
                }
            }

            function setTranslate(xPos, yPos, el) {
                el.style.transform = `translate3d(${xPos}px, ${yPos}px, 0)`;
            }

            function resetChartPosition() {
                chartContainer.style.position = '';
                chartContainer.style.left = '';
                chartContainer.style.top = '';
                chartContainer.style.transform = '';
                chartContainer.style.zIndex = '';
                chartContainer.classList.remove('chart-dragging');
                xOffset = 0;
                yOffset = 0;
                localStorage.removeItem('chartPosition');
            }

            // Reset position button
            const resetBtn = document.getElementById('resetChartPosition');
            if (resetBtn) {
                resetBtn.addEventListener('click', resetChartPosition);
            }

            // Event listeners
            chartContainer.addEventListener('mousedown', dragStart);
            document.addEventListener('mousemove', drag);
            document.addEventListener('mouseup', dragEnd);

            // Touch events for mobile
            chartContainer.addEventListener('touchstart', (e) => {
                if (e.target.closest('.drag-handle')) {
                    const touch = e.touches[0];
                    initialX = touch.clientX - xOffset;
                    initialY = touch.clientY - yOffset;
                    isDragging = true;
                    chartContainer.classList.add('chart-dragging');
                }
            });

            document.addEventListener('touchmove', (e) => {
                if (isDragging) {
                    e.preventDefault();
                    const touch = e.touches[0];
                    currentX = touch.clientX - initialX;
                    currentY = touch.clientY - initialY;

                    // Constrain to viewport bounds
                    const rect = chartContainer.getBoundingClientRect();
                    const maxX = window.innerWidth - rect.width;
                    const maxY = window.innerHeight - rect.height;

                    currentX = Math.max(0, Math.min(currentX, maxX));
                    currentY = Math.max(0, Math.min(currentY, maxY));

                    xOffset = currentX;
                    yOffset = currentY;
                    setTranslate(currentX, currentY, chartContainer);
                }
            });

            document.addEventListener('touchend', dragEnd);
        }


        // Detect when dashboard content is loaded (for SPA navigation)
        // Use MutationObserver to watch for changes
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'childList') {
                    // Check if chart container was added
                    const chartContainer = document.getElementById('chartContainer');
                    if (chartContainer && !chartContainer.hasAttribute('data-initialized')) {
                        chartContainer.setAttribute('data-initialized', 'true');
                        initializeDashboard();
                    }

                    // Check if warehouse tabs were added
                    const warehouseTabs = document.getElementById('warehouseTabs');
                    if (warehouseTabs && warehouseTabs.children.length > 0 && !warehouseTabs.hasAttribute('data-loaded')) {
                        warehouseTabs.setAttribute('data-loaded', 'true');
                        // Load current warehouse data if not already loaded
                        if (currentWarehouse && document.getElementById('chartContainer')) {
                            loadAndRenderData(currentWarehouse, currentPeriod);
                        }
                    }
                }
            });
        });

        // Start observing
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        // Listen for dashboard loaded event (from SPA navigation)
        window.addEventListener('dashboardLoaded', () => {
            console.log('Dashboard loaded via SPA navigation, initializing...');
            setTimeout(() => {
                initializeDashboard();
            }, 100); // Small delay to ensure DOM is ready
        });

        function exportExcel() {
            const params = new URLSearchParams({
                warehouse: currentWarehouse,
                period: currentPeriod
            });

            if (customStartDate && customEndDate) {
                params.set('start_date', customStartDate);
                params.set('end_date', customEndDate);
            }

            const url = `export_excel.php?${params.toString()}`;
            window.open(url, '_blank');
        }
    </script>
    <script src="../include/form-autosave.js"></script>
</body>
</html>