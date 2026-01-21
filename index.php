<?php
include './include/connect.php';
include './include/permissions.php';
checkAccess('thongke');

// Lấy danh sách kho được phép truy cập (sử dụng logic từ get_stats_data.php)
$allowedWarehouses = [];
$units = [];
$regions = [];

try {
    include_once './include/permissions.php';

    $role = trim($_SESSION['role'] ?? '');
    $ma_nd = $_SESSION['MaND'] ?? null;

    if ($role === 'Quản lý kho' && $ma_nd) {
        // Chỉ lấy loại kho được phân quyền
        $stmt = $pdo->prepare("
            SELECT DISTINCT lk.ma_loai_kho, lk.ten_loai_kho
            FROM phan_quyen pq
            JOIN loai_kho lk ON pq.ma_loai_kho = lk.ma_loai_kho
            WHERE pq.ma_nd = ?
            ORDER BY lk.ma_loai_kho
        ");
        $stmt->execute([$ma_nd]);
        $allowedWarehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Admin, Ban giám đốc: thấy tất cả
        $stmt = $pdo->query("SELECT ma_loai_kho, ten_loai_kho FROM loai_kho ORDER BY ma_loai_kho");
        $allowedWarehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Lấy danh sách đơn vị tính từ bảng hang_hoa (tất cả đơn vị duy nhất)
    $units = $pdo->query("SELECT DISTINCT don_vi_tinh FROM hang_hoa WHERE don_vi_tinh IS NOT NULL AND don_vi_tinh != '' ORDER BY don_vi_tinh")->fetchAll(PDO::FETCH_COLUMN);

    // Lấy danh sách vùng miền từ bảng vung_mien
    $regions = $pdo->query("SELECT ma_vung AS ma_vung_mien, ten_vung AS ten_vung_mien FROM vung_mien ORDER BY ma_vung")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $allowedWarehouses = [];
    $units = [];
    $regions = [];
}
?>

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
                    fontFamily: {
                        sans: ['Inter', 'sans-serif']
                    },
                    borderRadius: {
                        DEFAULT: '0.5rem'
                    },
                },
            },
        };
    </script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .sidebar-item-active {
            background-color: rgba(37, 99, 235, 0.1);
            color: #2563EB;
        }

        .dark .sidebar-item-active {
            background-color: rgba(37, 99, 235, 0.2);
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
                    <h1 class="text-2xl md:text-3xl font-bold text-slate-800 dark:text-white">Báo cáo thống kê kho</h1>
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

            <!-- Đẩy div filters xuống dưới với mt-4 (margin-top 1rem) -->
            <div class="flex items-center gap-3 flex-wrap mt-4 mb-4 justify-start"> <!-- Thêm justify-end để các phần tử (bao gồm search) lệch phải -->
                <!-- Lọc theo ĐVT (cải thiện) -->
                <div class="relative w-full sm:w-64">
                    <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">straighten</span>
                    <select id="unitFilter" class="w-full pl-10 pr-4 py-2 text-sm bg-slate-50 dark:bg-slate-800 border border-border-light dark:border-border-dark rounded-lg focus:ring-2 focus:ring-primary outline-none">
                        <option value="">Tất cả ĐVT</option>
                        <?php foreach ($units as $unit): ?>
                            <option value="<?php echo htmlspecialchars($unit); ?>"><?php echo htmlspecialchars($unit); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Lọc theo vùng miền (mới thêm) -->
                <div class="relative w-full sm:w-64">
                    <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">location_on</span>
                    <select id="regionFilter" class="w-full pl-10 pr-4 py-2 text-sm bg-slate-50 dark:bg-slate-800 border border-border-light dark:border-border-dark rounded-lg focus:ring-2 focus:ring-primary outline-none">
                        <option value="">Tất cả vùng miền</option>
                        <?php foreach ($regions as $region): ?>
                            <option value="<?php echo htmlspecialchars($region['ma_vung_mien']); ?>"><?php echo htmlspecialchars($region['ten_vung_mien']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>

            <!-- Chart & Summary -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

                <div id="chartContainer" class="lg:col-span-2 bg-surface-light dark:bg-surface-dark p-6 rounded-xl shadow-sm border border-border-light dark:border-border-dark relative">
                    <div class="flex items-center justify-between mb-6">

                        <div>
                            <h3 class="text-lg font-bold text-slate-800 dark:text-white">Biến động tổng nhập - xuất</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1" id="chartPeriodTitle">Dữ liệu theo tháng</p>
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
                        <h3 class="text-lg font-bold text-slate-800 dark:text-white">Bảng cân đối nhập - xuất - tồn</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Chi tiết tồn kho theo mặt hàng và kho</p>
                    </div>
                    <div class="flex items-center gap-3">

                        <div class="relative w-full sm:w-64">
                            <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
                            <input type="text" id="searchInput" class="w-full pl-10 pr-4 py-2 text-sm bg-slate-50 dark:bg-slate-800 border border-border-light dark:border-border-dark rounded-lg focus:ring-2 focus:ring-primary outline-none" placeholder="Tìm tên hàng, mã hàng..." />
                        </div>
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
                                <th class="px-6 py-4 text-right bg-green-50/40 dark:bg-green-900/20">Tổng nhập</th>
                                <th class="px-6 py-4 text-right bg-amber-50/40 dark:bg-amber-900/20">Tổng xuất</th>
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
                    <span class="text-slate-500 dark:text-slate-400" id="paginationInfo">Hiển thị tất cả kết quả</span>
                    <div class="flex gap-1" id="paginationControls">
                        <button class="px-4 py-2 border border-border-light dark:border-border-dark rounded hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-50" id="prevBtn" disabled>Trước</button>
                        <button class="px-4 py-2 bg-primary text-white rounded" id="currentPageBtn">1</button>
                        <button class="px-4 py-2 border border-border-light dark:border-border-dark rounded hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-50" id="nextBtn" disabled>Sau</button>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- JavaScript -->
    <script src="./javascripts/get_data_index.js"></script>
    <script src="../include/form-autosave.js"></script>
</body>

</html>
