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

    // Lấy danh sách kho
    $kho_stmt = $pdo->query("SELECT ma_kho, ten_kho, dia_chi FROM kho ORDER BY ten_kho");
    $kho_list = $kho_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Lấy danh sách người dùng với thông tin vai trò
    $nguoi_dung_stmt = $pdo->query("
        SELECT nd.ma_nd, nd.ten_nd, nd.mat_khau, nd.ma_vai_tro, vt.ten_vai_tro
        FROM nguoi_dung nd
        LEFT JOIN vai_tro vt ON nd.ma_vai_tro = vt.ma_vai_tro
        ORDER BY nd.ma_nd
    ");
    $nguoi_dung_list = $nguoi_dung_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Xử lý lỗi nếu cần
    $vai_tro_list = [];
    $loai_kho_list = [];
    $vung_mien_list = [];
    $kho_list = [];
    $nguoi_dung_list = [];
}

// Phân trang
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$records_per_page = 10;
$offset = ($current_page - 1) * $records_per_page;

// Tính tổng số record (sau khi áp dụng filter nếu có)
$total_records = count($nguoi_dung_list); // tạm thời dùng toàn bộ, sau có thể query COUNT
$total_pages = ceil($total_records / $records_per_page);

// Phạm vi trang hiển thị
$max_pages_to_show = 5;
$start_page = max(1, $current_page - floor($max_pages_to_show / 2));
$end_page = min($total_pages, $start_page + $max_pages_to_show - 1);
if ($end_page - $start_page < $max_pages_to_show - 1) {
    $start_page = max(1, $end_page - $max_pages_to_show + 1);
}

// Query string cho phân trang (nếu có filter/search từ GET)
$query_params = [];
if (!empty($_GET['search'])) $query_params['search'] = $_GET['search'];
if (!empty($_GET['role'])) $query_params['role'] = $_GET['role'];
$query_string = !empty($query_params) ? '&' . http_build_query($query_params) : '';
?>

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Danh sách Người dùng</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com" rel="preconnect" />
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&amp;display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
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
        font-variation-settings: 'FILL'0, 'wght'400, 'GRAD'0, 'opsz'24;
    }

    .material-symbols-outlined.filled {
        font-variation-settings: 'FILL'1, 'wght'400, 'GRAD'0, 'opsz'24;
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

    .filter-btn-active {
        background-color: rgb(37 99 235) !important;
        color: white !important;
        border-color: rgb(37 99 235) !important;
    }

    .dark .filter-btn-active {
        background-color: rgb(37 99 235) !important;
    }
    </style>
</head>

<body
    class="bg-background-light dark:bg-background-dark text-slate-800 dark:text-slate-100 transition-colors duration-200">
    <div class="flex h-screen overflow-hidden">
        <aside
            class="w-64 bg-surface-light dark:bg-surface-dark border-r border-border-light dark:border-border-dark flex-shrink-0 hidden md:flex flex-col transition-colors duration-200">
            <?php include '../include/sidebar.php'; ?>
        </aside>
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
                                class="filter-btn filter-btn-active px-3 py-1.5 text-sm font-medium rounded-md bg-primary text-white"
                                data-role="all">
                                Tất cả
                            </button>
                            <button
                                class="filter-btn px-3 py-1.5 text-sm font-medium rounded-md bg-white dark:bg-transparent text-slate-600 dark:text-slate-400  border border-transparent"
                                data-role="quan-ly-kho">
                                Quản lý kho
                            </button>
                            <button
                                class="filter-btn px-3 py-1.5 text-sm font-medium rounded-md bg-white dark:bg-transparent text-slate-600 dark:text-slate-400 border border-transparent"
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
                                    <th
                                        class="px-6 py-4 text-right border-b border-border-light dark:border-border-dark">
                                        Thao tác</th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody" class="divide-y divide-border-light dark:divide-border-dark text-sm">
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center">
                                        <div class="flex items-center justify-center">
                                            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-primary"></div>
                                            <span class="ml-2 text-slate-500 dark:text-slate-400">Đang tải dữ liệu...</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div id="paginationInfo"
                        class="px-6 py-4 border-t border-border-light dark:border-border-dark flex flex-col sm:flex-row justify-between items-center gap-4">
                        <span class="text-sm text-slate-500 dark:text-slate-400 order-2 sm:order-1">Hiển thị 1-<?php echo count($nguoi_dung_list); ?> trên <?php echo count($nguoi_dung_list); ?>
                            người dùng</span>
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
                                        class="mt-4 space-y-4">
                                        <input type="hidden" name="action" value="add_user">
                                        <div>
                                            <label
                                                class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1"
                                                for="username">Tên Người Dùng</label>
                                            <input
                                                class="w-full rounded-md border-slate-300 dark:border-slate-600 shadow-sm focus:border-primary focus:ring-primary dark:bg-slate-700 dark:text-white sm:text-sm px-3 py-2"
                                                id="ten_nd" name="ten_nd" placeholder="Nhập tên người dùng"
                                                type="text" />
                                        </div>
                                        <div>
                                            <label
                                                class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1"
                                                for="password">Mật khẩu</label>
                                            <input
                                                class="w-full rounded-md border-slate-300 dark:border-slate-600 shadow-sm focus:border-primary focus:ring-primary dark:bg-slate-700 dark:text-white sm:text-sm px-3 py-2"
                                                id="mat_khau" name="mat_khau" placeholder="••••••••" type="password" />
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
                                                        <?php foreach ($kho_list as $kho): ?>
                                                        <option value="<?php echo htmlspecialchars($kho['ma_kho']); ?>">
                                                            <?php echo htmlspecialchars($kho['ten_kho'] . ' - ' . $kho['dia_chi']); ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            class="bg-gray-50 dark:bg-slate-800/50 py-3  flex flex-col sm:flex-row sm:flex-row-reverse border-t border-border-light dark:border-border-dark gap-2 sm:gap-0">
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
                                <span
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400">
                                    <span class="material-symbols-outlined text-sm">expand_more</span>
                                </span>
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
    // Check for dark mode preference
    const html = document.documentElement;
    if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia(
            '(prefers-color-scheme: dark)').matches)) {
        html.classList.add('dark');
    } else {
        html.classList.remove('dark');
    }

    // Lấy tất cả các hàng trong bảng
    const allRows = Array.from(document.querySelectorAll('tbody tr'));

    // Lấy tất cả nút lọc
    const filterButtons = document.querySelectorAll('.filter-btn');

    // Hàm lọc bảng theo role
    function filterTable(role) {
        // Reset style tất cả nút
        filterButtons.forEach(btn => {
            btn.classList.remove('bg-primary', 'text-white', 'active');
            btn.classList.add('bg-white', 'dark:bg-transparent', 'text-slate-600', 'dark:text-slate-400',
                'border-transparent', 'hover:border-slate-200');
        });

        // Active nút được chọn
        const activeBtn = document.querySelector(`[data-role="${role}"]`);
        if (activeBtn) {
            activeBtn.classList.remove('bg-white', 'dark:bg-transparent', 'text-slate-600', 'dark:text-slate-400',
                'border-transparent', 'hover:border-slate-200');
            activeBtn.classList.add('bg-primary', 'text-white', 'active');
        }

        // Lọc các hàng
        allRows.forEach(row => {
            const roleCell = row.querySelector('td:nth-child(3) span'); // cột Chức vụ
            if (!roleCell) return;

            const userRole = roleCell.textContent.trim().toLowerCase();

            if (role === 'all') {
                row.style.display = '';
            } else if (role === 'quan-ly-kho') {
                row.style.display = userRole.includes('quản lý kho') ? '' : 'none';
            } else if (role === 'thu-kho') {
                row.style.display = userRole.includes('thủ kho') ? '' : 'none';
            }
        });
    }

    // Gắn sự kiện click cho các nút
    filterButtons.forEach(button => {
        button.addEventListener('click', () => {
            const role = button.getAttribute('data-role');
            filterTable(role);
        });
    });

    // Load dữ liệu người dùng khi trang được tải
    document.addEventListener('DOMContentLoaded', function() {
        loadUsersData(1, 'all', '');
    });

    // Xử lý lọc theo vai trò
    const roleButtons = document.querySelectorAll('.filter-btn');
    roleButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Xóa class active từ tất cả buttons
            roleButtons.forEach(btn => btn.classList.remove('filter-btn-active'));
            // Thêm class active cho button được click
            this.classList.add('filter-btn-active');

            const role = this.dataset.role || 'all';
            loadUsersData(1, role, currentSearch);
        });
    });

    // Xử lý tìm kiếm
    const searchInput = document.getElementById('userSearch');
    const clearSearchBtn = document.getElementById('clearSearchBtn');
    let searchTimeout;

    // Hàm cập nhật trạng thái nút xóa
    function updateClearButton() {
        if (clearSearchBtn && searchInput) {
            if (searchInput.value.trim() !== '') {
                clearSearchBtn.classList.remove('opacity-0');
                clearSearchBtn.classList.add('opacity-100');
            } else {
                clearSearchBtn.classList.remove('opacity-100');
                clearSearchBtn.classList.add('opacity-0');
            }
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            updateClearButton();
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentSearch = this.value.trim();
                loadUsersData(1, currentRoleFilter, currentSearch);
            }, 500); // Debounce 500ms
        });
    }

    // Xử lý nút xóa tìm kiếm
    if (clearSearchBtn) {
        clearSearchBtn.addEventListener('click', function() {
            if (searchInput) {
                searchInput.value = '';
                searchInput.focus();
                currentSearch = '';
                loadUsersData(1, currentRoleFilter, '');
                updateClearButton();
            }
        });
    }

    // Xử lý submit form thêm người dùng
    const addUserForm = document.getElementById('addUserForm');
    if (addUserForm) {
        addUserForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const submitBtn = document.getElementById('saveUserBtn');
            const originalText = submitBtn.innerHTML;

            // Disable button và hiển thị loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>';

            fetch('add_nguoidung.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Đóng modal và refresh dữ liệu
                    document.getElementById('addUserModal').classList.add('hidden');
                    document.body.style.overflow = '';
                    addUserForm.reset();
                    refreshUsersData();

                    // Hiển thị thông báo thành công
                    showNotification(data.message, 'success');
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Có lỗi xảy ra khi gửi dữ liệu', 'error');
            })
            .finally(() => {
                // Restore button
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
    }

    // Mở modal Thêm
    document.getElementById('openAddUserModal').addEventListener('click', function() {
        document.getElementById('addUserModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden'; // Ngăn scroll nền
        ensureButtonsVisible(); // Đảm bảo các nút hiển thị
    });

    // Đóng modal Thêm (nút Hủy)
    document.getElementById('closeAddUserModal').addEventListener('click', function() {
        document.getElementById('addUserModal').classList.add('hidden');
        document.body.style.overflow = ''; // Khôi phục scroll
    });

    // Đóng khi click nền (tùy chọn - rất tiện)
    document.getElementById('addUserModal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.add('hidden');
            document.body.style.overflow = '';
        }
    });

    //mo sua
    function openEditModal(ma_nd, ten_nd, ma_vai_tro) {
        // Điền dữ liệu vào form
        document.getElementById('edit_ma_nd').value = ma_nd;
        document.getElementById('edit_ma_nd_display').value = ma_nd;
        document.getElementById('edit_ten_nd').value = ten_nd;

        // Chọn đúng chức vụ trong select
        const select = document.getElementById('edit_ma_vai_tro');
        for (let option of select.options) {
            if (option.value === ma_vai_tro) {
                option.selected = true;
                break;
            }
        }

        // Trigger hiển thị các trường bổ sung theo vai trò
        toggleRoleFields(select);

        // Mở modal
        document.getElementById('editUserModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    // Đóng modal Sửa
    document.getElementById('closeEditUserModal').addEventListener('click', function() {
        document.getElementById('editUserModal').classList.add('hidden');
        document.body.style.overflow = '';
    });

    document.getElementById('cancelEditUserModal').addEventListener('click', function() {
        document.getElementById('editUserModal').classList.add('hidden');
        document.body.style.overflow = '';
    });

    // Đóng khi click nền
    document.getElementById('editUserModal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.add('hidden');
            document.body.style.overflow = '';
        }
    });

    // Hàm xử lý hiển thị trường theo vai trò
    function toggleRoleFields(selectElement) {
        const roleFieldsManager = document.getElementById('role-fields-manager');
        const roleFieldsStorekeeper = document.getElementById('role-fields-storekeeper');

        // Ẩn tất cả các trường bổ sung
        if (roleFieldsManager) roleFieldsManager.style.display = 'none';
        if (roleFieldsStorekeeper) roleFieldsStorekeeper.style.display = 'none';

        // Hiển thị trường tương ứng
        if (selectElement.value === 'VT003') { // Quản lý kho
            if (roleFieldsManager) roleFieldsManager.style.display = 'block';
        } else if (selectElement.value === 'VT004') { // Thủ kho
            if (roleFieldsStorekeeper) roleFieldsStorekeeper.style.display = 'block';
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

    // Biến global cho phân trang
    let currentPage = 1;
    let currentLimit = 10;
    let currentRoleFilter = 'all';
    let currentSearch = '';

    // Hàm load dữ liệu người dùng với AJAX
    function loadUsersData(page = 1, role = 'all', search = '') {
        currentPage = page;
        currentRoleFilter = role;
        currentSearch = search;

        // Hiển thị loading
        const tbody = document.getElementById('usersTableBody');
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="px-6 py-8 text-center">
                    <div class="flex items-center justify-center">
                        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-primary"></div>
                        <span class="ml-2 text-slate-500 dark:text-slate-400">Đang tải dữ liệu...</span>
                    </div>
                </td>
            </tr>
        `;

        // Gọi AJAX
        fetch(`get_users_data.php?page=${page}&limit=${currentLimit}&role=${role}&search=${encodeURIComponent(search)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateTableContent(data.data);
                    updatePagination(data.pagination);
                } else {
                    showError('Có lỗi xảy ra khi tải dữ liệu: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Không thể kết nối đến máy chủ');
            });
    }

    // Hàm cập nhật nội dung bảng
    function updateTableContent(users) {
        const tbody = document.getElementById('usersTableBody');

        if (users.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="4" class="px-6 py-4 text-center text-slate-500 dark:text-slate-400">
                        Không có dữ liệu người dùng
                    </td>
                </tr>
            `;
            return;
        }

        let html = '';
        users.forEach(user => {
            // Xác định màu sắc cho badge vai trò
            let badgeClass = '';
            switch (user.ma_vai_tro) {
                case 'VT001': // Admin
                    badgeClass = 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300 border-red-200 dark:border-red-800';
                    break;
                case 'VT002': // Ban giám đốc
                    badgeClass = 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300 border-blue-200 dark:border-blue-800';
                    break;
                case 'VT003': // Quản lý kho
                    badgeClass = 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300 border-purple-200 dark:border-purple-800';
                    break;
                case 'VT004': // Thủ kho
                    badgeClass = 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-600';
                    break;
                default:
                    badgeClass = 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-600';
            }

            html += `
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                    <td class="px-6 py-4 font-medium text-slate-700 dark:text-slate-300">
                        ${escapeHtml(user.ma_nd)}
                    </td>
                    <td class="px-6 py-4 font-medium text-slate-800 dark:text-white">
                        ${escapeHtml(user.ten_nd)}
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${badgeClass}">
                            ${escapeHtml(user.ten_vai_tro || 'Chưa xác định')}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end space-x-2">
                            <button onclick="openEditModal('${escapeHtml(user.ma_nd)}', '${escapeHtml(user.ten_nd.replace(/'/g, "\\'"))}', '${escapeHtml(user.ma_vai_tro)}')"
                                class="p-1.5 text-slate-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded transition-colors"
                                title="Sửa">
                                <span class="material-symbols-outlined text-lg">edit</span>
                            </button>
                            <button onclick="openDeleteModal('${escapeHtml(user.ma_nd)}', '${escapeHtml(user.ten_nd.replace(/'/g, "\\'"))}')"
                                class="p-1.5 text-slate-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded transition-colors"
                                title="Xóa">
                                <span class="material-symbols-outlined text-lg">delete</span>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    // Hàm cập nhật phân trang
    function updatePagination(pagination) {
        const paginationInfo = document.getElementById('paginationInfo');

        // Cập nhật thông tin
        const startRecord = (pagination.current_page - 1) * pagination.limit + 1;
        const endRecord = Math.min(pagination.current_page * pagination.limit, pagination.total_records);

        paginationInfo.querySelector('span').textContent = `Hiển thị ${startRecord}-${endRecord} trên ${pagination.total_records} người dùng`;

        // Tạo controls phân trang
        let controlsHtml = '';

        // Nút Previous
        if (pagination.has_prev) {
            controlsHtml += `<button onclick="loadUsersData(${pagination.current_page - 1}, '${currentRoleFilter}', '${currentSearch}')" class="px-3 py-1 text-sm border border-border-light dark:border-border-dark rounded hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300">Trước</button>`;
        } else {
            controlsHtml += `<button class="px-3 py-1 text-sm border border-border-light dark:border-border-dark rounded text-slate-500 dark:text-slate-400 cursor-not-allowed" disabled>Trước</button>`;
        }

        // Hiển thị các trang (tối đa 5 trang xung quanh trang hiện tại)
        const startPage = Math.max(1, pagination.current_page - 2);
        const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);

        for (let i = startPage; i <= endPage; i++) {
            if (i === pagination.current_page) {
                controlsHtml += `<button class="px-3 py-1 text-sm bg-primary text-white rounded">${i}</button>`;
            } else {
                controlsHtml += `<button onclick="loadUsersData(${i}, '${currentRoleFilter}', '${currentSearch}')" class="px-3 py-1 text-sm border border-border-light dark:border-border-dark rounded hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300">${i}</button>`;
            }
        }

        // Nút Next
        if (pagination.has_next) {
            controlsHtml += `<button onclick="loadUsersData(${pagination.current_page + 1}, '${currentRoleFilter}', '${currentSearch}')" class="px-3 py-1 text-sm border border-border-light dark:border-border-dark rounded hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300">Sau</button>`;
        } else {
            controlsHtml += `<button class="px-3 py-1 text-sm border border-border-light dark:border-border-dark rounded text-slate-500 dark:text-slate-400 cursor-not-allowed" disabled>Sau</button>`;
        }

    }

    // Hàm escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Hàm hiển thị lỗi
    function showError(message) {
        const tbody = document.getElementById('usersTableBody');
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="px-6 py-4 text-center text-red-600 dark:text-red-400">
                    ${message}
                </td>
            </tr>
        `;
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

    //mo xoa
    function openDeleteModal(ma_nd, ten_nd) {
        document.getElementById('delete_user_name').textContent = ten_nd;
        document.getElementById('delete_user_code').textContent = ma_nd;
        document.getElementById('delete_ma_nd').value = ma_nd;

        document.getElementById('deleteUserModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    // Đóng modal Xóa
    document.getElementById('cancelDeleteUserModal').addEventListener('click', function() {
        document.getElementById('deleteUserModal').classList.add('hidden');
        document.body.style.overflow = '';
    });

    // Đóng khi click nền (tùy chọn)
    document.getElementById('deleteUserModal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.add('hidden');
            document.body.style.overflow = '';
        }
    });

    // Hàm refresh dữ liệu sau khi thêm/sửa/xóa
    function refreshUsersData() {
        loadUsersData(currentPage, currentRoleFilter, currentSearch);
    }

    </script>

</body>

</html>