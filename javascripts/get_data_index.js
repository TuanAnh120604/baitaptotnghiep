    if (localStorage.getItem('color-theme') === 'dark' ||
(!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
document.documentElement.classList.add('dark');
}

let currentWarehouse = localStorage.getItem('currentWarehouse') || '';
let currentPeriod = localStorage.getItem('currentPeriod') || 'month';
let fluctuationChart = null;
let customStartDate = null;
let customEndDate = null;
let currentTablePage = 1;
let tableItemsPerPage = 10;

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
    } else {
        // Không có kho nào được phân quyền
        document.getElementById('warehouseTabs').innerHTML = '<div class="py-4 text-slate-500 dark:text-slate-400">Không có kho nào được phân quyền</div>';
        document.getElementById('chartContainer').innerHTML = '<div class="flex items-center justify-center h-80 text-slate-500 dark:text-slate-400"><span class="material-icons-round text-6xl">lock</span><p class="mt-4">Không có quyền truy cập dữ liệu kho</p></div>';
        document.getElementById('summary-import').textContent = '—';
        document.getElementById('summary-export').textContent = '—';
        document.getElementById('summary-count').textContent = '—';
        document.getElementById('table-body').innerHTML = '<tr><td colspan="7" class="text-center py-12 text-slate-500 dark:text-slate-400">Không có dữ liệu</td></tr>';
    }
} catch (err) {
    console.error('Lỗi tải kho:', err);
    document.getElementById('warehouseTabs').innerHTML = '<div class="py-4 text-red-500">Không tải được danh sách kho</div>';
}
}

// Icon mapping moved to PHP backend - get from API response
function getWarehouseIcon(type) {
    // This function is now redundant since icons come from backend
    // Keeping for backward compatibility, but data comes from API
    const icons = {
        'L001': 'category',
        'L002': 'local_gas_station',
        'L003': 'settings',
        'L004': 'inventory'
    };
    return icons[type] || 'inventory'; // Changed default to 'inventory' to match backend
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

async function loadAndRenderData(warehouse, period, page = 1) {
try {
    currentTablePage = page; // Update current page
    const params = new URLSearchParams({
        warehouse: warehouse,
        period: period,
        page: page,
        limit: tableItemsPerPage
    });
    if (customStartDate && customEndDate) {
        params.set('start_date', customStartDate);
        params.set('end_date', customEndDate);
    }

    // Add unit and region filters
    const unitFilter = document.getElementById('unitFilter')?.value;
    const regionFilter = document.getElementById('regionFilter')?.value;
    if (unitFilter && unitFilter !== '') {
        params.set('unit', unitFilter);
    }
    if (regionFilter && regionFilter !== '') {
        params.set('region', regionFilter);
    }



    const res = await fetch(`get_stats_data.php?${params.toString()}`);
    const data = await res.json();

    if (data.error) throw new Error(data.error);

    updateDateRange(data.start_date, data.end_date);
    updateChartTitle(period, data.start_date, data.end_date);

    initChart(data.chart || {
        labels: [],
        import: [],
        export: []
    });
    renderSummary(data.summary || {});
    renderTable(data.items || [], data.pagination);

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
        datasets: [{
                label: 'Tổng Nhập',
                data: chartData.import || [],
                borderColor: '#10B981',
                backgroundColor: 'rgba(16,185,129,0.12)',
                tension: 0.3,
                fill: true
            },
            {
                label: 'Tổng Xuất',
                data: chartData.export || [],
                borderColor: '#2563EB',
                backgroundColor: 'rgba(37,99,235,0.12)',
                tension: 0.3,
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: ctx => `${ctx.dataset.label}: ${new Intl.NumberFormat('vi-VN').format(ctx.parsed.y)}`
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: chartGridColor(),
                    borderDash: [6, 6]
                },
                ticks: {
                    color: chartFontColor()
                }
            },
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    color: chartFontColor()
                }
            }
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

// Unified table rendering function
function renderTable(items, pagination) {
const tbody = document.getElementById('table-body');
if (!tbody) return; // Exit if element doesn't exist (SPA navigation)

tbody.innerHTML = '';

if (!items?.length) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-12 text-slate-500">Không có dữ liệu trong kỳ</td></tr>';
    updateTablePagination(pagination);
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

updateTablePagination(pagination);
}

function updateTablePagination(pagination) {
const paginationFooter = document.querySelector('.px-6.py-4.border-t.flex');
if (!paginationFooter) return;

const { current_page, total_pages, total_items, limit, has_prev, has_next } = pagination || {};

// Cập nhật thông tin số lượng
const infoSpan = paginationFooter.querySelector('span');
if (infoSpan) {
    if (total_items > 0) {
        const startIdx = (current_page - 1) * limit + 1;
        const endIdx = Math.min(current_page * limit, total_items);
        infoSpan.textContent = `Hiển thị ${startIdx} đến ${endIdx} trong ${total_items} kết quả`;
    } else {
        infoSpan.textContent = 'Không có kết quả';
    }
}

// Cập nhật nút pagination
const navButtons = paginationFooter.querySelector('.flex.gap-1');
if (!navButtons) return;

// Clear existing page buttons, keep prev and next
const prevBtn = document.getElementById('prevBtn');
const nextBtn = document.getElementById('nextBtn');
if (!prevBtn || !nextBtn) return;

// Remove all children except prev and next
Array.from(navButtons.children).forEach(child => {
    if (child !== prevBtn && child !== nextBtn) {
        navButtons.removeChild(child);
    }
});

if (total_pages <= 1) {
    prevBtn.classList.add('opacity-50', 'cursor-not-allowed', 'pointer-events-none');
    nextBtn.classList.add('opacity-50', 'cursor-not-allowed', 'pointer-events-none');
    return;
}

// Nút Trước
if (has_prev) {
    prevBtn.classList.remove('opacity-50', 'cursor-not-allowed', 'pointer-events-none');
} else {
    prevBtn.classList.add('opacity-50', 'cursor-not-allowed', 'pointer-events-none');
}
prevBtn.onclick = (e) => {
    e.preventDefault();
    if (has_prev) {
        loadAndRenderData(currentWarehouse, currentPeriod, current_page - 1);
    }
};

// Tính toán phạm vi trang
const maxVisiblePages = 10;
const startPage = Math.max(1, current_page - Math.floor(maxVisiblePages / 2));
const endPage = Math.min(total_pages, startPage + maxVisiblePages - 1);

// Điều chỉnh startPage nếu endPage quá nhỏ
if (endPage - startPage < maxVisiblePages - 1) {
    const adjustedStart = Math.max(1, endPage - maxVisiblePages + 1);
    // Các nút số trang
    if (adjustedStart > 1) {
        const firstBtn = document.createElement('button');
        firstBtn.className = 'h-8 w-8 flex items-center justify-center rounded-lg text-[#637588] dark:text-[#9ca3af] hover:bg-gray-100 dark:hover:bg-[#243447] text-sm font-medium';
        firstBtn.textContent = '1';
        firstBtn.addEventListener('click', () => loadAndRenderData(currentWarehouse, currentPeriod, 1));
        navButtons.appendChild(firstBtn);

        if (adjustedStart > 2) {
            const ellipsis = document.createElement('span');
            ellipsis.className = 'h-8 w-8 flex items-center justify-center text-[#637588] dark:text-[#9ca3af]';
            ellipsis.textContent = '...';
            navButtons.appendChild(ellipsis);
        }
    }

    for (let i = adjustedStart; i <= endPage; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.className = `h-8 w-8 flex items-center justify-center rounded-lg text-sm font-medium ${
            i === current_page
                ? 'bg-primary text-white'
                : 'text-[#637588] dark:text-[#9ca3af] hover:bg-gray-100 dark:hover:bg-[#243447]'
        }`;
        pageBtn.textContent = i;
        pageBtn.addEventListener('click', () => loadAndRenderData(currentWarehouse, currentPeriod, i));
        navButtons.appendChild(pageBtn);
    }

    if (endPage < total_pages) {
        if (endPage < total_pages - 1) {
            const ellipsis = document.createElement('span');
            ellipsis.className = 'h-8 w-8 flex items-center justify-center text-[#637588] dark:text-[#9ca3af]';
            ellipsis.textContent = '...';
            navButtons.appendChild(ellipsis);
        }

        const lastBtn = document.createElement('button');
        lastBtn.className = 'h-8 w-8 flex items-center justify-center rounded-lg text-[#637588] dark:text-[#9ca3af] hover:bg-gray-100 dark:hover:bg-[#243447] text-sm font-medium';
        lastBtn.textContent = total_pages;
        lastBtn.addEventListener('click', () => loadAndRenderData(currentWarehouse, currentPeriod, total_pages));
        navButtons.appendChild(lastBtn);
    }
}

// Nút Sau
nextBtn.disabled = !has_next;
nextBtn.onclick = () => {
    if (has_next) {
        loadAndRenderData(currentWarehouse, currentPeriod, current_page + 1);
    }
};
}

// Cập nhật khoảng thời gian (dateRange) - chính xác cho 12/01/2026
function updateDateRange(start, end) {
const format = d => {
    if (!d) return '—';
    const dateObj = new Date(d);
    if (isNaN(dateObj)) return d;
    return dateObj.toLocaleDateString('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
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
    case 'day':
        title += 'ngày';
        break;
    case 'week':
        title += 'tuần';
        break;
    case 'month':
        title += 'tháng';
        break;
    case 'quarter':
        title += 'quý';
        break;
    case 'year':
        title += 'năm';
        break;
    case 'custom':
        if (startDate && endDate) {
            const format = d => {
                if (!d) return '';
                const dateObj = new Date(d);
                if (isNaN(dateObj)) return d;
                return dateObj.toLocaleDateString('vi-VN', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                });
            };
            title = `Dữ liệu từ ${format(startDate)} đến ${format(endDate)}`;
        } else {
            title = 'Dữ liệu theo khoảng ngày';
        }
        break;
    default:
        title += 'kỳ';
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

// Unit filter event listener
const unitFilter = document.getElementById('unitFilter');
if (unitFilter) {
    unitFilter.addEventListener('change', () => {
        loadAndRenderData(currentWarehouse, currentPeriod, 1); // Reset to page 1
    });
}

// Region filter event listener
const regionFilter = document.getElementById('regionFilter');
if (regionFilter) {
    regionFilter.addEventListener('change', () => {
        loadAndRenderData(currentWarehouse, currentPeriod, 1); // Reset to page 1
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

// Unit and region filters are populated directly from PHP
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
