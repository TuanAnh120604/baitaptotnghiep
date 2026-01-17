<?php
include '../include/connect.php';
include '../include/permissions.php';
checkAccess('vattu');

/* Lấy danh sách loại hàng (chỉ lấy 3 loại cho vật tư: Nguyên liệu, Nhiên liệu, Phụ tùng) */
$stmt = $pdo->query("SELECT ma_loai_hang, ten_loai_hang FROM loai_hang WHERE ma_loai_hang IN ('M001', 'M002', 'M003') ORDER BY ten_loai_hang ASC");
$loai_hang_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Nhận từ khóa tìm kiếm */
$q = $_GET['q'] ?? '';

/* Phân trang */
$items_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

/* Đếm tổng số bản ghi */
if ($q !== '') {
  $count_stmt = $pdo->prepare("
      SELECT COUNT(*) as total
      FROM hang_hoa
      WHERE ma_loai_hang != 'M004'
        AND (ma_hang LIKE ? OR ten_hang LIKE ?)
  ");
  $like = "%$q%";
  $count_stmt->execute([$like, $like]);
} else {
  $count_stmt = $pdo->prepare("
      SELECT COUNT(*) as total
      FROM hang_hoa
      WHERE ma_loai_hang != 'M004'
  ");
  $count_stmt->execute();
}
$total_items = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_items / $items_per_page);

/* Lấy danh sách vật tư (có tìm kiếm nếu có q) - loại bỏ thành phẩm */
// Đảm bảo offset và items_per_page là số nguyên an toàn
$items_per_page = intval($items_per_page);
$offset = intval($offset);

if ($q !== '') {
  $stmt2 = $pdo->prepare("
      SELECT ma_hang, ten_hang, don_gia, don_vi_tinh, muc_du_tru_min, muc_du_tru_max
      FROM hang_hoa
      WHERE ma_loai_hang != 'M004'
        AND (ma_hang LIKE ? OR ten_hang LIKE ?)
      ORDER BY ma_hang ASC
      LIMIT $items_per_page OFFSET $offset
  ");
  $like = "%$q%";
  $stmt2->execute([$like, $like]);
} else {
  $stmt2 = $pdo->prepare("
      SELECT ma_hang, ten_hang, don_gia, don_vi_tinh, muc_du_tru_min, muc_du_tru_max
      FROM hang_hoa
      WHERE ma_loai_hang != 'M004'
      ORDER BY ma_hang ASC
      LIMIT $items_per_page OFFSET $offset
  ");
  $stmt2->execute();
}

$vat_tu_list = $stmt2->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html class="light" lang="vi">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Mặt hàng - Vật tư</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script>
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: {
                    "primary": "#137fec",
                    "background-light": "#f6f7f8",
                    "background-dark": "#101922",
                },
                fontFamily: {
                    "display": ["Inter", "sans-serif"]
                },
                borderRadius: {
                    "DEFAULT": "0.25rem",
                    "lg": "0.5rem",
                    "xl": "0.75rem",
                    "full": "9999px"
                },
            },
        },
    }
    </script>
</head>

<body class="bg-background-light dark:bg-background-dark text-[#111418] font-display antialiased overflow-hidden">
    <div class="flex h-screen w-full">
        <aside
            class="w-64 bg-white dark:bg-[#1a2632] border-r border-[#e5e7eb] dark:border-[#2a3b4d] flex flex-col flex-shrink-0 h-full transition-all duration-300">
            <?php include '../include/sidebar.php'; ?>
        </aside>
        <main class="flex-1 flex flex-col h-full overflow-hidden bg-background-light dark:bg-background-dark">
            <?php include '../include/header.php'; ?>
            <div class="flex-1 overflow-y-auto p-6 md:p-8">
                <!-- Thông báo thành công -->
                <?php if (isset($_GET['success'])): ?>
                <div id="successMessage"
                    class="fixed top-4 right-4 z-50 p-4 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 rounded-lg shadow-lg border border-green-300 dark:border-green-700 mb-4 transition-all duration-300 opacity-100"
                    style="transition: opacity 0.3s ease, transform 0.3s ease;">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-green-600 dark:text-green-400">check_circle</span>
                        <span class="font-medium">
                            <?php if (isset($_GET['deleted'])): ?>
                            Đã xóa vật tư "<?= htmlspecialchars($_GET['deleted']) ?>" thành công!
                            <?php else: ?>
                            Thêm vật tư thành công!
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Thông báo lỗi -->
                <?php if (isset($_GET['error'])): ?>
                <div id="errorMessage"
                    class="fixed top-4 right-4 z-50 p-4 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 rounded-lg shadow-lg border border-red-300 dark:border-red-700 mb-4 transition-all duration-300 opacity-100"
                    style="transition: opacity 0.3s ease, transform 0.3s ease;">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-red-600 dark:text-red-400">error</span>
                        <span class="font-medium"><?= htmlspecialchars($_GET['error']) ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <div class="max-w-[1200px] mx-auto flex flex-col gap-6">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <h1 class="text-2xl font-bold text-[#111418] dark:text-white tracking-tight">Danh sách vật
                                tư</h1>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <?php if (canCreate('vattu')): ?>
                            <button onclick="openModal()"
                                class="flex items-center justify-center gap-2 h-10 px-5 bg-primary text-white rounded-lg text-sm font-medium shadow-sm hover:bg-blue-600 transition-all focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                                <span class="material-symbols-outlined text-[20px]">add</span>
                                Thêm mới
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div
                        class="bg-white dark:bg-[#1a2632] rounded-xl border border-[#e5e7eb] dark:border-[#2a3b4d] shadow-sm p-4">
                        <form id="searchForm" class="flex flex-col lg:flex-row gap-4">
                            <div class="flex-1 relative">
                                <span
                                    class="absolute left-3 top-1/2 -translate-y-1/2 text-[#637588] dark:text-[#9ca3af] material-symbols-outlined">search</span>
                                <input id="searchInput" name="q" value="<?= htmlspecialchars($q) ?>"
                                    class="w-full h-10 pl-10 pr-4 rounded-lg border border-[#dce0e5] dark:border-[#2a3b4d] bg-[#f8fafc] dark:bg-[#0d141c] text-[#111418] dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
                                    placeholder="Tìm kiếm theo mã hoặc tên vật tư..." type="text"
                                    autocomplete="off" />
                            </div>
                        </form>
                    </div>
                    <div
                        class="bg-white dark:bg-[#1a2632] rounded-xl border border-[#e5e7eb] dark:border-[#2a3b4d] shadow-sm overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr
                                        class="bg-[#f8fafc] dark:bg-[#243447] border-b border-[#e5e7eb] dark:border-[#2a3b4d]">
                                        <th
                                            class="py-4 px-6 text-xs font-semibold uppercase tracking-wider text-[#637588] dark:text-[#9ca3af]">
                                            Mã hàng</th>
                                        <th
                                            class="py-4 px-6 text-xs font-semibold uppercase tracking-wider text-[#637588] dark:text-[#9ca3af]">
                                            Tên Vật tư</th>
                                        <th
                                            class="py-4 px-6 text-xs font-semibold uppercase tracking-wider text-[#637588] dark:text-[#9ca3af]">
                                            DVT</th>
                                        <th
                                            class="py-4 px-6 text-xs font-semibold uppercase tracking-wider text-[#637588] dark:text-[#9ca3af]">
                                            Đơn giá</th>
                                        <th
                                            class="py-4 px-6 text-xs font-semibold uppercase tracking-wider text-[#637588] dark:text-[#9ca3af]">
                                            Mức dự trù Max/Min</th>
                                        <th
                                            class="py-4 px-6 text-xs font-semibold uppercase tracking-wider text-[#637588] dark:text-[#9ca3af] text-right">
                                            Hành động</th>
                                    </tr>
                                </thead>
                                <tbody id="tableBody" class="divide-y divide-[#e5e7eb] dark:divide-[#2a3b4d]">
                                    <?php if (empty($vat_tu_list)): ?>
                                    <tr>
                                        <td colspan="5" class="py-6 text-center text-gray-500">Chưa có vật tư nào.</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($vat_tu_list as $vt): ?>
                                    <tr class="group hover:bg-[#f8fafc] dark:hover:bg-[#243447] transition-colors"
                                        data-ma-hang="<?= htmlspecialchars($vt['ma_hang']) ?>"
                                        data-ten-hang="<?= htmlspecialchars($vt['ten_hang']) ?>"
                                        data-don-gia="<?= htmlspecialchars($vt['don_gia']) ?>"
                                        data-don-vi-tinh="<?= htmlspecialchars($vt['don_vi_tinh']) ?>"
                                        data-muc-du-tru-max="<?= htmlspecialchars($vt['muc_du_tru_max']) ?>"
                                        data-muc-du-tru-min="<?= htmlspecialchars($vt['muc_du_tru_min']) ?>">
                                        <td class="py-4 px-6 text-sm font-medium text-primary">
                                            <?= htmlspecialchars($vt['ma_hang']) ?>
                                        </td>

                                        <td class="py-4 px-6 text-sm font-medium text-[#111418] dark:text-white">
                                            <?= htmlspecialchars($vt['ten_hang']) ?>
                                        </td>

                                        <td class="py-4 px-6 text-sm font-medium text-[#111418] dark:text-white">
                                            <?= htmlspecialchars($vt['don_vi_tinh']) ?>
                                        </td>

                                        <td class="py-4 px-6 text-sm text-[#111418] dark:text-white">
                                            <?= number_format($vt['don_gia'], 0, ',', '.') ?> đ
                                        </td>

                                        <td class="py-4 px-6 text-sm text-[#111418] dark:text-white">
                                            <div class="flex items-center gap-2">
                                            <span
                                                    class="px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">
                                                    Min: <?= htmlspecialchars($vt['muc_du_tru_min']) ?>
                                                </span>
                                                <span
                                                    class="px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">
                                                    Max: <?= htmlspecialchars($vt['muc_du_tru_max']) ?>
                                                </span>
                                                
                                            </div>
                                        </td>

                                        <td class="py-4 px-6 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <?php if (canEdit('vattu')): ?>
                                                <button onclick="openEditModal(this.closest('tr'))"
                                                    class="p-1.5 rounded-md text-[#637588] hover:text-primary hover:bg-primary/10">
                                                    <span class="material-symbols-outlined text-[20px]">edit</span>
                                                </button>
                                                <?php endif; ?>

                                                <?php if (canDelete('vattu')): ?>
                                                <a href="xoa_vattu.php?ma_hang=<?= urlencode($vt['ma_hang']) ?>&page=<?= $current_page ?>&q=<?= urlencode($q) ?>"
                                                    onclick="return confirm('Bạn có chắc muốn xóa vật tư này không?')"
                                                    class="p-1.5 rounded-md text-[#637588] hover:text-red-500 hover:bg-red-50">
                                                    <span class="material-symbols-outlined text-[20px]">delete</span>
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>

                            </table>
                        </div>
                        <div
                            class="px-6 py-4 border-t border-[#e5e7eb] dark:border-[#2a3b4d] flex items-center justify-between">
                            <?php
                            $start_item = $total_items > 0 ? $offset + 1 : 0;
                            $end_item = min($offset + $items_per_page, $total_items);
                            ?>
                            <span class="text-sm text-[#637588] dark:text-[#9ca3af]">
                                Hiển thị <?= $start_item ?>-<?= $end_item ?> trên <?= $total_items ?> kết quả
                            </span>
                            <?php if ($total_pages > 1): ?>
                            <div class="flex items-center gap-1">
                                <?php
                                // Tạo URL với query string
                                $query_params = [];
                                if ($q !== '') {
                                    $query_params['q'] = $q;
                                }
                                
                                // Nút Previous
                                $prev_page = $current_page - 1;
                                $prev_url = 'vattu.php?' . http_build_query(array_merge($query_params, ['page' => $prev_page]));
                                ?>
                                <a href="<?= $prev_page >= 1 ? $prev_url : '#' ?>"
                                    class="h-8 w-8 flex items-center justify-center rounded-lg text-[#637588] hover:bg-gray-100 dark:hover:bg-[#243447] <?= $current_page <= 1 ? 'opacity-50 cursor-not-allowed pointer-events-none' : '' ?>">
                                    <span class="material-symbols-outlined text-[20px]">chevron_left</span>
                                </a>
                                
                                <?php
                                // Hiển thị các nút trang
                                $max_visible = 5; // Số trang tối đa hiển thị
                                $start_page = max(1, $current_page - floor($max_visible / 2));
                                $end_page = min($total_pages, $start_page + $max_visible - 1);
                                
                                // Điều chỉnh lại nếu gần cuối
                                if ($end_page - $start_page < $max_visible - 1) {
                                    $start_page = max(1, $end_page - $max_visible + 1);
                                }
                                
                                // Hiển thị dấu ... ở đầu nếu cần
                                if ($start_page > 1): ?>
                                    <a href="vattu.php?<?= http_build_query(array_merge($query_params, ['page' => 1])) ?>"
                                        class="h-8 w-8 flex items-center justify-center rounded-lg text-[#637588] dark:text-[#9ca3af] hover:bg-gray-100 dark:hover:bg-[#243447] text-sm font-medium">
                                        1
                                    </a>
                                    <?php if ($start_page > 2): ?>
                                        <span class="h-8 w-8 flex items-center justify-center text-[#637588] dark:text-[#9ca3af]">...</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <a href="vattu.php?<?= http_build_query(array_merge($query_params, ['page' => $i])) ?>"
                                        class="h-8 w-8 flex items-center justify-center rounded-lg <?= $i == $current_page ? 'bg-primary text-white' : 'text-[#637588] dark:text-[#9ca3af] hover:bg-gray-100 dark:hover:bg-[#243447]' ?> text-sm font-medium">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php
                                // Hiển thị dấu ... ở cuối nếu cần
                                if ($end_page < $total_pages): ?>
                                    <?php if ($end_page < $total_pages - 1): ?>
                                        <span class="h-8 w-8 flex items-center justify-center text-[#637588] dark:text-[#9ca3af]">...</span>
                                    <?php endif; ?>
                                    <a href="vattu.php?<?= http_build_query(array_merge($query_params, ['page' => $total_pages])) ?>"
                                        class="h-8 w-8 flex items-center justify-center rounded-lg text-[#637588] dark:text-[#9ca3af] hover:bg-gray-100 dark:hover:bg-[#243447] text-sm font-medium">
                                        <?= $total_pages ?>
                                    </a>
                                <?php endif; ?>
                                
                                <?php
                                // Nút Next
                                $next_page = $current_page + 1;
                                $next_url = 'vattu.php?' . http_build_query(array_merge($query_params, ['page' => $next_page]));
                                ?>
                                <a href="<?= $next_page <= $total_pages ? $next_url : '#' ?>"
                                    class="h-8 w-8 flex items-center justify-center rounded-lg text-[#637588] hover:bg-gray-100 dark:hover:bg-[#243447] <?= $current_page >= $total_pages ? 'opacity-50 cursor-not-allowed pointer-events-none' : '' ?>">
                                    <span class="material-symbols-outlined text-[20px]">chevron_right</span>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <div
        class="w-full max-w-lg bg-white dark:bg-[#1a2632] rounded-xl shadow-2xl border border-[#e5e7eb] dark:border-[#2a3b4d] flex flex-col max-h-[90vh]">
        <div id="addModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm">

            <!-- MODAL CONTENT -->
            <div
                class="w-full max-w-lg bg-white dark:bg-[#1a2632] rounded-xl shadow-2xl border border-[#e5e7eb] dark:border-[#2a3b4d] flex flex-col max-h-[90vh]">
                <div class="flex items-center justify-between p-5 border-b border-[#e5e7eb] dark:border-[#2a3b4d]">
                    <h3 class="text-lg font-bold text-[#111418] dark:text-white">Thêm Vật tư Mới</h3>
                    <button onclick="closeModal()"
                        class="text-[#637588] hover:text-[#111418] dark:text-[#9ca3af] dark:hover:text-white transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto">
                    <form method="POST" action="luu_vattu.php" class="flex flex-col gap-4" novalidate>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium text-[#111418] dark:text-white">Loại hàng</label>
                            <select name="ma_loai_hang"
                                class="h-10 px-3 rounded-lg border border-[#dce0e5] dark:border-[#2a3b4d] bg-white dark:bg-[#0d141c] text-[#111418] dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all cursor-pointer">

                                <option value="">Chọn loại hàng</option>

                                <?php foreach ($loai_hang_list as $lh): ?>
                                <option value="<?= htmlspecialchars($lh['ma_loai_hang']) ?>">
                                    <?= htmlspecialchars($lh['ten_loai_hang']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium text-[#111418] dark:text-white">Tên hàng <span class="text-red-500">*</span></label>
                            <input name="ten_hang" id="add_ten_hang" required
                                class="h-10 px-3 rounded-lg border border-[#dce0e5] dark:border-[#2a3b4d] bg-white dark:bg-[#0d141c] text-[#111418] dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all placeholder:text-[#637588] dark:placeholder:text-[#64748b]"
                                placeholder="Nhập tên" type="text" />
                            <p id="add_ten_hang_error" class="mt-1 text-sm text-red-600 dark:text-red-400 hidden">Vui lòng nhập tên hàng</p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex flex-col gap-1.5">
                                <label class="text-sm font-medium text-[#111418] dark:text-white">Đơn vị tính <span class="text-red-500">*</span></label>
                                <input name="don_vi_tinh" id="add_don_vi_tinh" required
                                    class="h-10 px-3 rounded-lg border border-[#dce0e5] dark:border-[#2a3b4d] bg-white dark:bg-[#0d141c] text-[#111418] dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all placeholder:text-[#637588] dark:placeholder:text-[#64748b]"
                                    placeholder="VD: kg, cái, lít..." type="text" />
                                <p id="add_don_vi_tinh_error" class="mt-1 text-sm text-red-600 dark:text-red-400 hidden">Vui lòng nhập đơn vị tính</p>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="text-sm font-medium text-[#111418] dark:text-white">Đơn giá <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <input name="don_gia" id="add_don_gia" required
                                        class="w-full h-10 pl-3 pr-8 rounded-lg border border-[#dce0e5] dark:border-[#2a3b4d] bg-white dark:bg-[#0d141c] text-[#111418] dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all placeholder:text-[#637588] dark:placeholder:text-[#64748b]"
                                        placeholder="0" type="number" min="0" step="0.01" />
                                    <span
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-[#637588] dark:text-[#9ca3af]">đ</span>
                                </div>
                                <p id="add_don_gia_error" class="mt-1 text-sm text-red-600 dark:text-red-400 hidden">Vui lòng nhập đơn giá hợp lệ (số không âm)</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                                <label class="text-sm font-medium text-[#111418] dark:text-white">Mức dự trù Min <span class="text-red-500">*</span></label>
                                <input name="muc_du_tru_min" id="add_muc_du_tru_min" required
                                    class="h-10 px-3 rounded-lg border border-[#dce0e5] dark:border-[#2a3b4d] bg-white dark:bg-[#0d141c] text-[#111418] dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all placeholder:text-[#637588] dark:placeholder:text-[#64748b]"
                                    placeholder="Số lượng tối thiểu" type="number" min="0" step="0.01" />
                                <p id="add_muc_du_tru_min_error" class="mt-1 text-sm text-red-600 dark:text-red-400 hidden">Vui lòng nhập mức dự trù Min hợp lệ (số không âm)</p>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="text-sm font-medium text-[#111418] dark:text-white">Mức dự trù Max <span class="text-red-500">*</span></label>
                                <input name="muc_du_tru_max" id="add_muc_du_tru_max" required
                                    class="h-10 px-3 rounded-lg border border-[#dce0e5] dark:border-[#2a3b4d] bg-white dark:bg-[#0d141c] text-[#111418] dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all placeholder:text-[#637588] dark:placeholder:text-[#64748b]"
                                    placeholder="Số lượng tối đa" type="number" min="0" step="0.01" />
                                <p id="add_muc_du_tru_max_error" class="mt-1 text-sm text-red-600 dark:text-red-400 hidden">Vui lòng nhập mức dự trù Max hợp lệ (số không âm và lớn hơn Min)</p>
                            </div>
                            
                        </div>
                        <div
                            class="flex items-center justify-end gap-3 py-5 border-t border-[#e5e7eb] dark:border-[#2a3b4d] bg-[#f8fafc] dark:bg-[#243447] rounded-b-xl">
                            <button type="button" onclick="closeModal()"
                                class="h-10 px-5 rounded-lg border border-[#dce0e5] dark:border-[#2a3b4d] text-[#111418] dark:text-white text-sm font-medium hover:bg-gray-50 dark:hover:bg-[#2a3b4d] transition-colors focus:ring-2 focus:ring-offset-2 focus:ring-gray-200">
                                Hủy
                            </button>
                            <button type="submit"
                                class="h-10 px-5 rounded-lg bg-primary text-white text-sm font-medium hover:bg-blue-600 transition-colors focus:ring-2 focus:ring-offset-2 focus:ring-primary shadow-sm">
                                Lưu
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
    <!-- Edit Modal -->
    <div id="editModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div
            class="bg-white dark:bg-[#1a2632] w-full max-w-2xl rounded-xl shadow-2xl border border-[#e5e7eb] dark:border-[#2a3b4d] flex flex-col max-h-[90vh]">
            <div class="flex items-center justify-between p-6 border-b border-[#e5e7eb] dark:border-[#2a3b4d]">
                <h3 class="text-xl font-bold text-[#111418] dark:text-white">Chỉnh sửa vật tư</h3>
                <button onclick="closeEditModal()"
                    class="text-[#637588] hover:text-[#111418] dark:text-[#9ca3af] dark:hover:text-white transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <form method="POST" action="capnhat_vattu.php" class="flex flex-col h-full" novalidate>
                <input type="hidden" name="ma_hang" id="edit_ma_hang">

                <div class="p-6 overflow-y-auto">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium text-[#111418] dark:text-white">Mã vật tư</label>
                            <input id="edit_ma_hang_display" disabled
                                class="h-10 px-3 rounded-lg border border-[#dce0e5] dark:border-[#2a3b4d] bg-gray-100 dark:bg-[#243447] text-[#637588] dark:text-[#9ca3af] text-sm cursor-not-allowed">
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium text-[#111418] dark:text-white">Tên vật tư <span class="text-red-500">*</span></label>
                            <input name="ten_hang" id="edit_ten_hang" required
                                class="h-10 px-3 rounded-lg border border-[#dce0e5] dark:border-[#2a3b4d] bg-white dark:bg-[#0d141c] text-[#111418] dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                            <p id="edit_ten_hang_error" class="mt-1 text-sm text-red-600 dark:text-red-400 hidden">Vui lòng nhập tên vật tư</p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium text-[#111418] dark:text-white">Đơn giá <span class="text-red-500">*</span></label>
                            <input name="don_gia" type="number" id="edit_don_gia" min="0" step="0.01" required
                                class="h-10 px-3 rounded-lg border border-[#dce0e5] dark:border-[#2a3b4d] bg-white dark:bg-[#0d141c] text-[#111418] dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                            <p id="edit_don_gia_error" class="mt-1 text-sm text-red-600 dark:text-red-400 hidden">Vui lòng nhập đơn giá hợp lệ (số không âm)</p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium text-[#111418] dark:text-white">Đơn vị tính <span class="text-red-500">*</span></label>
                            <input name="don_vi_tinh" id="edit_don_vi_tinh" required
                                class="h-10 px-3 rounded-lg border border-[#dce0e5] dark:border-[#2a3b4d] bg-white dark:bg-[#0d141c] text-[#111418] dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                            <p id="edit_don_vi_tinh_error" class="mt-1 text-sm text-red-600 dark:text-red-400 hidden">Vui lòng nhập đơn vị tính</p>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium text-[#111418] dark:text-white">Mức dự trù Min <span class="text-red-500">*</span></label>
                            <input name="muc_du_tru_min" type="number" id="edit_muc_du_tru_min" min="0" step="0.01" required
                                class="h-10 px-3 rounded-lg border border-[#dce0e5] dark:border-[#2a3b4d] bg-white dark:bg-[#0d141c] text-[#111418] dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                            <p id="edit_muc_du_tru_min_error" class="mt-1 text-sm text-red-600 dark:text-red-400 hidden">Vui lòng nhập mức dự trù Min hợp lệ (số không âm)</p>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium text-[#111418] dark:text-white">Mức dự trù Max <span class="text-red-500">*</span></label>
                            <input name="muc_du_tru_max" type="number" id="edit_muc_du_tru_max" min="0" step="0.01" required
                                class="h-10 px-3 rounded-lg border border-[#dce0e5] dark:border-[#2a3b4d] bg-white dark:bg-[#0d141c] text-[#111418] dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                            <p id="edit_muc_du_tru_max_error" class="mt-1 text-sm text-red-600 dark:text-red-400 hidden">Vui lòng nhập mức dự trù Max hợp lệ (số không âm và lớn hơn Min)</p>
                        </div>

                        
                    </div>
                </div>

                <div
                    class="p-6 border-t border-[#e5e7eb] dark:border-[#2a3b4d] flex items-center justify-end gap-3 bg-[#f8fafc] dark:bg-[#243447] rounded-b-xl">
                    <button type="button" onclick="closeEditModal()"
                        class="px-5 h-10 flex items-center rounded-lg border border-[#dce0e5] dark:border-[#2a3b4d] bg-white dark:bg-[#243447] text-[#111418] dark:text-white text-sm font-medium hover:bg-gray-50 dark:hover:bg-[#2a3b4d] transition-all">
                        Hủy
                    </button>
                    <button type="submit"
                        class="px-5 h-10 rounded-lg bg-primary text-white text-sm font-medium shadow-sm hover:bg-blue-600 transition-all focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                        Lưu
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script>
    function openModal() {
        document.getElementById("addModal").classList.remove("hidden");
        document.getElementById("addModal").classList.add("flex");
    }

    function closeModal() {
        const modal = document.getElementById("addModal");
        modal.classList.add("hidden");
        modal.classList.remove("flex");
        
        // Reset form và ẩn thông báo lỗi
        const form = modal.querySelector('form');
        if (form) {
            form.reset();
            hideError('add_don_gia', 'add_don_gia_error');
            hideError('add_muc_du_tru_min', 'add_muc_du_tru_min_error');
            hideError('add_muc_du_tru_max', 'add_muc_du_tru_max_error');
        }
    }

    function openEditModal(row) {
        // Lấy dữ liệu từ data attributes
        const maHang = row.getAttribute('data-ma-hang');
        const tenHang = row.getAttribute('data-ten-hang');
        const donGia = row.getAttribute('data-don-gia');
        const donViTinh = row.getAttribute('data-don-vi-tinh');
        const mucDuTruMax = row.getAttribute('data-muc-du-tru-max');
        const mucDuTruMin = row.getAttribute('data-muc-du-tru-min');

        // Điền dữ liệu vào form
        document.getElementById('edit_ma_hang').value = maHang;
        document.getElementById('edit_ma_hang_display').value = maHang;
        document.getElementById('edit_ten_hang').value = tenHang;
        document.getElementById('edit_don_gia').value = donGia;
        document.getElementById('edit_don_vi_tinh').value = donViTinh;
        document.getElementById('edit_muc_du_tru_max').value = mucDuTruMax;
        document.getElementById('edit_muc_du_tru_min').value = mucDuTruMin;

        // Hiển thị modal
        document.getElementById("editModal").classList.remove("hidden");
        document.getElementById("editModal").classList.add("flex");
    }

    function closeEditModal() {
        const modal = document.getElementById("editModal");
        modal.classList.add("hidden");
        modal.classList.remove("flex");
        
        // Ẩn thông báo lỗi
        hideError('edit_ten_hang', 'edit_ten_hang_error');
        hideError('edit_don_vi_tinh', 'edit_don_vi_tinh_error');
        hideError('edit_don_gia', 'edit_don_gia_error');
        hideError('edit_muc_du_tru_min', 'edit_muc_du_tru_min_error');
        hideError('edit_muc_du_tru_max', 'edit_muc_du_tru_max_error');
    }

    // Đóng khi click ra ngoài modal
    document.getElementById("addModal")?.addEventListener("click", function(e) {
        if (e.target === this) closeModal();
    });

    document.getElementById("editModal")?.addEventListener("click", function(e) {
        if (e.target === this) closeEditModal();
    });

    // Helper functions để hiển thị/ẩn lỗi
    function showError(inputId, errorId, message) {
        const input = document.getElementById(inputId);
        const error = document.getElementById(errorId);
        if (input && error) {
            input.classList.add('border-red-500');
            input.classList.remove('border-[#dce0e5]', 'dark:border-[#2a3b4d]');
            error.textContent = message;
            error.classList.remove('hidden');
        } else if (input && !error) {
            // Tạo error element nếu chưa có
            const errorP = document.createElement('p');
            errorP.id = errorId;
            errorP.className = 'mt-1 text-sm text-red-600 dark:text-red-400';
            errorP.textContent = message;
            input.parentElement.appendChild(errorP);
            input.classList.add('border-red-500');
            input.classList.remove('border-[#dce0e5]', 'dark:border-[#2a3b4d]');
        }
    }

    function hideError(inputId, errorId) {
        const input = document.getElementById(inputId);
        const error = document.getElementById(errorId);
        if (input && error) {
            input.classList.remove('border-red-500');
            input.classList.add('border-[#dce0e5]', 'dark:border-[#2a3b4d]');
            error.classList.add('hidden');
        }
    }

    // Validation trường bắt buộc
    function validateRequired(inputId, errorId, message) {
        const input = document.getElementById(inputId);
        if (!input) return true;
        
        if (input.value.trim() === '') {
            showError(inputId, errorId, message);
            return false;
        } else {
            hideError(inputId, errorId);
            return true;
        }
    }

    // Validation đơn giá
    function validateDonGia(inputId, errorId) {
        const input = document.getElementById(inputId);
        if (!input) return true;
        
        if (input.value.trim() === '') {
            showError(inputId, errorId, 'Vui lòng nhập đơn giá');
            return false;
        }
        
        const value = parseFloat(input.value);
        if (isNaN(value) || value < 0) {
            showError(inputId, errorId, 'Đơn giá phải là số không âm');
            return false;
        } else {
            hideError(inputId, errorId);
            return true;
        }
    }

    // Validation Min
    function validateMin(inputId, errorId) {
        const input = document.getElementById(inputId);
        if (!input) return true;
        
        if (input.value.trim() === '') {
            showError(inputId, errorId, 'Vui lòng nhập mức dự trù Min');
            return false;
        }
        
        const value = parseFloat(input.value);
        if (isNaN(value) || value < 0) {
            showError(inputId, errorId, 'Mức dự trù Min phải là số không âm');
            return false;
        } else {
            hideError(inputId, errorId);
            return true;
        }
    }

    // Validation Max và so sánh với Min
    function validateMax(maxInputId, maxErrorId, minInputId, minErrorId) {
        const maxInput = document.getElementById(maxInputId);
        if (!maxInput) return true;
        
        if (maxInput.value.trim() === '') {
            showError(maxInputId, maxErrorId, 'Vui lòng nhập mức dự trù Max');
            return false;
        }
        
        const minInput = document.getElementById(minInputId);
        const maxValue = parseFloat(maxInput.value) || 0;
        const minValue = minInput ? (parseFloat(minInput.value) || 0) : 0;
        
        if (isNaN(maxValue) || maxValue < 0) {
            showError(maxInputId, maxErrorId, 'Mức dự trù Max phải là số không âm');
            return false;
        } else if (minValue > 0 && maxValue > 0 && minValue >= maxValue) {
            showError(maxInputId, maxErrorId, 'Mức dự trù Max phải lớn hơn Min');
            if (minInputId) {
                const minError = document.getElementById(minErrorId);
                if (minError) {
                    minError.textContent = 'Mức dự trù Min phải nhỏ hơn Max';
                    minError.classList.remove('hidden');
                }
            }
            return false;
        } else {
            hideError(maxInputId, maxErrorId);
            if (minInputId && minValue > 0 && maxValue > 0 && minValue < maxValue) {
                hideError(minInputId, minErrorId);
            }
            return true;
        }
    }

    // Validation cho form thêm mới
    const addForm = document.querySelector('#addModal form');
    if (addForm) {
        const addDonGia = document.getElementById('add_don_gia');
        const addMinInput = document.getElementById('add_muc_du_tru_min');
        const addMaxInput = document.getElementById('add_muc_du_tru_max');
        
        if (addDonGia) {
            addDonGia.addEventListener('blur', () => validateDonGia('add_don_gia', 'add_don_gia_error'));
        }
        
        if (addMinInput) {
            addMinInput.addEventListener('blur', function() {
                validateMin('add_muc_du_tru_min', 'add_muc_du_tru_min_error');
                if (addMaxInput && addMaxInput.value !== '') {
                    validateMax('add_muc_du_tru_max', 'add_muc_du_tru_max_error', 'add_muc_du_tru_min', 'add_muc_du_tru_min_error');
                }
            });
        }
        
        if (addMaxInput) {
            addMaxInput.addEventListener('blur', () => {
                validateMax('add_muc_du_tru_max', 'add_muc_du_tru_max_error', 'add_muc_du_tru_min', 'add_muc_du_tru_min_error');
            });
        }
        
        // Validation cho các trường text
        const addTenHang = document.getElementById('add_ten_hang');
        const addDonViTinh = document.getElementById('add_don_vi_tinh');
        const addLoaiHang = addForm.querySelector('select[name="ma_loai_hang"]');
        
        if (addTenHang) {
            addTenHang.addEventListener('blur', () => validateRequired('add_ten_hang', 'add_ten_hang_error', 'Vui lòng nhập tên hàng'));
        }
        if (addDonViTinh) {
            addDonViTinh.addEventListener('blur', () => validateRequired('add_don_vi_tinh', 'add_don_vi_tinh_error', 'Vui lòng nhập đơn vị tính'));
        }
        if (addLoaiHang) {
            addLoaiHang.addEventListener('change', function() {
                if (this.value === '') {
                    showError('add_loai_hang', 'add_loai_hang_error', 'Vui lòng chọn loại hàng');
                } else {
                    hideError('add_loai_hang', 'add_loai_hang_error');
                }
            });
        }
        
        addForm.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Kiểm tra tất cả các trường bắt buộc
            if (addLoaiHang && addLoaiHang.value === '') {
                showError('add_loai_hang', 'add_loai_hang_error', 'Vui lòng chọn loại hàng');
                isValid = false;
            }
            if (addTenHang && !validateRequired('add_ten_hang', 'add_ten_hang_error', 'Vui lòng nhập tên hàng')) {
                isValid = false;
            }
            if (addDonViTinh && !validateRequired('add_don_vi_tinh', 'add_don_vi_tinh_error', 'Vui lòng nhập đơn vị tính')) {
                isValid = false;
            }
            if (addDonGia && !validateDonGia('add_don_gia', 'add_don_gia_error')) {
                isValid = false;
            }
            if (addMinInput && !validateMin('add_muc_du_tru_min', 'add_muc_du_tru_min_error')) {
                isValid = false;
            }
            if (addMaxInput && !validateMax('add_muc_du_tru_max', 'add_muc_du_tru_max_error', 'add_muc_du_tru_min', 'add_muc_du_tru_min_error')) {
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                return false;
            }
        });
    }

        // Validation cho form chỉnh sửa
        const editForm = document.querySelector('#editModal form');
        if (editForm) {
            const editTenHang = document.getElementById('edit_ten_hang');
            const editDonViTinh = document.getElementById('edit_don_vi_tinh');
            const editDonGia = document.getElementById('edit_don_gia');
            const editMinInput = document.getElementById('edit_muc_du_tru_min');
            const editMaxInput = document.getElementById('edit_muc_du_tru_max');
            
            if (editTenHang) {
                editTenHang.addEventListener('blur', () => validateRequired('edit_ten_hang', 'edit_ten_hang_error', 'Vui lòng nhập tên vật tư'));
            }
            if (editDonViTinh) {
                editDonViTinh.addEventListener('blur', () => validateRequired('edit_don_vi_tinh', 'edit_don_vi_tinh_error', 'Vui lòng nhập đơn vị tính'));
            }
            if (editDonGia) {
                editDonGia.addEventListener('blur', () => validateDonGia('edit_don_gia', 'edit_don_gia_error'));
            }
            
            if (editMinInput) {
                editMinInput.addEventListener('blur', function() {
                    validateMin('edit_muc_du_tru_min', 'edit_muc_du_tru_min_error');
                    if (editMaxInput && editMaxInput.value !== '') {
                        validateMax('edit_muc_du_tru_max', 'edit_muc_du_tru_max_error', 'edit_muc_du_tru_min', 'edit_muc_du_tru_min_error');
                    }
                });
            }
            
            if (editMaxInput) {
                editMaxInput.addEventListener('blur', () => {
                    validateMax('edit_muc_du_tru_max', 'edit_muc_du_tru_max_error', 'edit_muc_du_tru_min', 'edit_muc_du_tru_min_error');
                });
            }
            
            editForm.addEventListener('submit', function(e) {
                let isValid = true;
                
                // Kiểm tra tất cả các trường bắt buộc
                if (editTenHang && !validateRequired('edit_ten_hang', 'edit_ten_hang_error', 'Vui lòng nhập tên vật tư')) {
                    isValid = false;
                }
                if (editDonViTinh && !validateRequired('edit_don_vi_tinh', 'edit_don_vi_tinh_error', 'Vui lòng nhập đơn vị tính')) {
                    isValid = false;
                }
                if (editDonGia && !validateDonGia('edit_don_gia', 'edit_don_gia_error')) {
                    isValid = false;
                }
                if (editMinInput && !validateMin('edit_muc_du_tru_min', 'edit_muc_du_tru_min_error')) {
                    isValid = false;
                }
                if (editMaxInput && !validateMax('edit_muc_du_tru_max', 'edit_muc_du_tru_max_error', 'edit_muc_du_tru_min', 'edit_muc_du_tru_min_error')) {
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                    return false;
                }
            });
        }

    // Tự động ẩn thông báo sau 2.5 giây và xử lý tìm kiếm
    // Tự động tìm kiếm khi gõ - không cần nhấn Enter
    let searchTimeout;
    let originalTableContent = '';

    function searchTable() {
        const searchInput = document.getElementById('searchInput');
        const tableBody = document.getElementById('tableBody');
        const searchValue = searchInput ? searchInput.value.trim() : '';

        // Hiển thị loading state
        if (tableBody) {
            tableBody.innerHTML = '<tr><td colspan="6" class="py-6 text-center text-gray-500">Đang tải...</td></tr>';
        }

        // Tạo URL với tham số tìm kiếm
        const params = new URLSearchParams();
        if (searchValue) {
            params.append('q', searchValue);
        }

        // Gọi AJAX
        fetch('ajax_get_table.php?' + params.toString())
            .then(response => response.json())
            .then(data => {
                if (data.success && tableBody) {
                    // Cập nhật bảng
                    tableBody.innerHTML = data.table_body;
                    
                    // Cập nhật URL mà không reload
                    const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
                    window.history.pushState({}, '', newUrl);
                } else if (tableBody) {
                    tableBody.innerHTML = '<tr><td colspan="6" class="py-6 text-center text-red-600 dark:text-red-400">' + (data.error || 'Có lỗi xảy ra') + '</td></tr>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (tableBody) {
                    tableBody.innerHTML = '<tr><td colspan="6" class="py-6 text-center text-red-600 dark:text-red-400">Có lỗi xảy ra khi tải dữ liệu</td></tr>';
                }
            });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const searchForm = document.getElementById('searchForm');
        const tableBody = document.getElementById('tableBody');
        
        // Lưu nội dung bảng ban đầu
        if (tableBody) {
            originalTableContent = tableBody.innerHTML;
        }

        if (searchInput) {
            // Tự động tìm kiếm khi gõ với debounce
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const searchValue = this.value.trim();
                
                // Nếu xóa hết text, hiển thị lại bảng ban đầu ngay lập tức
                if (searchValue === '') {
                    if (tableBody && originalTableContent) {
                        tableBody.innerHTML = originalTableContent;
                    }
                    // Reset URL về ban đầu
                    window.history.pushState({}, '', window.location.pathname);
                } else {
                    // Nếu có text, đợi 300ms sau khi người dùng ngừng gõ
                    searchTimeout = setTimeout(() => {
                        searchTable();
                    }, 300);
                }
            });
        }

        // Ngăn form submit mặc định
        if (searchForm) {
            searchForm.addEventListener('submit', function(e) {
                e.preventDefault();
                clearTimeout(searchTimeout);
                searchTable();
            });
        }

        // Đọc tham số URL để khôi phục trang và từ khóa sau reload
        const urlParams = new URLSearchParams(window.location.search);
        const pageParam = parseInt(urlParams.get('page') || '1', 10);
        if (!isNaN(pageParam) && pageParam > 0) {
            currentPage = pageParam;
        }
        const qParam = urlParams.get('q') || '';
        if (searchInput && qParam) {
            searchInput.value = qParam;
            searchTable();
        }

        const successMessage = document.getElementById('successMessage');
        const errorMessage = document.getElementById('errorMessage');

        if (successMessage) {
            setTimeout(function() {
                successMessage.style.opacity = '0';
                successMessage.style.transform = 'translateX(100%)';
                setTimeout(function() {
                    successMessage.style.display = 'none';
                }, 300);
            }, 2500);
        }

        if (errorMessage) {
            setTimeout(function() {
                errorMessage.style.opacity = '0';
                errorMessage.style.transform = 'translateX(100%)';
                setTimeout(function() {
                    errorMessage.style.display = 'none';
                }, 300);
            }, 2500);
        }
    });
    </script>

</body>

</html>