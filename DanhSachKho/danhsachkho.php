<?php
include '../include/connect.php';
include '../include/permissions.php';
checkAccess('danhsachkho');

// Lấy thông tin user
$role = trim($_SESSION['role'] ?? '');
$ma_nd = $_SESSION['MaND'] ?? null;

// Khởi tạo các biến
$kho = [];
$warehouse_keepers = [];
$warehouse_vung = [];
$warehouse_types = [];
$error_message = '';
$search_query = '';
$filter_loai_kho = '';
$total_records = 0;
$total_pages = 0;
$current_page = 1;
$records_per_page = 10;

// Khởi tạo biến thông báo
$message = '';
$message_type = ''; // 'success' hoặc 'error'

// Lấy thông báo từ URL parameter (từ redirect)
if (isset($_GET['status']) && isset($_GET['message'])) {
    $message_type = $_GET['status'];
    $message = urldecode($_GET['message']);
}

// Lấy thông tin người quản lý kho từ cơ sở dữ liệu.
try {
    $stmt = $pdo->prepare('SELECT ma_nd, ten_nd FROM nguoi_dung');
    $stmt->execute();
    $warehouse_keepers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = 'Lỗi khi lấy danh sách thủ kho: ' . $e->getMessage();
}

// Lấy thông tin về loại kho hàng từ cơ sở dữ liệu.
try {
    $stmt = $pdo->prepare('SELECT ma_loai_kho, ten_loai_kho FROM loai_kho');
    $stmt->execute();
    $warehouse_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = 'Lỗi khi lấy danh sách loại kho: ' . $e->getMessage();
}

// Lấy thông tin về loại kho hàng từ cơ sở dữ liệu.
try {
    $stmt = $pdo->prepare('SELECT ma_vung, ten_vung FROM vung_mien');
    $stmt->execute();
    $warehouse_vung = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = 'Lỗi khi lấy danh sách vùng miền: ' . $e->getMessage();
}

// Xử lý việc lọc và tìm kiếm
$search_query = trim($_GET['search'] ?? '');
$filter_loai_kho = trim($_GET['filter_loai_kho'] ?? '');

// Cài đặt phân trang
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $records_per_page;

$filter_sql = '';
$params = [];

// Lọc theo quyền user
if ($role === 'Thủ kho' && $ma_nd) {
    $filter_sql .= ' AND k.ma_nd = :ma_nd';
    $params[':ma_nd'] = $ma_nd;
} elseif ($role === 'Quản lý kho' && $ma_nd) {
    $filter_sql .= ' AND k.ma_kho IN (
        SELECT k2.ma_kho 
        FROM kho k2 
        JOIN phan_quyen pq ON k2.ma_vung = pq.ma_vung AND k2.ma_loai_kho = pq.ma_loai_kho 
        WHERE pq.ma_nd = :ma_nd
    )';
    $params[':ma_nd'] = $ma_nd;
}
// Admin và Ban giám đốc thấy hết, không thêm điều kiện

if (!empty($search_query)) {
    $filter_sql .= " AND k.ten_kho LIKE :search_query";
    $params[':search_query'] = "%$search_query%";
}

if (!empty($filter_loai_kho)) {
    $filter_sql .= " AND k.ma_loai_kho = :filter_loai_kho";
    $params[':filter_loai_kho'] = $filter_loai_kho;
}

// Lấy tổng số lượng để phân trang
try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) as total
                                 FROM kho k
                                 LEFT JOIN nguoi_dung nd ON k.ma_nd = nd.ma_nd
                                 LEFT JOIN loai_kho lk ON k.ma_loai_kho = lk.ma_loai_kho
                                 LEFT JOIN vung_mien vm ON k.ma_vung = vm.ma_vung
                                 WHERE 1=1 $filter_sql");
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $records_per_page);
} catch (Exception $e) {
    $error_message = 'Lỗi khi đếm dữ liệu: ' . $e->getMessage();
    $total_records = 0;
    $total_pages = 0;
}

// Lấy dữ liệu được phân trang
try {
    $stmt = $pdo->prepare("SELECT k.ma_kho, k.ten_kho, k.dia_chi, k.ma_vung, k.ma_nd, k.ma_loai_kho, nd.ten_nd, lk.ten_loai_kho, vm.ten_vung
                           FROM kho k
                           LEFT JOIN nguoi_dung nd ON k.ma_nd = nd.ma_nd
                           LEFT JOIN loai_kho lk ON k.ma_loai_kho = lk.ma_loai_kho
                           LEFT JOIN vung_mien vm ON k.ma_vung = vm.ma_vung
                           WHERE 1=1 $filter_sql
                           ORDER BY k.ma_kho
                           LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $kho = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = 'Lỗi khi lấy dữ liệu: ' . $e->getMessage();
}

// Lấy tất cả dữ liệu kho cho JavaScript (không phân trang, có lọc quyền)
try {
    $all_sql = "SELECT k.ma_kho, k.ten_kho, k.dia_chi, k.ma_vung, k.ma_nd, k.ma_loai_kho, nd.ten_nd, lk.ten_loai_kho, vm.ten_vung
                FROM kho k
                LEFT JOIN nguoi_dung nd ON k.ma_nd = nd.ma_nd
                LEFT JOIN loai_kho lk ON k.ma_loai_kho = lk.ma_loai_kho
                LEFT JOIN vung_mien vm ON k.ma_vung = vm.ma_vung
                WHERE 1=1";

    $all_params = [];

    // Lọc theo quyền user
    if ($role === 'Thủ kho' && $ma_nd) {
        $all_sql .= ' AND k.ma_nd = :ma_nd';
        $all_params[':ma_nd'] = $ma_nd;
    } elseif ($role === 'Quản lý kho' && $ma_nd) {
        $all_sql .= ' AND k.ma_kho IN (
            SELECT k2.ma_kho 
            FROM kho k2 
            JOIN phan_quyen pq ON k2.ma_vung = pq.ma_vung AND k2.ma_loai_kho = pq.ma_loai_kho 
            WHERE pq.ma_nd = :ma_nd
        )';
        $all_params[':ma_nd'] = $ma_nd;
    }
    // Admin và Ban giám đốc thấy hết, không thêm điều kiện

    $all_sql .= " ORDER BY k.ma_kho";

    $all_stmt = $pdo->prepare($all_sql);
    foreach ($all_params as $key => $value) {
        $all_stmt->bindValue($key, $value);
    }
    $all_stmt->execute();
    $all_kho = $all_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $all_kho = [];
}

// Tính toán giá trị hiển thị phân trang
$start_record = $total_records > 0 ? $offset + 1 : 0;
$end_record = min($offset + $records_per_page, $total_records);

// Xây dựng chuỗi truy vấn cho các liên kết phân trang
$query_params = [];
if (!empty($search_query)) {
    $query_params['search'] = $search_query;
}
if (!empty($filter_loai_kho)) {
    $query_params['filter_loai_kho'] = $filter_loai_kho;
}
$query_string = !empty($query_params) ? '&' . http_build_query($query_params) : '';

// Tính toán phạm vi trang cần hiển thị
$max_pages_to_show = 5;
$start_page = max(1, $current_page - floor($max_pages_to_show / 2));
$end_page = min($total_pages, $start_page + $max_pages_to_show - 1);
if ($end_page - $start_page < $max_pages_to_show - 1) {
    $start_page = max(1, $end_page - $max_pages_to_show + 1);
}
?>

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Quản lý Kho - Cập nhật Thủ kho</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&amp;display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "#2563eb",
                        "primary-hover": "#1d4ed8",
                        "background-light": "#f3f4f6",
                        "background-dark": "#111827",
                        "surface-light": "#ffffff",
                        "surface-dark": "#1f2937",
                        "border-light": "#e5e7eb",
                        "border-dark": "#374151",
                        "text-light": "#111827",
                        "text-dark": "#f9fafb",
                        "text-secondary-light": "#6b7280",
                        "text-secondary-dark": "#9ca3af",
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    borderRadius: {
                        DEFAULT: "0.5rem",
                    },
                },
            },
        };
    </script>
</head>

<body
    class="bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark font-sans antialiased min-h-screen transition-colors duration-200">
    <?php include '../include/sidebar.php'; ?>
    <div class="flex-1 flex flex-col h-screen overflow-hidden">
        <?php include '../include/header.php'; ?>
        <main class="flex-1 overflow-y-auto p-6 bg-background-light dark:bg-background-dark">
            <div class="flex py-3 flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h1 class="text-[#111418] dark:text-white text-2xl font-black tracking-tight">Quản lý danh sách kho
                    </h1>
                </div>
            </div>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                    <div class="relative group w-full sm:w-72">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="material-icons text-gray-400">search</span>
                        </div>
                        <input id="liveSearch"
                            class="block w-full pl-10 pr-3 py-2 border border-border-light dark:border-border-dark rounded-lg leading-5 bg-surface-light dark:bg-surface-dark text-text-light dark:text-text-dark placeholder-text-secondary-light dark:placeholder-text-secondary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary sm:text-sm transition-colors"
                            placeholder="Tìm kiếm theo mã, tên kho..." type="text" />
                    </div>
                    <div class="w-full sm:w-48">
                        <select id="liveFilterType"
                            class="block w-full pl-3 pr-10 py-2 text-base border border-border-light dark:border-border-dark focus:outline-none focus:ring-primary focus:border-primary sm:text-sm rounded-lg bg-surface-light dark:bg-surface-dark text-text-light dark:text-text-dark transition-colors">
                            <option value="">Tất cả loại kho</option>
                            <?php foreach ($warehouse_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type['ma_loai_kho']); ?>">
                                    <?php echo htmlspecialchars($type['ten_loai_kho']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button id="resetBtn"
                        class="inline-flex items-center justify-center px-4 py-1 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-lg text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-primary transition-colors">
                        <span class="material-icons mr-2 text-base">refresh</span>
                        Làm mới
                    </button>
                </div>
                <?php if (canCreate('danhsachkho')): ?>
                    <button onclick="openModal()"
                        class="inline-flex items-center justify-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-lg text-white bg-primary hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors w-full md:w-auto">
                        <span class="material-icons mr-2 text-base">add</span>
                        Thêm kho mới
                    </button>
                <?php endif; ?>
            </div>
            <!-- Toast Notification -->
            <?php if (!empty($message)): ?>
                <div id="toast" class="fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg border flex items-center gap-3 <?php echo $message_type === 'success' ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 text-green-800 dark:text-green-200' : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-800 dark:text-red-200'; ?> animate-fadeInDown">
                    <span class="material-icons">
                        <?php echo $message_type === 'success' ? 'check_circle' : 'error'; ?>
                    </span>
                    <span><?php echo htmlspecialchars($message); ?></span>
                    <button onclick="document.getElementById('toast').remove()" class="ml-2 hover:opacity-70">
                        <span class="material-icons text-lg">close</span>
                    </button>
                </div>
                <style>
                    @keyframes fadeInDown {
                        from {
                            opacity: 0;
                            transform: translateY(-20px);
                        }

                        to {
                            opacity: 1;
                            transform: translateY(0);
                        }
                    }

                    .animate-fadeInDown {
                        animation: fadeInDown 0.3s ease-out;
                    }
                </style>
                <script>
                    // Clear URL parameters after displaying message (so reload doesn't show message again)
                    window.history.replaceState({}, document.title, window.location.pathname);

                    // Auto hide toast after 5 seconds
                    setTimeout(() => {
                        const toast = document.getElementById('toast');
                        if (toast) {
                            toast.style.opacity = '0';
                            toast.style.transition = 'opacity 0.3s ease-out';
                            setTimeout(() => toast.remove(), 300);
                        }
                    }, 5000);
                </script>
            <?php endif; ?>
            <div
                class="bg-surface-light dark:bg-surface-dark shadow-sm rounded-xl border border-border-light dark:border-border-dark overflow-hidden transition-colors duration-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-border-light dark:divide-border-dark table-fixed">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-text-secondary-light dark:text-text-secondary-dark uppercase tracking-wider w-[10%]"
                                    scope="col">
                                    Mã Kho
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-text-secondary-light dark:text-text-secondary-dark uppercase tracking-wider w-[20%]"
                                    scope="col">
                                    Tên Kho
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-text-secondary-light dark:text-text-secondary-dark uppercase tracking-wider w-[20%]"
                                    scope="col">
                                    Địa chỉ
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-text-secondary-light dark:text-text-secondary-dark uppercase tracking-wider w-[15%]"
                                    scope="col">
                                    Vùng miền
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-text-secondary-light dark:text-text-secondary-dark uppercase tracking-wider w-[15%]"
                                    scope="col">
                                    Thủ kho
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-text-secondary-light dark:text-text-secondary-dark uppercase tracking-wider w-[20%]"
                                    scope="col">
                                    Loại Kho
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-text-secondary-light dark:text-text-secondary-dark uppercase tracking-wider w-[15%]"
                                    scope="col">
                                    Thao tác
                                </th>
                            </tr>
                        </thead>
                        <tbody id="tableBody"
                            class="bg-surface-light dark:bg-surface-dark divide-y divide-border-light dark:divide-border-dark">
                            <?php if (!empty($kho)): ?>
                                <?php foreach ($kho as $item): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                                        data-ma-kho="<?php echo htmlspecialchars(strtolower($item['ma_kho'])); ?>"
                                        data-ten-kho="<?php echo htmlspecialchars(strtolower($item['ten_kho'])); ?>"
                                        data-dia-chi="<?php echo htmlspecialchars(strtolower($item['dia_chi'])); ?>"
                                        data-vung-mien="<?php echo htmlspecialchars(strtolower($item['ten_vung'] ?? '')); ?>"
                                        data-loai-kho="<?php echo htmlspecialchars($item['ma_loai_kho'] ?? ''); ?>"
                                        data-ten-loai-kho="<?php echo htmlspecialchars(strtolower($item['ten_loai_kho'] ?? '')); ?>">
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-sm font-medium text-text-light dark:text-text-dark">
                                            <?php echo htmlspecialchars($item['ma_kho']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-text-light dark:text-text-dark truncate"
                                            title="<?php echo htmlspecialchars($item['ten_kho']); ?>">
                                            <?php echo htmlspecialchars($item['ten_kho']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-text-light dark:text-text-dark truncate"
                                            title="<?php echo htmlspecialchars($item['dia_chi']); ?>">
                                            <?php echo htmlspecialchars($item['dia_chi']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-text-light dark:text-text-dark">
                                            <?php echo htmlspecialchars($item['ten_vung'] ?? ''); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-text-light dark:text-text-dark">
                                            <?php echo htmlspecialchars($item['ten_nd'] ?? ''); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span
                                                class="px-2.5 py-1 inline-flex text-xs leading-4 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                                <?php echo htmlspecialchars($item['ten_loai_kho'] ?? 'Chưa xác định'); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex items-center justify-end space-x-2">
                                                <?php if (canEdit('danhsachkho')): ?>
                                                    <button
                                                        onclick="openEditModal('<?php echo htmlspecialchars($item['ma_kho']); ?>', '<?php echo htmlspecialchars($item['ten_kho']); ?>', '<?php echo htmlspecialchars($item['dia_chi']); ?>', '<?php echo htmlspecialchars($item['ma_vung'] ?? ''); ?>', '<?php echo htmlspecialchars($item['ma_nd'] ?? ''); ?>', '<?php echo htmlspecialchars($item['ma_loai_kho'] ?? ''); ?>')"
                                                        class="p-1.5 rounded-md text-[#637588] hover:text-primary hover:bg-primary/10"
                                                        title="Chỉnh sửa">
                                                        <span class="material-icons text-[20px]">edit</span>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if (canDelete('danhsachkho')): ?>
                                                   <button
                                                        class="p-1.5 rounded-md text-[#637588] hover:text-red-500 hover:bg-red-50"
                                                        title="Xóa"
                                                        onclick="confirmDelete('<?php echo htmlspecialchars($item['ma_kho']); ?>')">
                                                        <span class="material-icons text-[20px]">delete</span>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6"
                                        class="px-6 py-8 text-center text-sm text-text-secondary-light dark:text-text-secondary-dark">
                                        Không có dữ liệu kho
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div id="paginationInfo"
                    class="bg-surface-light dark:bg-surface-dark px-4 py-3 flex items-center justify-between border-t border-border-light dark:border-border-dark sm:px-6">
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p id="resultCount" class="text-sm text-text-secondary-light dark:text-text-secondary-dark">
                                Hiển thị từ <span class="font-medium text-text-light dark:text-text-dark"><?php echo $start_record; ?></span> đến <span class="font-medium text-text-light dark:text-text-dark"><?php echo $end_record; ?></span> trong <span class="font-medium text-text-light dark:text-text-dark"><?php echo $total_records; ?></span> kết quả
                            </p>
                        </div>
                        <?php if ($total_pages > 1): ?>
                            <div class="flex items-center gap-1">
                                <!-- Nút Previous -->
                                <button onclick="changePage('prev')"
                                    class="h-8 w-8 flex items-center justify-center rounded-lg text-slate-600 hover:bg-gray-100 dark:hover:bg-slate-700 dark:text-slate-300 <?= $current_page <= 1 ? 'opacity-50 cursor-not-allowed pointer-events-none' : '' ?>">
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
                                    <button onclick="changePage(1)"
                                        class="h-8 w-8 flex items-center justify-center rounded-lg text-slate-600 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700 text-sm font-medium">
                                        1
                                    </button>
                                    <?php if ($start_page > 2): ?>
                                        <span
                                            class="h-8 w-8 flex items-center justify-center text-slate-600 dark:text-slate-300">...</span>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <button onclick="changePage(<?= $i ?>)" data-page="<?= $i ?>"
                                        class="pagination-btn h-8 w-8 flex items-center justify-center rounded-lg <?= $i == $current_page ? 'bg-primary text-white' : 'text-slate-600 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700' ?> text-sm font-medium">
                                        <?= $i ?>
                                    </button>
                                <?php endfor; ?>

                                <?php if ($end_page < $total_pages): ?>
                                    <?php if ($end_page < $total_pages - 1): ?>
                                        <span
                                            class="h-8 w-8 flex items-center justify-center text-slate-600 dark:text-slate-300">...</span>
                                    <?php endif; ?>
                                    <button onclick="changePage(<?= $total_pages ?>)"
                                        class="h-8 w-8 flex items-center justify-center rounded-lg text-slate-600 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700 text-sm font-medium">
                                        <?= $total_pages ?>
                                    </button>
                                <?php endif; ?>

                                <!-- Nút Next -->
                                <button onclick="changePage('next')"
                                    class="h-8 w-8 flex items-center justify-center rounded-lg text-slate-600 hover:bg-gray-100 dark:hover:bg-slate-700 dark:text-slate-300 <?= $current_page >= $total_pages ? 'opacity-50 cursor-not-allowed pointer-events-none' : '' ?>">
                                    <span class="material-symbols-outlined text-[20px]">chevron_right</span>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <div id="addWarehouseModal" aria-labelledby="modal-title" aria-modal="true"
        class="fixed inset-0 hidden z-50 overflow-y-auto" role="dialog">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div aria-hidden="true" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
            <span aria-hidden="true" class="hidden sm:inline-block sm:align-middle sm:h-screen">​</span>
            <div
                class="inline-block align-bottom bg-surface-light dark:bg-surface-dark rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full border border-border-light dark:border-border-dark">
                <form method="POST" action="add_dskho.php">
                    <div
                        class="px-6 py-4 border-b border-border-light dark:border-border-dark flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-text-light dark:text-text-dark" id="modal-title">
                            Thêm Kho Mới
                        </h3>
                        <button onclick="closeModal()"
                            class="text-text-secondary-light dark:text-text-secondary-dark hover:text-text-light dark:hover:text-text-dark transition-colors focus:outline-none"
                            type="button">
                            <span class="material-icons">close</span>
                        </button>
                    </div>
                    <div class="px-6 py-6 space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-text-light dark:text-text-dark mb-1"
                                for="warehouse-name">
                                Tên Kho <span class="text-red-500">*</span>
                            </label>
                            <input
                                class="block w-full border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark focus:ring-primary focus:border-primary sm:text-sm py-2 px-3"
                                id="warehouse-name" name="warehouse-name" placeholder="Nhập tên kho..." type="text"
                                required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-light dark:text-text-dark mb-1"
                                for="warehouse-address">
                                Địa chỉ <span class="text-red-500">*</span>
                            </label>
                            <input
                                class="block w-full border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark focus:ring-primary focus:border-primary sm:text-sm py-2 px-3"
                                id="warehouse-address" name="warehouse-address" placeholder="Nhập địa chỉ..." type="text" required />
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-text-light dark:text-text-dark mb-1"
                                    for="warehouse-vung">
                                    Vùng miền
                                </label>
                                <select
                                    class="block w-full border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark focus:ring-primary focus:border-primary sm:text-sm py-2 px-3"
                                    id="warehouse-vung" name="warehouse-vung">
                                    <option disabled selected value="">Chọn vùng miền</option>
                                    <?php foreach ($warehouse_vung as $vung): ?>
                                        <option value="<?php echo htmlspecialchars($vung['ma_vung']); ?>">
                                            <?php echo htmlspecialchars($vung['ten_vung']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-text-light dark:text-text-dark mb-1"
                                    for="warehouse-type">
                                    Loại Kho <span class="text-red-500">*</span>
                                </label>
                                <select
                                    class="block w-full border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark focus:ring-primary focus:border-primary sm:text-sm py-2 px-3"
                                    id="warehouse-type" name="warehouse-type" required>
                                    <option disabled selected value="">Chọn loại kho</option>
                                    <?php foreach ($warehouse_types as $type): ?>
                                        <option value="<?php echo htmlspecialchars($type['ma_loai_kho']); ?>">
                                            <?php echo htmlspecialchars($type['ten_loai_kho']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-800/50 px-6 py-4 flex flex-row-reverse gap-3">
                        <button
                            class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary sm:ml-3 sm:w-auto sm:text-sm transition-colors"
                            type="submit" name="add-warehouse">
                            Lưu
                        </button>
                        <button onclick="closeModal()"
                            class="mt-3 w-full inline-flex justify-center rounded-lg border border-border-light dark:border-border-dark shadow-sm px-4 py-2 bg-surface-light dark:bg-surface-dark text-base font-medium text-text-light dark:text-text-dark hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-colors"
                            type="button">
                            Hủy
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Warehouse Modal -->
    <div id="editWarehouseModal" aria-labelledby="edit-modal-title" aria-modal="true"
        class="fixed inset-0 hidden z-50 overflow-y-auto" role="dialog">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div aria-hidden="true" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
            <span aria-hidden="true" class="hidden sm:inline-block sm:align-middle sm:h-screen">​</span>
            <div
                class="inline-block align-bottom bg-surface-light dark:bg-surface-dark rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full border border-border-light dark:border-border-dark">
                <form method="POST" action="update_dskho.php">
                    <div
                        class="px-6 py-4 border-b border-border-light dark:border-border-dark flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-text-light dark:text-text-dark" id="edit-modal-title">
                            Chỉnh sửa Kho
                        </h3>
                        <button onclick="closeEditModal()"
                            class="text-text-secondary-light dark:text-text-secondary-dark hover:text-text-light dark:hover:text-text-dark transition-colors focus:outline-none"
                            type="button">
                            <span class="material-icons">close</span>
                        </button>
                    </div>
                    <div class="px-6 space-y-5">
                        <input type="hidden" name="ma_kho" id="edit_ma_kho">
                        <div>
                            <label class="block text-sm font-medium text-text-light dark:text-text-dark mb-1">
                                Mã Kho <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-text-secondary-light dark:text-text-secondary-dark">
                                    <span class="material-icons text-sm">warehouse</span>
                                </span>
                                <input
                                    class="pl-10 block w-full border-border-light dark:border-border-dark rounded-lg bg-gray-100 dark:bg-gray-800 text-text-secondary-light dark:text-text-secondary-dark focus:ring-primary focus:border-primary sm:text-sm py-2 px-3 cursor-not-allowed"
                                    id="edit_ma_kho_display" readonly type="text" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-light dark:text-text-dark mb-1"
                                for="edit-warehouse-name">
                                Tên Kho <span class="text-red-500">*</span>
                            </label>
                            <input
                                class="block w-full border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark focus:ring-primary focus:border-primary sm:text-sm py-2 px-3"
                                id="edit-warehouse-name" name="warehouse-name" placeholder="Nhập tên kho..." type="text"
                                required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-light dark:text-text-dark mb-1"
                                for="edit-warehouse-address">
                                Địa chỉ <span class="text-red-500">*</span>
                            </label>
                            <input
                                class="block w-full border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark focus:ring-primary focus:border-primary sm:text-sm py-2 px-3"
                                id="edit-warehouse-address" name="warehouse-address" placeholder="Nhập địa chỉ..." type="text" required />
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-text-light dark:text-text-dark mb-1"
                                    for="edit-warehouse-vung">
                                    Vùng miền
                                </label>
                                <select
                                    class="block w-full border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark focus:ring-primary focus:border-primary sm:text-sm py-2 px-3"
                                    id="edit-warehouse-vung" name="warehouse-vung">
                                    <option disabled selected value="">Chọn vùng miền</option>
                                    <?php foreach ($warehouse_vung as $vung): ?>
                                        <option value="<?php echo htmlspecialchars($vung['ma_vung']); ?>">
                                            <?php echo htmlspecialchars($vung['ten_vung']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="hidden">
                                <label class="block text-sm font-medium text-text-light dark:text-text-dark mb-1"
                                    for="edit-warehouse-keeper">
                                    Thủ kho
                                </label>
                                <select
                                    class="block w-full border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark focus:ring-primary focus:border-primary sm:text-sm py-2 px-3"
                                    id="edit-warehouse-keeper" name="warehouse-keeper">
                                    <option disabled selected value="">Chọn thủ kho</option>
                                    <?php foreach ($warehouse_keepers as $keeper): ?>
                                        <option value="<?php echo htmlspecialchars($keeper['ma_nd']); ?>">
                                            <?php echo htmlspecialchars($keeper['ten_nd']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-text-light dark:text-text-dark mb-1"
                                    for="edit-warehouse-type">
                                    Loại Kho <span class="text-red-500">*</span>
                                </label>
                                <select
                                    class="block w-full border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark focus:ring-primary focus:border-primary sm:text-sm py-2 px-3"
                                    id="edit-warehouse-type" name="warehouse-type" required>
                                    <option disabled value="">Chọn loại kho</option>
                                    <?php foreach ($warehouse_types as $type): ?>
                                        <option value="<?php echo htmlspecialchars($type['ma_loai_kho']); ?>">
                                            <?php echo htmlspecialchars($type['ten_loai_kho']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-800/50 px-6 py-4 flex flex-row-reverse gap-3">
                        <button
                            class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary sm:ml-3 sm:w-auto sm:text-sm transition-colors"
                            type="submit" name="update-warehouse">
                            Lưu thay đổi
                        </button>
                        <button onclick="closeEditModal()"
                            class="mt-3 w-full inline-flex justify-center rounded-lg border border-border-light dark:border-border-dark shadow-sm px-4 py-2 bg-surface-light dark:bg-surface-dark text-base font-medium text-text-light dark:text-text-dark hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-colors"
                            type="button">
                            Hủy
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    var allWarehouseData = <?php echo json_encode($all_kho); ?>;
    const canEditWarehouse = <?php echo canEdit('danhsachkho') ? 'true' : 'false'; ?>;
    const canDeleteWarehouse = <?php echo canDelete('danhsachkho') ? 'true' : 'false'; ?>;
    </script>
    <script src="danhsachkho.js"></script>
</body>

</html>
