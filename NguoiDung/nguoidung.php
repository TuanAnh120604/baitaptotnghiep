<?php
include '../include/connect.php';
include '../include/permissions.php';
checkAccess('nguoidung');

// Query dữ liệu cho các select
try {
    // Lấy danh sách vai trò
    $vai_tro_stmt = $pdo->query("SELECT ma_vai_tro, ten_vai_tro FROM vai_tro ORDER BY ten_vai_tro");
    $vai_tro_list = $vai_tro_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Lấy danh sách loại kho
    $loai_kho_stmt = $pdo->query("SELECT ma_loai_kho, ten_loai_kho FROM loai_kho ORDER BY ten_loai_kho");
    $loai_kho_list = $loai_kho_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Lấy danh sách vùng miền
    $vung_mien_stmt = $pdo->query("SELECT ma_vung, ten_vung FROM vung_mien ORDER BY ten_vung");
    $vung_mien_list = $vung_mien_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Lấy danh sách kho (tách: tất cả kho và kho chưa có thủ kho)
    $kho_stmt = $pdo->query("SELECT ma_kho, ten_kho, dia_chi, ma_nd FROM kho ORDER BY ten_kho");
    $kho_all_list = $kho_stmt->fetchAll(PDO::FETCH_ASSOC);

    $kho_available_list = [];
    foreach ($kho_all_list as $k) {
        // Kho chưa gán thủ kho (ma_nd NULL hoặc rỗng)
        if (empty($k['ma_nd'])) {
            $kho_available_list[] = $k;
        }
    }
} catch (Exception $e) {
    // Xử lý lỗi nếu cần
    $vai_tro_list = [];
    $loai_kho_list = [];
    $vung_mien_list = [];
    $kho_all_list = [];
    $kho_available_list = [];
}

// Phân trang và lọc dữ liệu
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$records_per_page = 10;
$offset = ($current_page - 1) * $records_per_page;

// Xây dựng query với filter
try {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $filter_role = isset($_GET['role']) ? trim($_GET['role']) : 'all';

    // Query đếm tổng số record
    $count_query = "SELECT COUNT(*) as total FROM nguoi_dung nd LEFT JOIN vai_tro vt ON nd.ma_vai_tro = vt.ma_vai_tro WHERE 1=1";

    if (!empty($search)) {
        $count_query .= " AND (nd.ma_nd LIKE :search OR nd.ten_nd LIKE :search)";
    }

    if ($filter_role !== 'all') {
        if ($filter_role === 'quan-ly-kho') {
            $count_query .= " AND nd.ma_vai_tro = 'VT003'";
        } elseif ($filter_role === 'thu-kho') {
            $count_query .= " AND nd.ma_vai_tro = 'VT004'";
        }
    }

    $count_stmt = $pdo->prepare($count_query);
    if (!empty($search)) {
        $search_param = '%' . $search . '%';
        $count_stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
    $total_records = $count_result['total'];

    // Query lấy dữ liệu với thông tin nơi làm việc
    $data_query = "SELECT nd.ma_nd, nd.ten_nd, nd.mat_khau, nd.ma_vai_tro, vt.ten_vai_tro,
                          CASE
                              WHEN nd.ma_vai_tro = 'VT003' THEN CONCAT(COALESCE(lk.ten_loai_kho, ''), ' - ', COALESCE(vm.ten_vung, ''))
                              WHEN nd.ma_vai_tro = 'VT004' THEN COALESCE(k.ten_kho, 'Chưa xác định')
                              ELSE ''
                          END as noi_lam_viec
                   FROM nguoi_dung nd
                   LEFT JOIN vai_tro vt ON nd.ma_vai_tro = vt.ma_vai_tro
                   LEFT JOIN phan_quyen pq ON nd.ma_nd = pq.ma_nd
                   LEFT JOIN loai_kho lk ON pq.ma_loai_kho = lk.ma_loai_kho
                   LEFT JOIN vung_mien vm ON pq.ma_vung = vm.ma_vung
                   LEFT JOIN kho k ON nd.ma_nd = k.ma_nd
                   WHERE 1=1";

    if (!empty($search)) {
        $data_query .= " AND (nd.ma_nd LIKE :search OR nd.ten_nd LIKE :search)";
    }

    if ($filter_role !== 'all') {
        if ($filter_role === 'quan-ly-kho') {
            $data_query .= " AND nd.ma_vai_tro = 'VT003'";
        } elseif ($filter_role === 'thu-kho') {
            $data_query .= " AND nd.ma_vai_tro = 'VT004'";
        }
    }

    $data_query .= " ORDER BY nd.ma_nd";

    $data_stmt = $pdo->prepare($data_query);
    if (!empty($search)) {
        $data_stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
    }
    $data_stmt->execute();
    $nguoi_dung_list = $data_stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_pages = ceil($total_records / $records_per_page);
} catch (Exception $e) {
    $nguoi_dung_list = [];
    $total_records = 0;
    $total_pages = 0;
}

// Phạm vi trang hiển thị
$max_pages_to_show = 5;
$start_page = max(1, $current_page - floor($max_pages_to_show / 2));
$end_page = min($total_pages, $start_page + $max_pages_to_show - 1);
if ($end_page - $start_page < $max_pages_to_show - 1) {
    $start_page = max(1, $end_page - $max_pages_to_show + 1);
}

// Query string cho phân trang (nếu có filter/search từ GET)
$query_params = [];
if (!empty($search)) $query_params['search'] = $search;
if (!empty($filter_role) && $filter_role !== 'all') $query_params['role'] = $filter_role;
$query_string = !empty($query_params) ? '&' . http_build_query($query_params) : '';

?>

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Danh sách Người dùng</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com" rel="preconnect" />
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "#2563EB", // Royal Blue
                        secondary: "#64748B", // Slate
                        success: "#10B981",
                        warning: "#F59E0B",
                        danger: "#EF4444",
                        "background-light": "#F1F5F9",
                        "background-dark": "#0F172A",
                        "surface-light": "#FFFFFF",
                        "surface-dark": "#1E293B",
                        "border-light": "#E2E8F0",
                        "border-dark": "#334155",
                    },
                    fontFamily: {
                        sans: ["Inter", "sans-serif"],
                    },
                    borderRadius: {
                        DEFAULT: "0.5rem",
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
            border-right: 3px solid #2563EB;
        }

        .dark .sidebar-item-active {
            background-color: rgba(37, 99, 235, 0.2);
            color: #60A5FA;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        .material-symbols-outlined.filled {
            font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        #role-fields-manager,
        #role-fields-storekeeper {
            display: none;
        }

        /* Đảm bảo các nút luôn hiển thị */
        #saveUserBtn,
        #closeAddUserModal {
            display: inline-flex !important;
        }

        .filter-btn {
            background-color: white !important;
            color: rgb(71 85 105) !important;
            border: 1px solid transparent !important;
            transition: all 0.3s ease;
        }

        .dark .filter-btn {
            background-color: transparent !important;
            color: rgb(148 163 184) !important;
        }

        .filter-btn-active {
            background-color: rgb(37 99 235) !important;
            color: white !important;
            border-color: rgb(37 99 235) !important;
        }

        .dark .filter-btn-active {
            background-color: rgb(37 99 235) !important;
            color: white !important;
        }
    </style>
</head>

<body
    class="bg-background-light dark:bg-background-dark text-slate-800 dark:text-slate-100 transition-colors duration-200">
        
        <?php
        // Hiển thị modal popup nếu có status từ redirect
        $status = $_GET['status'] ?? '';
        $message = $_GET['message'] ?? '';
        ?>

        <?php if ($status === 'success' && !empty($message)): ?>
        <div id="successModal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-surface-dark rounded-xl shadow-2xl max-w-md w-full p-8 text-center">
                <div class="mb-4 flex justify-center">
                    <span class="material-symbols-outlined text-6xl text-green-500">check_circle</span>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Thành công!</h3>
                <p class="text-gray-600 dark:text-gray-300 mb-6"><?= htmlspecialchars($message) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($status === 'error' && !empty($message)): ?>
        <div id="errorModal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-surface-dark rounded-xl shadow-2xl max-w-md w-full p-8 text-center">
                <div class="mb-4 flex justify-center">
                    <span class="material-symbols-outlined text-6xl text-red-500">error</span>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Lỗi!</h3>
                <p class="text-gray-600 dark:text-gray-300 mb-6"><?= htmlspecialchars($message) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <?php include '../include/sidebar.php'; ?>
        <div class="flex-1 flex flex-col overflow-hidden">
            <?php include '../include/header.php'; ?>
            <main
                class="flex-1 overflow-x-hidden overflow-y-auto bg-background-light dark:bg-background-dark p-6 transition-colors duration-200">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-slate-800 dark:text-white">Danh sách người dùng</h1>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <?php if (canCreate('nguoidung')): ?>
                            <button id="openAddUserModal"
                                class="flex items-center justify-center px-4 py-2 bg-primary hover:bg-blue-700 text-white rounded-lg shadow-sm text-sm font-medium transition-colors">
                                <span class="material-symbols-outlined text-base mr-2">add</span>
                                Thêm Người dùng
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div
                    class="bg-surface-light dark:bg-surface-dark rounded-xl shadow-sm border border-border-light dark:border-border-dark flex flex-col">
                    <div
                        class="p-6 border-b border-border-light dark:border-border-dark flex flex-col md:flex-row justify-between md:items-center gap-4">
                        <div class="flex items-center space-x-2">
                            <button
                                class="filter-btn filter-btn-active px-3 py-1.5 text-sm font-medium rounded-md"
                                data-role="all">
                                Tất cả
                            </button>
                            <button
                                class="filter-btn px-3 py-1.5 text-sm font-medium rounded-md"
                                data-role="quan-ly-kho">
                                Quản lý kho
                            </button>
                            <button
                                class="filter-btn px-3 py-1.5 text-sm font-medium rounded-md"
                                data-role="thu-kho">
                                Thủ kho
                            </button>
                        </div>
                        <div class="flex items-center space-x-3 w-full md:w-auto">
                            <div class="relative w-full md:w-64">
                                <span
                                    class="material-symbols-outlined absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400 text-sm">search</span>
                                <input id="userSearch"
                                    class="pl-9 pr-9 py-2 text-sm bg-slate-50 dark:bg-slate-800 border border-border-light dark:border-border-dark rounded-lg focus:ring-1 focus:ring-primary outline-none text-slate-700 dark:text-slate-200 w-full placeholder-slate-400"
                                    placeholder="Tìm theo tên, mã..." type="text" />
                                <button id="clearSearchBtn"
                                    class="absolute right-2 top-1/2 transform -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 opacity-0 transition-opacity duration-200"
                                    title="Xóa tìm kiếm">
                                    <span class="material-symbols-outlined text-base">close</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr
                                    class="bg-slate-50 dark:bg-slate-800/50 text-xs text-slate-500 dark:text-slate-400 uppercase font-bold tracking-wider">
                                    <th
                                        class="px-6 py-4 sticky left-0 bg-slate-50 dark:bg-slate-800/50 z-10 border-b border-border-light dark:border-border-dark">
                                        Mã Người dùng</th>
                                    <th class="px-6 py-4 border-b border-border-light dark:border-border-dark">Tên Người
                                        dùng</th>
                                    <th class="px-6 py-4 border-b border-border-light dark:border-border-dark">Vai trò
                                    </th>
                                    <th class="px-6 py-4 border-b border-border-light dark:border-border-dark">Nơi làm việc
                                    </th>
                                    <th
                                        class="px-6 py-4 text-right border-b border-border-light dark:border-border-dark">
                                        Thao tác</th>
                                </tr>
                            </thead>
                            <tbody id="userTableBody" class="divide-y divide-border-light dark:divide-border-dark text-sm">
                            </tbody>
                        </table>
                    </div>
                    <div id="paginationInfo"
                        class="bg-surface-light dark:bg-surface-dark px-4 py-3 flex items-center justify-between border-t border-border-light dark:border-border-dark sm:px-6">
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <?php
                                $start_record = ($current_page - 1) * $records_per_page + 1;
                                $end_record = min($current_page * $records_per_page, $total_records);
                                ?>
                                <p id="resultCount" class="text-sm text-text-secondary-light dark:text-text-secondary-dark">
                                    Hiển thị từ <span class="font-medium text-text-light dark:text-text-dark"><?php echo $start_record; ?></span> đến <span class="font-medium text-text-light dark:text-text-dark"><?php echo $end_record; ?></span> trong <span class="font-medium text-text-light dark:text-text-dark"><?php echo $total_records; ?></span> kết quả
                                </p>
                            </div>
                            <?php if ($total_pages > 1): ?>
                                <div id="paginationContainer" class="flex items-center gap-1">
                                    <!-- Nút Previous -->
                                    <button id="prevBtn" onclick="changePage(event, 'prev')" type="button"
                                        class="h-8 w-8 flex items-center justify-center rounded-lg text-[#637588] hover:bg-gray-100 dark:hover:bg-[#243447]">
                                        <span class="material-symbols-outlined text-[20px]">chevron_left</span>
                                    </button>

                                    <!-- Các nút số trang -->
                                    <?php
                                    $max_visible_pages = 5;
                                    $start_page = max(1, $current_page - floor($max_visible_pages / 2));
                                    $end_page = min($total_pages, $start_page + $max_visible_pages - 1);

                                    if ($end_page - $start_page < $max_visible_pages - 1) {
                                        $start_page = max(1, $end_page - $max_visible_pages + 1);
                                    }

                                    if ($start_page > 1): ?>
                                        <button onclick="changePage(event, 1)" type="button"
                                            class="h-8 w-8 flex items-center justify-center rounded-lg text-[#637588] dark:text-[#9ca3af] hover:bg-gray-100 dark:hover:bg-[#243447] text-sm font-medium">
                                            1
                                        </button>
                                        <?php if ($start_page > 2): ?>
                                            <span
                                                class="h-8 w-8 flex items-center justify-center text-[#637588] dark:text-[#9ca3af]">...</span>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                        <button onclick="changePage(event, <?= $i ?>)" data-page="<?= $i ?>" type="button"
                                            class="pagination-btn h-8 w-8 flex items-center justify-center rounded-lg <?= $i == $current_page ? 'bg-primary text-white' : 'text-[#637588] dark:text-[#9ca3af] hover:bg-gray-100 dark:hover:bg-[#243447]' ?> text-sm font-medium">
                                            <?= $i ?>
                                        </button>
                                    <?php endfor; ?>

                                    <?php if ($end_page < $total_pages): ?>
                                        <?php if ($end_page < $total_pages - 1): ?>
                                            <span
                                                class="h-8 w-8 flex items-center justify-center text-[#637588] dark:text-[#9ca3af]">...</span>
                                        <?php endif; ?>
                                        <button onclick="changePage(event, <?= $total_pages ?>)" type="button"
                                            class="h-8 w-8 flex items-center justify-center rounded-lg text-[#637588] dark:text-[#9ca3af] hover:bg-gray-100 dark:hover:bg-[#243447] text-sm font-medium">
                                            <?= $total_pages ?>
                                        </button>
                                    <?php endif; ?>

                                    <!-- Nút Next -->
                                    <button id="nextBtn" onclick="changePage(event, 'next')" type="button"
                                        class="h-8 w-8 flex items-center justify-center rounded-lg text-[#637588] hover:bg-gray-100 dark:hover:bg-[#243447]">
                                        <span class="material-symbols-outlined text-[20px]">chevron_right</span>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
        <div id="addUserModal" class="hidden">
            <div aria-labelledby="modal-title" aria-modal="true" class="fixed inset-0 z-50 overflow-y-auto"
                role="dialog">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div aria-hidden="true" class="fixed inset-0 bg-gray-900/75 dark:bg-gray-900/90 transition-opacity">
                    </div>
                    <span aria-hidden="true" class="hidden sm:inline-block sm:align-middle sm:h-screen">​</span>
                    <div
                        class="relative inline-block align-bottom bg-surface-light dark:bg-surface-dark rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full border border-border-light dark:border-border-dark">
                        <div class="bg-surface-light dark:bg-surface-dark px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div
                                    class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 dark:bg-blue-900/30 sm:mx-0 sm:h-10 sm:w-10">
                                    <span
                                        class="material-symbols-outlined text-primary dark:text-blue-400">person_add</span>
                                </div>
                                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                    <h3 class="text-lg leading-6 font-medium text-slate-900 dark:text-slate-100"
                                        id="modal-title">
                                        Thêm người dùng mới
                                    </h3>
                                    <form id="addUserForm" method="POST" action="add_nguoidung.php"
                                        class="mt-4 space-y-4" onsubmit="return validateAddUserForm()">
                                        <input type="hidden" name="action" value="add_user">
                                        <!-- Hiển thị lỗi chung -->
                                        <div id="addFormErrors" class="hidden p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                                            <div class="flex items-start gap-3">
                                                <span class="material-symbols-outlined text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5">error</span>
                                                <div id="addFormErrorsList" class="text-sm text-red-700 dark:text-red-300"></div>
                                            </div>
                                        </div>
                                        <div>
                                            <label
                                                class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1"
                                                for="username">Tên Người Dùng</label>
                                            <input
                                                class="w-full rounded-md border-slate-300 dark:border-slate-600 shadow-sm focus:border-primary focus:ring-primary dark:bg-slate-700 dark:text-white sm:text-sm px-3 py-2"
                                                id="ten_nd" name="ten_nd" placeholder="Nhập tên người dùng"
                                                type="text" />
                                            <span id="ten_nd_error" class="text-xs text-red-600 dark:text-red-400 mt-1 hidden flex items-center gap-1">
                                                <span class="material-symbols-outlined text-sm">error</span>
                                                <span id="ten_nd_error_text"></span>
                                            </span>
                                        </div>
                                        <div>
                                            <label
                                                class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1"
                                                for="password">Mật khẩu</label>
                                            <input
                                                class="w-full rounded-md border-slate-300 dark:border-slate-600 shadow-sm focus:border-primary focus:ring-primary dark:bg-slate-700 dark:text-white sm:text-sm px-3 py-2"
                                                id="mat_khau" name="mat_khau" placeholder="••••••••" type="password" />
                                            <span id="mat_khau_error" class="text-xs text-red-600 dark:text-red-400 mt-1 hidden flex items-center gap-1">
                                                <span class="material-symbols-outlined text-sm">error</span>
                                                <span id="mat_khau_error_text"></span>
                                            </span>
                                        </div>
                                        <div class="relative">
                                            <label
                                                class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1"
                                                for="role">Chức Vụ</label>
                                            <select
                                                class="w-full rounded-md border-slate-300 dark:border-slate-600 shadow-sm focus:border-primary focus:ring-primary dark:bg-slate-700 dark:text-white sm:text-sm px-3 py-2 transition-all"
                                                id="ma_vai_tro" name="ma_vai_tro">
                                                <option value="">Chọn chức vụ</option>
                                                <?php foreach ($vai_tro_list as $vai_tro): ?>
                                                    <option value="<?php echo htmlspecialchars($vai_tro['ma_vai_tro']); ?>">
                                                        <?php echo htmlspecialchars($vai_tro['ten_vai_tro']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <span id="ma_vai_tro_error" class="text-xs text-red-600 dark:text-red-400 mt-1 hidden flex items-center gap-1">
                                                <span class="material-symbols-outlined text-sm">error</span>
                                                <span id="ma_vai_tro_error_text"></span>
                                            </span>
                                            <div class="mt-4 space-y-4 pt-4 border-t border-border-light dark:border-border-dark"
                                                id="role-fields-manager">
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                    <div>
                                                        <label
                                                            class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1"
                                                            for="warehouse-type">Loại kho</label>
                                                        <select
                                                            class="w-full rounded-md border-slate-300 dark:border-slate-600 shadow-sm focus:border-primary focus:ring-primary dark:bg-slate-700 dark:text-white sm:text-sm px-3 py-2"
                                                            id="ma_loai_kho" name="ma_loai_kho">
                                                            <option value="">Chọn loại kho</option>
                                                            <?php foreach ($loai_kho_list as $loai_kho): ?>
                                                                <option
                                                                    value="<?php echo htmlspecialchars($loai_kho['ma_loai_kho']); ?>">
                                                                    <?php echo htmlspecialchars($loai_kho['ten_loai_kho']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <span id="ma_loai_kho_error" class="text-xs text-red-600 dark:text-red-400 mt-1 hidden flex items-center gap-1">
                                                            <span class="material-symbols-outlined text-sm">error</span>
                                                            <span id="ma_loai_kho_error_text"></span>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <label
                                                            class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1"
                                                            for="region">Vùng miền</label>
                                                        <select
                                                            class="w-full rounded-md border-slate-300 dark:border-slate-600 shadow-sm focus:border-primary focus:ring-primary dark:bg-slate-700 dark:text-white sm:text-sm px-3 py-2"
                                                            id="ma_vung" name="ma_vung">
                                                            <option value="">Chọn vùng miền</option>
                                                            <?php foreach ($vung_mien_list as $vung_mien): ?>
                                                                <option
                                                                    value="<?php echo htmlspecialchars($vung_mien['ma_vung']); ?>">
                                                                    <?php echo htmlspecialchars($vung_mien['ten_vung']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <span id="ma_vung_error" class="text-xs text-red-600 dark:text-red-400 mt-1 hidden flex items-center gap-1">
                                                            <span class="material-symbols-outlined text-sm">error</span>
                                                            <span id="ma_vung_error_text"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mt-4 pt-4 border-t border-border-light dark:border-border-dark"
                                                id="role-fields-storekeeper">
                                                <div>
                                                    <label
                                                        class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1"
                                                        for="warehouse-assigned">Kho</label>
                                                    <select
                                                        class="w-full rounded-md border-slate-300 dark:border-slate-600 shadow-sm focus:border-primary focus:ring-primary dark:bg-slate-700 dark:text-white sm:text-sm px-3 py-2"
                                                        id="ma_kho" name="ma_kho">
                                                        <option value="">Chọn kho</option>
                                                        <?php foreach ($kho_available_list as $kho): ?>
                                                            <option value="<?php echo htmlspecialchars($kho['ma_kho']); ?>">
                                                                <?php echo htmlspecialchars($kho['ten_kho'] . ' - ' . $kho['dia_chi']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <span id="ma_kho_error" class="text-xs text-red-600 dark:text-red-400 mt-1 hidden flex items-center gap-1">
                                                        <span class="material-symbols-outlined text-sm">error</span>
                                                        <span id="ma_kho_error_text"></span>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            class="py-3  flex flex-col sm:flex-row sm:flex-row-reverse border-t border-border-light dark:border-border-dark gap-2 sm:gap-0">
                                            <button
                                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary order-2 sm:order-1 sm:ml-3 sm:w-auto sm:text-sm"
                                                type="submit" form="addUserForm" id="saveUserBtn">
                                                Lưu
                                            </button>
                                            <button
                                                class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-slate-600 shadow-sm px-4 py-2 bg-white dark:bg-slate-700 text-base font-medium text-slate-700 dark:text-slate-200 hover:bg-gray-50 dark:hover:bg-slate-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary order-1 sm:order-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                                                type="button" id="closeAddUserModal">
                                                Hủy
                                            </button>
                                        </div>
                                </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <!-- Edit User Modal -->
    <div id="editUserModal" class="hidden">
        <div aria-labelledby="edit-modal-title" aria-modal="true" class="fixed inset-0 z-50 overflow-y-auto"
            role="dialog">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div aria-hidden="true" class="fixed inset-0 bg-gray-900/75 dark:bg-gray-900/90 transition-opacity">
                </div>
                <span aria-hidden="true" class="hidden sm:inline-block sm:align-middle sm:h-screen"></span>
                <div
                    class="relative inline-block align-bottom bg-surface-light dark:bg-surface-dark rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full border border-border-light dark:border-border-dark">
                    <div
                        class="px-6 py-4 border-b border-border-light dark:border-border-dark flex justify-between items-center bg-slate-50 dark:bg-slate-800/50 rounded-t-xl">
                        <h3 class="text-lg font-semibold text-slate-800 dark:text-white flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">edit_square</span>
                            Chỉnh sửa Người dùng
                        </h3>
                        <button id="closeEditUserModal"
                            class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition-colors">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>
                    <form id="editUserForm" method="POST" action="update_nguoidung.php" class="p-6 space-y-3">
                        <input type="hidden" name="action" value="edit_user">
                        <input type="hidden" name="ma_nd" id="edit_ma_nd">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                                Mã Người Dùng <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <span
                                    class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                    <span class="material-symbols-outlined text-sm">badge</span>
                                </span>
                                <input id="edit_ma_nd_display"
                                    class="pl-9 w-full rounded-lg bg-slate-100 dark:bg-slate-800 border-slate-300 dark:border-slate-600 text-slate-500 dark:text-slate-400 focus:ring-primary focus:border-primary sm:text-sm cursor-not-allowed"
                                    readonly type="text" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                                Tên Người Dùng <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <span
                                    class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                    <span class="material-symbols-outlined text-sm">person</span>
                                </span>
                                <input id="edit_ten_nd" name="ten_nd"
                                    class="pl-9 w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-surface-dark text-slate-800 dark:text-white focus:ring-primary focus:border-primary sm:text-sm"
                                    placeholder="Nhập tên người dùng" type="text" required />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                                Mật khẩu
                            </label>
                            <div class="relative">
                                <span
                                    class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                    <span class="material-symbols-outlined text-sm">lock</span>
                                </span>
                                <input id="edit_mat_khau" name="mat_khau"
                                    class="pl-9 w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-surface-dark text-slate-800 dark:text-white focus:ring-primary focus:border-primary sm:text-sm"
                                    placeholder="Để trống nếu không đổi mật khẩu" type="password" />
                            </div>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Nhập mật khẩu mới chỉ khi bạn
                                muốn thay đổi.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                                Chức Vụ <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <span
                                    class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                    <span class="material-symbols-outlined text-sm">work</span>
                                </span>
                                <select id="edit_ma_vai_tro" name="ma_vai_tro"
                                    class="pl-9 w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-surface-dark text-slate-800 dark:text-white focus:ring-primary focus:border-primary sm:text-sm appearance-none"
                                    required>
                                    <?php foreach ($vai_tro_list as $vai_tro): ?>
                                        <option value="<?php echo htmlspecialchars($vai_tro['ma_vai_tro']); ?>">
                                            <?php echo htmlspecialchars($vai_tro['ten_vai_tro']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <!-- QUẢN LÝ KHO -->
                                <div class="mt-4 space-y-4 pt-4 border-t border-border-light dark:border-border-dark"
                                    id="edit-role-fields-manager" style="display:none">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium mb-1 mt-4">Loại kho</label>
                                            <select name="ma_loai_kho"
                                                class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-surface-dark">
                                                <option value="">Chọn loại kho</option>
                                                <?php foreach ($loai_kho_list as $l): ?>
                                                    <option value="<?= $l['ma_loai_kho'] ?>">
                                                        <?= htmlspecialchars($l['ten_loai_kho']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium mb-1 mt-4">Vùng miền</label>
                                            <select name="ma_vung"
                                                class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-surface-dark">
                                                <option value="">Chọn vùng</option>
                                                <?php foreach ($vung_mien_list as $v): ?>
                                                    <option value="<?= $v['ma_vung'] ?>">
                                                        <?= htmlspecialchars($v['ten_vung']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- THỦ KHO -->
                                <div class="mt-4 pt-4 border-t border-border-light dark:border-border-dark"
                                    id="edit-role-fields-storekeeper" style="display:none">
                                    <label class="block text-sm font-medium mb-1 mt-4">Kho quản lý</label>
                                    <select name="ma_kho"
                                        class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-surface-dark">
                                        <option value="">Chọn kho</option>
                                        <?php foreach ($kho_all_list as $k): ?>
                                            <option value="<?= $k['ma_kho'] ?>">
                                                <?= htmlspecialchars($k['ten_kho']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                            </div>
                        </div>
                        <div class="flex justify-end gap-3 pt-4 border-t border-border-light dark:border-border-dark">
                            <button type="button" id="cancelEditUserModal"
                                class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-600 transition-colors">
                                Hủy
                            </button>
                            <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-primary hover:bg-blue-700 rounded-lg transition-colors">
                                Lưu thay đổi
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Delete User Modal -->
    <div id="deleteUserModal" class="hidden">
        <div aria-labelledby="modal-title" aria-modal="true" class="fixed inset-0 z-50 overflow-y-auto" role="dialog">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div aria-hidden="true" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity">
                </div>
                <span aria-hidden="true" class="hidden sm:inline-block sm:align-middle sm:h-screen"></span>
                <div
                    class="relative inline-block align-bottom bg-surface-light dark:bg-surface-dark rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full border border-border-light dark:border-border-dark">
                    <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div
                                class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900/30 sm:mx-0 sm:h-10 sm:w-10">
                                <span class="material-symbols-outlined text-red-600 dark:text-red-400">warning</span>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-semibold text-slate-900 dark:text-white"
                                    id="delete-modal-title">Xác nhận Xóa Người dùng</h3>
                                <div class="mt-2">
                                    <p class="text-sm text-slate-500 dark:text-slate-400">
                                        Bạn có chắc chắn muốn xóa người dùng <span id="delete_user_name"
                                            class="font-bold text-slate-700 dark:text-slate-200"></span> (<span
                                            id="delete_user_code"
                                            class="font-bold text-slate-700 dark:text-slate-200"></span>) này không?
                                        Hành động này không thể hoàn tác.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <form id="deleteUserForm" method="POST" action="delete_nguoidung.php">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="ma_nd" id="delete_ma_nd">
                        <div
                            class="bg-slate-50 dark:bg-slate-800/50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-border-light dark:border-border-dark">
                            <button type="submit"
                                class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-red-600 hover:bg-red-700 text-base font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm transition-colors">
                                Xóa
                            </button>
                            <button type="button" id="cancelDeleteUserModal"
                                class="mt-3 w-full inline-flex justify-center rounded-lg border border-border-light dark:border-border-dark shadow-sm px-4 py-2 bg-white dark:bg-transparent text-base font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-colors">
                                Hủy
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    </div>
    <script>
        var allUsersData = <?php echo json_encode($nguoi_dung_list); ?>;

        // Check for dark mode preference
        const html = document.documentElement;
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia(
                '(prefers-color-scheme: dark)').matches)) {
            html.classList.add('dark');
        } else {
            html.classList.remove('dark');
        }

        // Xử lý lọc theo vai trò và tìm kiếm - không reload trang
        const filterButtons = document.querySelectorAll('.filter-btn');
        const searchInput = document.getElementById('userSearch');
        const clearSearchBtn = document.getElementById('clearSearchBtn');
        const tableBody = document.querySelector('tbody');
        const paginationControls = document.getElementById('paginationContainer');
        const pageInfo = document.getElementById('resultCount');
        const recordsPerPage = 10;
        let filteredData = [];
        let currentPageNum = 1;
        let searchTimeout;

        function getActiveRole() {
            return document.querySelector('.filter-btn.filter-btn-active')?.dataset.role || 'all';
        }

        function syncUrlParams({ page, role, search }, { reload = false, replace = true } = {}) {
            const params = new URLSearchParams(window.location.search);

            if (page != null) params.set('page', String(page));

            if (role != null) {
                if (role === 'all') params.delete('role');
                else params.set('role', String(role));
            }

            if (search != null) {
                const s = String(search).trim();
                if (s === '') params.delete('search');
                else params.set('search', s);
            }

            const query = params.toString();
            const newUrl = query ? `${window.location.pathname}?${query}` : window.location.pathname;

            if (reload) {
                window.location.href = newUrl;
                return;
            }

            if (replace) history.replaceState(null, '', newUrl);
            else history.pushState(null, '', newUrl);
        }

        function changePage(event, page) {
            if (event) event.preventDefault();
            const totalPages = Math.ceil(filteredData.length / recordsPerPage);
            if (page === 'prev') page = currentPageNum - 1;
            else if (page === 'next') page = currentPageNum + 1;
            else page = parseInt(page);
            if (page < 1 || page > totalPages) return;
            currentPageNum = page;
            renderTable();
            document.querySelector('table')?.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
        }

        // Kiểm tra quyền từ PHP
        const canEditUser = <?php echo canEdit('nguoidung') ? 'true' : 'false'; ?>;
        const canDeleteUser = <?php echo canDelete('nguoidung') ? 'true' : 'false'; ?>;

        // Initialize data and UI
        const urlParams = new URLSearchParams(window.location.search);
        const initialPage = parseInt(urlParams.get('page')) || 1;
        const initialSearch = urlParams.get('search') || '';
        const initialRole = urlParams.get('role') || 'all';

        searchInput.value = initialSearch;
        document.querySelectorAll('.filter-btn').forEach(btn => {
            if (btn.dataset.role === initialRole) {
                btn.classList.add('filter-btn-active');
            } else {
                btn.classList.remove('filter-btn-active');
            }
        });

        filteredData = [...allUsersData];
        currentPageNum = initialPage;
        renderTable();

        // Hàm lọc và phân trang bảng
        function filterAndRenderTable() {
            const selectedRole = document.querySelector('.filter-btn.filter-btn-active')?.dataset.role || 'all';
            const searchTextRaw = searchInput ? searchInput.value.trim() : '';
            const searchText = searchTextRaw.toLowerCase();

            // Reset về trang 1 khi thay đổi filter/search + đồng bộ URL (không reload)
            currentPageNum = 1;
            syncUrlParams({ page: 1, role: selectedRole, search: searchTextRaw }, { reload: false, replace: true });


            // Lọc dữ liệu
            filteredData = allUsersData.filter(user => {
                // Lọc theo vai trò
                if (selectedRole !== 'all') {
                    if (selectedRole === 'quan-ly-kho' && user.ma_vai_tro !== 'VT003') {
                        return false;
                    } else if (selectedRole === 'thu-kho' && user.ma_vai_tro !== 'VT004') {
                        return false;
                    }
                }

                // Lọc theo tìm kiếm
                if (searchText) {
                    const maMatch = (user.ma_nd || '').toLowerCase().includes(searchText);
                    const tenMatch = (user.ten_nd || '').toLowerCase().includes(searchText);
                    if (!maMatch && !tenMatch) {
                        return false;
                    }
                }

                return true;
            });

            renderTable();
        }

        // Hàm xác định màu sắc badge vai trò
        function getBadgeClass(ma_vai_tro) {
            switch (ma_vai_tro) {
                case 'VT001': // Admin
                    return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300 border-red-200 dark:border-red-800';
                case 'VT002': // Ban giám đốc
                    return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300 border-blue-200 dark:border-blue-800';
                case 'VT003': // Quản lý kho
                    return 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300 border-purple-200 dark:border-purple-800';
                case 'VT004': // Thủ kho
                    return 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-600';
                default:
                    return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-600';
            }
        }

        // Hàm escape HTML để tránh XSS
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Hàm render bảng dựa trên trang hiện tại
        function renderTable() {
            const totalPages = Math.ceil(filteredData.length / recordsPerPage);

            // Clamp currentPageNum hợp lệ (tránh URL page vượt quá totalPages)
            if (totalPages <= 0) {
                currentPageNum = 1;
            } else if (currentPageNum > totalPages) {
                currentPageNum = totalPages;
                syncUrlParams({ page: currentPageNum }, { reload: false, replace: true });
            } else if (currentPageNum < 1) {
                currentPageNum = 1;
                syncUrlParams({ page: 1 }, { reload: false, replace: true });
            }
            const offset = (currentPageNum - 1) * recordsPerPage;
            const pageData = filteredData.slice(offset, offset + recordsPerPage);

            // Render các hàng dữ liệu
            if (pageData.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="5" class="px-6 py-8 text-center text-slate-500 dark:text-slate-400">Không có dữ liệu người dùng</td></tr>';
            } else {
                tableBody.innerHTML = pageData.map(user => {
                    const badgeClass = getBadgeClass(user.ma_vai_tro || '');
                    const tenVaiTro = escapeHtml(user.ten_vai_tro || 'Chưa xác định');
                    const maNd = escapeHtml(user.ma_nd || '');
                    const tenNd = escapeHtml(user.ten_nd || '');
                    const noiLamViec = escapeHtml(user.noi_lam_viec || '');
                    const tenNdEscaped = tenNd.replace(/'/g, "\\'");
                    const maVaiTro = escapeHtml(user.ma_vai_tro || '');

                    let actionButtons = '';
                    if (canEditUser) {
                        actionButtons += `<button onclick="openEditModal('${maNd}', '${tenNdEscaped}', '${maVaiTro}')" class="p-1.5 text-slate-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded transition-colors" title="Sửa"><span class="material-symbols-outlined text-lg">edit</span></button>`;
                    }
                    if (canDeleteUser) {
                        actionButtons += `<button onclick="openDeleteModal('${maNd}', '${tenNdEscaped}')" class="p-1.5 text-slate-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded transition-colors" title="Xóa"><span class="material-symbols-outlined text-lg">delete</span></button>`;
                    }

                    return `
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                        <td class="px-6 py-4 font-medium text-slate-700 dark:text-slate-300">${maNd}</td>
                        <td class="px-6 py-4 font-medium text-slate-800 dark:text-white">${tenNd}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${badgeClass}">${tenVaiTro}</span>
                        </td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-300">${noiLamViec}</td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end space-x-2">${actionButtons}</div>
                        </td>
                    </tr>
                `;
                }).join('');
            }

            // Cập nhật pagination info
            const startRecord = filteredData.length > 0 ? (currentPageNum - 1) * recordsPerPage + 1 : 0;
            const endRecord = Math.min(currentPageNum * recordsPerPage, filteredData.length);

            const resultCountEl = document.getElementById('resultCount');
            if (resultCountEl) {
                resultCountEl.textContent = `Hiển thị ${startRecord}-${endRecord} trên ${filteredData.length} người dùng`;
            }

            // Disable/enable prev/next buttons (có thể không tồn tại nếu chỉ có 1 trang)
            const prevBtnEl = document.getElementById('prevBtn');
            if (prevBtnEl) prevBtnEl.disabled = currentPageNum <= 1;

            const nextBtnEl = document.getElementById('nextBtn');
            if (nextBtnEl) nextBtnEl.disabled = currentPageNum >= totalPages;

            // Cập nhật nút phân trang (nếu có)
            updatePaginationButtons(totalPages);
        }

        // Hàm cập nhật nút phân trang
        function updatePaginationButtons(totalPages) {
            const paginationBtns = paginationControls?.querySelectorAll('.pagination-btn');
            if (!paginationBtns) return;

            paginationBtns.forEach(btn => {
                const page = parseInt(btn.dataset.page);
                btn.classList.remove('bg-primary', 'text-white', 'cursor-not-allowed', 'text-slate-500', 'dark:text-slate-400');
                btn.classList.add('border', 'border-border-light', 'dark:border-border-dark', 'text-slate-600', 'dark:text-slate-300');
                btn.disabled = false;

                // Disable nút trang vượt quá totalPages (khi đang lọc client-side)
                if (page > totalPages) {
                    btn.disabled = true;
                    btn.classList.add('cursor-not-allowed', 'text-slate-400', 'dark:text-slate-500');
                    return;
                }

                if (page === currentPageNum) {
                    btn.classList.remove('border', 'border-border-light', 'dark:border-border-dark', 'text-slate-600', 'dark:text-slate-300');
                    btn.classList.add('bg-primary', 'text-white');
                }
            });
        }



        // Sự kiện click nút lọc
        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                filterButtons.forEach(btn => btn.classList.remove('filter-btn-active'));
                this.classList.add('filter-btn-active');
                filterAndRenderTable();
            });
        });

        // Cập nhật trạng thái nút xóa
        function updateClearButton() {
            if (clearSearchBtn && searchInput) {
                if (searchInput.value.trim() !== '') {
                    clearSearchBtn.classList.remove('opacity-0', 'pointer-events-none');
                    clearSearchBtn.classList.add('opacity-100', 'pointer-events-auto');
                } else {
                    clearSearchBtn.classList.add('opacity-0', 'pointer-events-none');
                    clearSearchBtn.classList.remove('opacity-100', 'pointer-events-auto');
                }
            }
        }

        // Sự kiện tìm kiếm với debounce
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                updateClearButton();
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    filterAndRenderTable();
                }, 300); // Debounce 300ms
            });
        }

        // Cập nhật trạng thái nút xóa khi trang load
        updateClearButton();

        // Xử lý nút xóa tìm kiếm
        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', function() {
                searchInput.value = '';
                updateClearButton();
                filterAndRenderTable();
            });
        }

        // Xử lý submit form thêm người dùng
        const addUserForm = document.getElementById('addUserForm');
        if (addUserForm) {
            addUserForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Validate trước
                if (!validateAddUserForm()) {
                    return false;
                }
                
                const submitBtn = document.getElementById('saveUserBtn');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>';

                // Submit form bằng AJAX
                const formData = new FormData(this);
                
                fetch('add_nguoidung.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    // Kiểm xem response có chứa success hay không
                    if (response.ok) {
                        return response.text();
                    } else {
                        throw new Error('Server error');
                    }
                })
                .then(html => {
                    // Nếu trang chứa "successModal" hoặc có query param success, đó là thành công
                    if (html.includes('status=success')) {
                        // Reset form
                        addUserForm.reset();
                        document.getElementById('addUserModal').classList.add('hidden');
                        document.body.style.overflow = '';
                        
                        // Hiển thị success modal
                        showSuccessNotification('Thêm người dùng thành công!');
                        
                        // Reload table sau 1.5 giây
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        throw new Error('Có lỗi xảy ra');
                    }
                })
                .catch(error => {
                    // Lỗi server - hiển thị trong form
                    const errorContainer = document.getElementById('addFormErrors');
                    const errorsList = document.getElementById('addFormErrorsList');
                    errorsList.innerHTML = `<div>• ${error.message || 'Có lỗi xảy ra, vui lòng thử lại'}</div>`;
                    errorContainer.classList.remove('hidden');
                    
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Lưu';
                });
            });
        }

        // Mở modal Thêm
        const openAddUserModalBtn = document.getElementById('openAddUserModal');
        if (openAddUserModalBtn) {
            openAddUserModalBtn.addEventListener('click', function() {
                document.getElementById('addUserModal').classList.remove('hidden');
                document.body.style.overflow = 'hidden'; // Ngăn scroll nền
                ensureButtonsVisible(); // Đảm bảo các nút hiển thị
            });
        }

        // Đóng modal Thêm (nút Hủy)
        const closeAddUserModalBtn = document.getElementById('closeAddUserModal');
        if (closeAddUserModalBtn) {
            closeAddUserModalBtn.addEventListener('click', function() {
                document.getElementById('addUserModal').classList.add('hidden');
                document.body.style.overflow = ''; // Khôi phục scroll
            });
        }

        // Đóng khi click nền (tùy chọn - rất tiện)
        const addUserModal = document.getElementById('addUserModal');
        if (addUserModal) {
            addUserModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                    document.body.style.overflow = '';
                }
            });
        }

        //mo sua
        function openEditModal(ma_nd, ten_nd, ma_vai_tro) {

            // Gán dữ liệu cơ bản
            document.getElementById('edit_ma_nd').value = ma_nd;
            document.getElementById('edit_ma_nd_display').value = ma_nd;
            document.getElementById('edit_ten_nd').value = ten_nd;

            // Set vai trò
            const roleSelect = document.getElementById('edit_ma_vai_tro');
            roleSelect.value = ma_vai_tro;

            // Ẩn toàn bộ field phụ
            document.getElementById('edit-role-fields-manager').style.display = 'none';
            document.getElementById('edit-role-fields-storekeeper').style.display = 'none';

            // Reset select
            document.querySelector('#edit-role-fields-manager select[name="ma_vung"]').value = '';
            document.querySelector('#edit-role-fields-manager select[name="ma_loai_kho"]').value = '';
            document.querySelector('#edit-role-fields-storekeeper select[name="ma_kho"]').value = '';

            // Gọi AJAX lấy dữ liệu chi tiết
            fetch(`ajax_edit_nguoidung.php?action=get_user&ma_nd=${ma_nd}`)
                .then(res => res.json())
                .then(data => {

                    /* ===== QUẢN LÝ KHO ===== */
                    if (ma_vai_tro === 'VT003' && data.phan_quyen?.length > 0) {

                        document.getElementById('edit-role-fields-manager').style.display = 'block';

                        const pq = data.phan_quyen[0];

                        document.querySelector('#edit-role-fields-manager select[name="ma_vung"]').value = pq.ma_vung;
                        document.querySelector('#edit-role-fields-manager select[name="ma_loai_kho"]').value = pq.ma_loai_kho;
                    }

                    /* ===== THỦ KHO ===== */
                    if (ma_vai_tro === 'VT004' && data.kho?.length > 0) {

                        document.getElementById('edit-role-fields-storekeeper').style.display = 'block';

                        document.querySelector('#edit-role-fields-storekeeper select[name="ma_kho"]').value = data.kho[0].ma_kho;
                    }
                });

            // Mở modal
            document.getElementById('editUserModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }


        // Đóng modal Sửa
        const closeEditUserModalBtn = document.getElementById('closeEditUserModal');
        if (closeEditUserModalBtn) {
            closeEditUserModalBtn.addEventListener('click', function() {
                document.getElementById('editUserModal').classList.add('hidden');
                document.body.style.overflow = '';
            });
        }

        const cancelEditUserModalBtn = document.getElementById('cancelEditUserModal');
        if (cancelEditUserModalBtn) {
            cancelEditUserModalBtn.addEventListener('click', function() {
                document.getElementById('editUserModal').classList.add('hidden');
                document.body.style.overflow = '';
            });
        }

        // Đóng khi click nền
        const editUserModal = document.getElementById('editUserModal');
        if (editUserModal) {
            editUserModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                    document.body.style.overflow = '';
                }
            });
        }

        // Hàm validate form thêm người dùng
        function validateAddUserForm() {
            const tenNd = document.getElementById('ten_nd').value.trim();
            const matKhau = document.getElementById('mat_khau').value.trim();
            const maVaiTro = document.getElementById('ma_vai_tro').value.trim();
            const maLoaiKho = document.getElementById('ma_loai_kho').value.trim();
            const maVung = document.getElementById('ma_vung').value.trim();
            const maKho = document.getElementById('ma_kho').value.trim();
            
            const errorContainer = document.getElementById('addFormErrors');
            const errorsList = document.getElementById('addFormErrorsList');
            const errors = [];
            
            // Xóa tất cả error trước
            document.querySelectorAll('[id$="_error"]').forEach(el => {
                if (el.id !== 'addFormErrors') el.classList.add('hidden');
            });
            errorContainer.classList.add('hidden');
            
            // Validate tên
            if (tenNd === '') {
                errors.push('Tên người dùng không được để trống');
            //     document.getElementById('ten_nd_error').classList.remove('hidden');
            //     document.getElementById('ten_nd_error_text').textContent = 'Tên người dùng không được để trống';
             }
            
            // Validate mật khẩu
            if (matKhau === '') {
                errors.push('Mật khẩu không được để trống');
                // document.getElementById('mat_khau_error').classList.remove('hidden');
                // document.getElementById('mat_khau_error_text').textContent = 'Mật khẩu không được để trống';
            } else if (matKhau.length < 6) {
                errors.push('Mật khẩu phải có ít nhất 6 ký tự');
                // document.getElementById('mat_khau_error').classList.remove('hidden');
                // document.getElementById('mat_khau_error_text').textContent = 'Mật khẩu phải có ít nhất 6 ký tự';
            }
            
            // Validate chức vụ
            if (maVaiTro === '') {
                errors.push('Vui lòng chọn chức vụ');
                // document.getElementById('ma_vai_tro_error').classList.remove('hidden');
                // document.getElementById('ma_vai_tro_error_text').textContent = 'Vui lòng chọn chức vụ';
            }
            
            // Validate theo chức vụ
            if (maVaiTro === 'VT003') {
                if (maLoaiKho === '') {
                    errors.push('Quản lý kho cần chọn loại kho');
                    // document.getElementById('ma_loai_kho_error').classList.remove('hidden');
                    // document.getElementById('ma_loai_kho_error_text').textContent = 'Loại kho không được để trống';
                }
                if (maVung === '') {
                    errors.push('Quản lý kho cần chọn vùng miền');
                    // document.getElementById('ma_vung_error').classList.remove('hidden');
                    // document.getElementById('ma_vung_error_text').textContent = 'Vùng miền không được để trống';
                }
            }
            
            if (maVaiTro === 'VT004') {
                if (maKho === '') {
                    errors.push('Thủ kho cần chọn kho');
                    // document.getElementById('ma_kho_error').classList.remove('hidden');
                    // document.getElementById('ma_kho_error_text').textContent = 'Kho không được để trống';
                }
            }
            
            // Hiển thị danh sách lỗi
            if (errors.length > 0) {
                errorsList.innerHTML = errors.map(error => `<div>• ${error}</div>`).join('');
                errorContainer.classList.remove('hidden');
                return false;
            }
            
            return true;
        }

        // Hàm xử lý hiển thị trường theo vai trò
        function toggleRoleFields(selectElement) {

            const addManager = document.getElementById('role-fields-manager');
            const addStore = document.getElementById('role-fields-storekeeper');
            const editManager = document.getElementById('edit-role-fields-manager');
            const editStore = document.getElementById('edit-role-fields-storekeeper');

            // Ẩn tất cả
            [addManager, addStore, editManager, editStore].forEach(el => {
                if (el) el.style.display = 'none';
            });

            if (selectElement.value === 'VT003') {
                if (addManager) addManager.style.display = 'block';
                if (editManager) editManager.style.display = 'block';
            }

            if (selectElement.value === 'VT004') {
                if (addStore) addStore.style.display = 'block';
                if (editStore) editStore.style.display = 'block';
            }
        }



        // Đảm bảo các nút luôn hiển thị khi modal mở
        function ensureButtonsVisible() {
            const saveBtn = document.getElementById('saveUserBtn');
            const cancelBtn = document.getElementById('closeAddUserModal');

            console.log('Save button:', saveBtn);
            console.log('Cancel button:', cancelBtn);

            if (saveBtn) {
                saveBtn.style.display = 'inline-flex';
                saveBtn.style.visibility = 'visible';
                console.log('Save button display:', saveBtn.style.display);
            }
            if (cancelBtn) {
                cancelBtn.style.display = 'inline-flex';
                cancelBtn.style.visibility = 'visible';
                console.log('Cancel button display:', cancelBtn.style.display);
            }
        }

        // Xử lý hiển thị trường theo vai trò cho form thêm
        const vaiTroSelect = document.getElementById('ma_vai_tro');
        if (vaiTroSelect) {
            vaiTroSelect.addEventListener('change', function() {
                toggleRoleFields(this);
            });
        } else {
            console.error('Không tìm thấy element ma_vai_tro');
        }

        // Xử lý hiển thị trường theo vai trò cho form sửa
        const editVaiTroSelect = document.getElementById('edit_ma_vai_tro');
        if (editVaiTroSelect) {
            editVaiTroSelect.addEventListener('change', function() {
                toggleRoleFields(this);
            });
        }

        // Hàm hiển thị thông báo
        function showNotification(message, type = 'info') {
            // Tạo notification element
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg transition-all duration-300 transform translate-x-full`;

            let bgColor, textColor;
            switch (type) {
                case 'success':
                    bgColor = 'bg-green-500';
                    textColor = 'text-white';
                    break;
                case 'error':
                    bgColor = 'bg-red-500';
                    textColor = 'text-white';
                    break;
                case 'warning':
                    bgColor = 'bg-yellow-500';
                    textColor = 'text-black';
                    break;
                default:
                    bgColor = 'bg-blue-500';
                    textColor = 'text-white';
            }

            notification.className += ` ${bgColor} ${textColor}`;

            notification.innerHTML = `
            <div class="flex items-center">
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 hover:opacity-75">
                    <span class="material-symbols-outlined text-sm">close</span>
                </button>
            </div>
        `;

            document.body.appendChild(notification);

            // Animate in
            setTimeout(() => {
                notification.classList.remove('translate-x-full');
            }, 100);

            // Auto remove after 5 seconds
            setTimeout(() => {
                notification.classList.add('translate-x-full');
                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.remove();
                    }
                }, 300);
            }, 5000);
        }

        // Hàm hiển thị success notification (modal)
        function showSuccessNotification(message) {
            const modal = document.createElement('div');
            modal.id = 'tempSuccessModal';
            modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4';
            modal.innerHTML = `
                <div class="bg-white dark:bg-surface-dark rounded-xl shadow-2xl max-w-md w-full p-8 text-center">
                    <div class="mb-4 flex justify-center">
                        <span class="material-symbols-outlined text-6xl text-green-500">check_circle</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Thành công!</h3>
                    <p class="text-gray-600 dark:text-gray-300 mb-6">${message}</p>
                </div>
            `;
            document.body.appendChild(modal);
            
            // Auto close after 3 seconds
            setTimeout(() => {
                if (modal.parentElement) {
                    modal.remove();
                }
            }, 1000);
        }

        //mo xoa
        function openDeleteModal(ma_nd, ten_nd) {
            document.getElementById('delete_user_name').textContent = ten_nd;
            document.getElementById('delete_user_code').textContent = ma_nd;
            document.getElementById('delete_ma_nd').value = ma_nd;

            document.getElementById('deleteUserModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        // Đóng modal Xóa
        const cancelDeleteUserModalBtn = document.getElementById('cancelDeleteUserModal');
        if (cancelDeleteUserModalBtn) {
            cancelDeleteUserModalBtn.addEventListener('click', function() {
                document.getElementById('deleteUserModal').classList.add('hidden');
                document.body.style.overflow = '';
            });
        }

        // Đóng khi click nền (tùy chọn)
        const deleteUserModal = document.getElementById('deleteUserModal');
        if (deleteUserModal) {
            deleteUserModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                    document.body.style.overflow = '';
                }
            });
        }

        // Tự động đóng success/error modal sau 3 giây
        const successModal = document.getElementById('successModal');
        const errorModal = document.getElementById('errorModal');
        
        if (successModal) {
            setTimeout(function() {
                successModal.classList.add('hidden');
            }, 3000); // 3 giây
        }
        
        if (errorModal) {
            setTimeout(function() {
                errorModal.classList.add('hidden');
            }, 3000); // 3 giây
        }
    </script>

</body>

</html>