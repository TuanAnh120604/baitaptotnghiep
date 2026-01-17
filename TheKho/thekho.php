<?php
// thekho.php
include '../include/connect.php'; // file kết nối PDO của bạn
include '../include/permissions.php';
checkAccess('thekho');

// Kiểm tra xem đây có phải là request AJAX không
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Phân trang
$limit = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Bộ lọc kho
$ma_kho_filter = $_GET['ma_kho'] ?? '';

// Bộ lọc loại hàng
$ma_loai_hang_filter = $_GET['ma_loai_hang'] ?? '';

// Lấy thông tin user
$role = trim($_SESSION['role'] ?? '');
$ma_nd = $_SESSION['MaND'] ?? null;

// Lấy danh sách kho và loại hàng (chỉ khi không phải AJAX)
if (!$is_ajax) {
    $kho_sql = "SELECT ma_kho, ten_kho FROM kho";
    $kho_conditions = [];
    $kho_params = [];

    // Lọc kho theo quyền user
    if ($role === 'Thủ kho' && $ma_nd) {
        $kho_conditions[] = "ma_nd = ?";
        $kho_params[] = $ma_nd;
    } elseif ($role === 'Quản lý kho' && $ma_nd) {
        $kho_conditions[] = "ma_kho IN (
            SELECT k.ma_kho 
            FROM kho k 
            JOIN phan_quyen pq ON k.ma_vung = pq.ma_vung AND k.ma_loai_kho = pq.ma_loai_kho 
            WHERE pq.ma_nd = ?
        )";
        $kho_params[] = $ma_nd;
    }
    // Admin và Ban giám đốc thấy hết

    if (!empty($kho_conditions)) {
        $kho_sql .= " WHERE " . implode(" AND ", $kho_conditions);
    }
    $kho_sql .= " ORDER BY ten_kho";

    $kho_stmt = $pdo->prepare($kho_sql);
    $kho_stmt->execute($kho_params);
    $kho_list = $kho_stmt->fetchAll(PDO::FETCH_ASSOC);

    $loai_hang_list = $pdo->query("SELECT ma_loai_hang, ten_loai_hang FROM loai_hang ORDER BY ten_loai_hang")->fetchAll(PDO::FETCH_ASSOC);
}

// Đếm tổng
$count_sql = "SELECT COUNT(DISTINCT tk.ma_the_kho) FROM the_kho tk
              LEFT JOIN hang_hoa h ON tk.ma_hang = h.ma_hang";
$conditions = [];
$params = [];

if ($ma_kho_filter) {
    $conditions[] = "tk.ma_kho = ?";
    $params[] = $ma_kho_filter;
}
if ($ma_loai_hang_filter) {
    $conditions[] = "h.ma_loai_hang = ?";
    $params[] = $ma_loai_hang_filter;
}

// Lọc theo quyền user
if ($role === 'Thủ kho' && $ma_nd) {
    $conditions[] = "tk.ma_kho IN (SELECT ma_kho FROM kho WHERE ma_nd = ?)";
    $params[] = $ma_nd;
} elseif ($role === 'Quản lý kho' && $ma_nd) {
    $conditions[] = "tk.ma_kho IN (
        SELECT k.ma_kho 
        FROM kho k 
        JOIN phan_quyen pq ON k.ma_vung = pq.ma_vung AND k.ma_loai_kho = pq.ma_loai_kho 
        WHERE pq.ma_nd = ?
    )";
    $params[] = $ma_nd;
}
// Admin và Ban giám đốc thấy hết, không thêm điều kiện

if (!empty($conditions)) {
    $count_sql .= " WHERE " . implode(" AND ", $conditions);
}

$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Lấy dữ liệu (chỉ cần các cột cần thiết + tồn kho cuối cùng của mỗi thẻ)
$sql = "
    SELECT DISTINCT
        tk.ma_the_kho,
        tk.ma_kho,
        k.ten_kho,
        tk.ma_hang,
        h.ten_hang,
        tk.so_luong_ton
    FROM the_kho tk
    LEFT JOIN kho k ON tk.ma_kho = k.ma_kho
    LEFT JOIN hang_hoa h ON tk.ma_hang = h.ma_hang
";

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY tk.ma_the_kho DESC LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($sql);
$idx = 1;

// Bind các tham số điều kiện lọc
foreach ($params as $param) {
    $stmt->bindValue($idx++, $param);
}

$stmt->bindValue($idx++, $limit, PDO::PARAM_INT);
$stmt->bindValue($idx, $offset, PDO::PARAM_INT);
$stmt->execute();
$the_kho_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Nếu là request AJAX, trả về JSON
if ($is_ajax) {
    header('Content-Type: application/json');
    echo json_encode([
        'data' => $the_kho_list,
        'pagination' => [
            'page' => $page,
            'total_pages' => $total_pages,
            'total_records' => $total_records,
            'offset' => $offset,
            'limit' => $limit,
            'ma_kho_filter' => $ma_kho_filter,
            'ma_loai_hang_filter' => $ma_loai_hang_filter
        ]
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Quản lý Thẻ kho</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
    <!-- Giữ nguyên phần tailwind config và style của bạn -->
    <style>
    /* copy toàn bộ style từ file gốc của bạn */
    body {
        font-family: 'Inter', sans-serif;
    }

    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    ::-webkit-scrollbar-track {
        background: transparent;
    }

    ::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }

    .dark ::-webkit-scrollbar-thumb {
        background: #475569;
    }

    aside.history-section {
        display: none;
    }

    .history-section .overflow-y-auto {
        max-height: 695px;
        overflow-y: auto;
    }
    </style>
</head>

<body
    class="bg-background-light dark:bg-background-dark text-slate-800 dark:text-slate-200 transition-colors duration-200 h-screen flex flex-col overflow-hidden">
    <?php include '../include/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        <?php include '../include/header.php'; ?>

        <main class="flex-1 flex flex-col min-w-0 overflow-hidden bg-background-light dark:bg-background-dark">
            <!-- Header + bộ lọc -->
            <div
                class="px-6 py-5 border-b border-border-light dark:border-border-dark bg-surface-light dark:bg-surface-dark flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-slate-800 dark:text-white">Thẻ kho</h2>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <!-- Bộ lọc kho -->
                    <div class="relative">
                        <select id="filter-kho"
                            class="pl-3 pr-10 py-2 rounded-lg border ... appearance-none cursor-pointer">
                            <option value="">Tất cả kho</option>
                            <?php
                            foreach ($kho_list as $k) {
                                $selected = ($ma_kho_filter === $k['ma_kho']) ? 'selected' : '';
                                echo "<option value='{$k['ma_kho']}' $selected>{$k['ten_kho']} ({$k['ma_kho']})</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Bộ lọc loại hàng -->
                    <div class="relative">
                        <select id="filter-loai"
                            class="pl-3 pr-10 py-2 rounded-lg border ... appearance-none cursor-pointer">
                            <option value="">Tất cả loại hàng</option>
                            <?php
                            foreach ($loai_hang_list as $l) {
                                $selected = ($ma_loai_hang_filter === $l['ma_loai_hang']) ? 'selected' : '';
                                echo "<option value='{$l['ma_loai_hang']}' $selected>{$l['ten_loai_hang']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <button id="export-excel-btn"
                        class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors shadow-sm">
                        <span class="material-icons text-lg">file_download</span>
                        Xuất Excel
                    </button>
                </div>
            </div>

            <!-- Bảng danh sách -->
            <div class="flex-1 overflow-auto p-6">
                <div
                    class="bg-surface-light dark:bg-surface-dark rounded-xl shadow-sm border overflow-hidden flex flex-col">
                    <div class="px-6 py-4 border-b flex justify-between items-center bg-gray-50 dark:bg-slate-800/50">
                        <h3 class="font-semibold text-slate-800 dark:text-white">Danh sách thẻ kho</h3>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead
                                class="text-xs text-slate-500 uppercase bg-slate-50 dark:bg-slate-800 dark:text-slate-400">
                                <tr>
                                    <th class="px-6 py-3">Mã Thẻ Kho</th>
                                    <th class="px-6 py-3">Kho</th>
                                    <th class="px-6 py-3">Tên Hàng</th>
                                    <th class="px-6 py-3 text-right">Số lượng tồn</th>
                                    <th class="px-6 py-3 text-center">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-light dark:divide-border-dark">
                                <?php if (empty($the_kho_list)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-slate-500">Chưa có dữ liệu thẻ
                                        kho</td>
                                </tr>
                                <?php else: foreach ($the_kho_list as $tk): ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                                    data-ma-hang="<?= htmlspecialchars($tk['ma_hang']) ?>"
                                    data-ma-kho="<?= htmlspecialchars($tk['ma_kho'] ?? '') ?>"
                                    data-ten-hang="<?= htmlspecialchars($tk['ten_hang'] ?? $tk['ma_hang']) ?>">
                                    <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">
                                        <?= htmlspecialchars($tk['ma_the_kho']) ?>
                                    </td>
                                    <td class="px-6 py-4 text-primary font-medium">
                                        <?= htmlspecialchars($tk['ten_kho'] ?? 'Kho không xác định') ?>
                                        <span class="block text-xs text-slate-500 dark:text-slate-400">Mã:
                                            <?= htmlspecialchars($tk['ma_kho']) ?></span>
                                    </td>
                                    <td class="px-6 py-4 text-primary font-medium">
                                        <?= htmlspecialchars($tk['ten_hang'] ?? $tk['ma_hang']) ?>
                                        <span class="block text-xs text-slate-500 dark:text-slate-400">Mã:
                                            <?= htmlspecialchars($tk['ma_hang']) ?></span>
                                    </td>
                                    <td
                                        class="px-6 py-4 text-right font-bold <?= $tk['so_luong_ton'] <= 0 ? 'text-red-600' : 'text-green-600' ?>">
                                        <?= number_format($tk['so_luong_ton']) ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <button
                                            class="view-history-btn text-slate-400 hover:text-primary transition-colors ml-2"
                                            title="Xem lịch sử biến động">
                                            <span class="material-icons text-lg">history</span>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div
                        class="px-6 py-4 border-t border-border-light dark:border-border-dark flex items-center justify-between shrink-0 bg-white dark:bg-surface-dark">
                        <div class="text-sm text-slate-700 dark:text-slate-400">
                            Hiển thị <span class="font-medium"><?= $offset + 1 ?></span> đến <span
                                class="font-medium"><?= min($offset + $limit, $total_records) ?></span> của <span
                                class="font-medium"><?= $total_records ?></span> dòng
                        </div>
                        <nav class="flex items-center gap-1">
                            <?php
                            $pagination_params = [];
                            if ($ma_kho_filter) $pagination_params[] = "ma_kho=" . urlencode($ma_kho_filter);
                            if ($ma_loai_hang_filter) $pagination_params[] = "ma_loai_hang=" . urlencode($ma_loai_hang_filter);
                            $pagination_query = !empty($pagination_params) ? "&" . implode("&", $pagination_params) : "";
                            ?>
                            <a href="#" onclick="changePage(<?= $page - 1 ?>, '<?= $ma_kho_filter ?>', '<?= $ma_loai_hang_filter ?>')"
                                class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 <?= $page <= 1 ? 'opacity-50 cursor-not-allowed' : '' ?>">
                                <span class="material-icons text-sm">chevron_left</span>
                            </a>
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="#" onclick="changePage(<?= $i ?>, '<?= $ma_kho_filter ?>', '<?= $ma_loai_hang_filter ?>')"
                                class="px-3 py-1.5 rounded-lg <?= $i == $page ? 'bg-primary text-white' : 'hover:bg-slate-100 dark:hover:bg-slate-800' ?> text-sm font-medium">
                                <?= $i ?>
                            </a>
                            <?php endfor; ?>
                            <a href="#" onclick="changePage(<?= $page + 1 ?>, '<?= $ma_kho_filter ?>', '<?= $ma_loai_hang_filter ?>')"
                                class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 <?= $page >= $total_pages ? 'opacity-50 cursor-not-allowed' : '' ?>">
                                <span class="material-icons text-sm">chevron_right</span>
                            </a>
                        </nav>
                    </div>
        </main>
    </div>
    <!-- Sidebar lịch sử biến động -->
    <aside
        class="w-80 bg-surface-light dark:bg-surface-dark border-l border-border-light dark:border-border-dark hidden lg:flex flex-col history-section">
        <div class="p-5 border-b ... flex items-center justify-between">
            <div>
                <h3 class="font-bold text-lg text-slate-800 dark:text-white">Lịch sử biến động</h3>
                <p class="text-sm text-slate-500" id="current-title">Chọn một mặt hàng để xem lịch sử</p>
            </div>
            <button id="close-history"
                class="text-slate-400 hover:text-slate-500 dark:hover:text-slate-300 text-3xl">×</button>
        </div>
        <div class="flex-1 overflow-y-auto p-5" id="history-content">
            <div class="text-center text-slate-500 py-10">
                Chọn một dòng trong bảng để xem lịch sử chi tiết
            </div>
        </div>
    </aside>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const historySection = document.querySelector("aside.history-section");
        const historyContent = document.getElementById("history-content");
        const currentTitle = document.getElementById("current-title");
        const closeBtn = document.getElementById("close-history");

        // Đóng sidebar
        if (closeBtn) {
            closeBtn.addEventListener("click", () => {
                historySection.style.display = "none";
            });
        }

        // Click nút xem lịch sử
        document.querySelectorAll(".view-history-btn").forEach(function(btn) {
            btn.addEventListener("click", function(e) {
                e.preventDefault();
                e.stopPropagation();

                const tr = this.closest("tr");
                if (!tr) return;

                const maHang = tr.dataset.maHang;
                const maKho = tr.dataset.maKho;
                const tenHang = tr.dataset.tenHang;

                if (!maHang) {
                    alert("Không tìm thấy mã hàng hóa!");
                    return;
                }

                currentTitle.textContent =
                    `${tenHang} (${maHang})${maKho ? ' - Kho: ' + maKho : ''}`;

                fetch(
                        `get_lsu_biendong.php?ma_hang=${encodeURIComponent(maHang)}${maKho ? '&ma_kho=' + encodeURIComponent(maKho) : ''}`
                    )
                    .then(response => {
                        if (!response.ok) throw new Error('Lỗi mạng');
                        return response.text();
                    })
                    .then(html => {
                        historyContent.innerHTML = html;
                        historySection.style.display = "flex"; // hoặc "block" tùy layout
                    })
                    .catch(err => {
                        console.error(err);
                        historyContent.innerHTML =
                            '<p class="text-red-500 text-center py-10">Không thể tải lịch sử biến động</p>';
                    });
            });
        });

        // Bộ lọc tự động khi thay đổi select
        function applyFilters() {
            const kho = document.getElementById("filter-kho").value;
            const loai = document.getElementById("filter-loai").value;

            // Hiển thị loading
            const tableBody = document.querySelector("tbody");
            const paginationContainer = document.querySelector(".px-6.py-4.border-t .text-sm");
            const paginationNav = document.querySelector("nav.flex.items-center.gap-1");

            tableBody.innerHTML = '<tr><td colspan="5" class="px-6 py-12 text-center text-slate-500"><div class="flex items-center justify-center gap-2"><div class="animate-spin rounded-full h-4 w-4 border-b-2 border-primary"></div>Đang tải dữ liệu...</div></td></tr>';
            paginationContainer.innerHTML = '';
            paginationNav.innerHTML = '';

            // Gọi AJAX
            fetch(window.location.pathname + '?page=1' +
                  (kho ? '&ma_kho=' + encodeURIComponent(kho) : '') +
                  (loai ? '&ma_loai_hang=' + encodeURIComponent(loai) : ''), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                updateTable(data.data);
                updatePagination(data.pagination);
                updateUrl(kho, loai, 1);
            })
            .catch(error => {
                console.error('Error:', error);
                tableBody.innerHTML = '<tr><td colspan="5" class="px-6 py-12 text-center text-red-500">Có lỗi xảy ra khi tải dữ liệu</td></tr>';
            });
        }

        // Cập nhật bảng
        function updateTable(data) {
            const tableBody = document.querySelector("tbody");

            if (data.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="5" class="px-6 py-12 text-center text-slate-500">Chưa có dữ liệu thẻ kho</td></tr>';
                return;
            }

            let html = '';
            data.forEach(tk => {
                html += `
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                        data-ma-hang="${tk.ma_hang}"
                        data-ma-kho="${tk.ma_kho || ''}"
                        data-ten-hang="${tk.ten_hang || tk.ma_hang}">
                        <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">
                            ${tk.ma_the_kho}
                        </td>
                        <td class="px-6 py-4 text-primary font-medium">
                            ${tk.ten_kho || 'Kho không xác định'}
                            <span class="block text-xs text-slate-500 dark:text-slate-400">Mã: ${tk.ma_kho}</span>
                        </td>
                        <td class="px-6 py-4 text-primary font-medium">
                            ${tk.ten_hang || tk.ma_hang}
                            <span class="block text-xs text-slate-500 dark:text-slate-400">Mã: ${tk.ma_hang}</span>
                        </td>
                        <td class="px-6 py-4 text-right font-bold ${tk.so_luong_ton <= 0 ? 'text-red-600' : 'text-green-600'}">
                            ${Number(tk.so_luong_ton).toLocaleString()}
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button class="view-history-btn text-slate-400 hover:text-primary transition-colors ml-2"
                                    title="Xem lịch sử biến động">
                                <span class="material-icons text-lg">history</span>
                            </button>
                        </td>
                    </tr>
                `;
            });
            tableBody.innerHTML = html;
        }

        // Cập nhật phân trang
        function updatePagination(pagination) {
            const paginationContainer = document.querySelector(".px-6.py-4.border-t .text-sm");
            const paginationNav = document.querySelector("nav.flex.items-center.gap-1");

            // Cập nhật thông tin hiển thị
            const start = pagination.offset + 1;
            const end = Math.min(pagination.offset + pagination.limit, pagination.total_records);
            paginationContainer.innerHTML = `
                Hiển thị <span class="font-medium">${start}</span> đến <span class="font-medium">${end}</span> của <span class="font-medium">${pagination.total_records}</span> dòng
            `;

            // Cập nhật navigation
            let navHtml = '';
            const page = pagination.page;
            const totalPages = pagination.total_pages;
            const maKhoFilter = pagination.ma_kho_filter;
            const maLoaiHangFilter = pagination.ma_loai_hang_filter;

            // Nút Previous
            navHtml += `<a href="#" onclick="changePage(${page - 1}, '${maKhoFilter}', '${maLoaiHangFilter}')" class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 ${page <= 1 ? 'opacity-50 cursor-not-allowed' : ''}"><span class="material-icons text-sm">chevron_left</span></a>`;

            // Các nút trang
            for (let i = Math.max(1, page - 2); i <= Math.min(totalPages, page + 2); i++) {
                navHtml += `<a href="#" onclick="changePage(${i}, '${maKhoFilter}', '${maLoaiHangFilter}')" class="px-3 py-1.5 rounded-lg ${i == page ? 'bg-primary text-white' : 'hover:bg-slate-100 dark:hover:bg-slate-800'} text-sm font-medium">${i}</a>`;
            }

            // Nút Next
            navHtml += `<a href="#" onclick="changePage(${page + 1}, '${maKhoFilter}', '${maLoaiHangFilter}')" class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 ${page >= totalPages ? 'opacity-50 cursor-not-allowed' : ''}"><span class="material-icons text-sm">chevron_right</span></a>`;

            paginationNav.innerHTML = navHtml;
        }

        // Hàm thay đổi trang
        function changePage(pageNum, maKho, maLoaiHang) {
            if (pageNum < 1) return;

            // Hiển thị loading
            const tableBody = document.querySelector("tbody");
            tableBody.innerHTML = '<tr><td colspan="5" class="px-6 py-12 text-center text-slate-500"><div class="flex items-center justify-center gap-2"><div class="animate-spin rounded-full h-4 w-4 border-b-2 border-primary"></div>Đang tải dữ liệu...</div></td></tr>';

            // Gọi AJAX
            fetch(window.location.pathname + '?page=' + pageNum +
                  (maKho ? '&ma_kho=' + encodeURIComponent(maKho) : '') +
                  (maLoaiHang ? '&ma_loai_hang=' + encodeURIComponent(maLoaiHang) : ''), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                updateTable(data.data);
                updatePagination(data.pagination);
                updateUrl(maKho, maLoaiHang, pageNum);
            })
            .catch(error => {
                console.error('Error:', error);
                tableBody.innerHTML = '<tr><td colspan="5" class="px-6 py-12 text-center text-red-500">Có lỗi xảy ra khi tải dữ liệu</td></tr>';
            });
        }

        // Cập nhật URL mà không reload trang
        function updateUrl(maKho, maLoaiHang, page) {
            const url = new URL(window.location);
            url.searchParams.set('page', page);
            if (maKho) url.searchParams.set('ma_kho', maKho);
            else url.searchParams.delete('ma_kho');
            if (maLoaiHang) url.searchParams.set('ma_loai_hang', maLoaiHang);
            else url.searchParams.delete('ma_loai_hang');
            window.history.pushState({}, '', url);
        }

        // Lắng nghe sự kiện change cho các select lọc
        document.getElementById("filter-kho")?.addEventListener("change", applyFilters);
        document.getElementById("filter-loai")?.addEventListener("change", applyFilters);

        // Xử lý xuất Excel
        document.getElementById("export-excel-btn")?.addEventListener("click", function() {
            const kho = document.getElementById("filter-kho").value;
            const loai = document.getElementById("filter-loai").value;

            // Tạo URL với các bộ lọc hiện tại
            let exportUrl = 'export_excel.php';
            const params = [];

            if (kho) params.push('ma_kho=' + encodeURIComponent(kho));
            if (loai) params.push('ma_loai_hang=' + encodeURIComponent(loai));

            if (params.length > 0) {
                exportUrl += '?' + params.join('&');
            }

            // Mở link để tải file Excel
            window.open(exportUrl, '_blank');
        });
    });
    </script>
</body>

</html>