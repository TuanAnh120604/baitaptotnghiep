<?php
include '../include/connect.php';

header('Content-Type: application/json');

$thanh_pham_list = [];
$error_message = '';
$q = '';
$total_items = 0;
$items_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Lấy mã loại hàng của "Thành phẩm"
$stmt = $pdo->prepare("SELECT ma_loai_hang, ten_loai_hang FROM loai_hang WHERE ten_loai_hang = ?");
$stmt->execute(['Thành phẩm']);
$loai_hang_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Nếu có loại hàng thì lấy mã
$ma_thanh_pham = $loai_hang_list[0]['ma_loai_hang'] ?? null;

// Nhận từ khóa tìm kiếm
$q = trim($_GET['q'] ?? '');

if ($ma_thanh_pham) {
    // Đếm tổng số bản ghi
    if ($q !== '') {
        $stmt_count = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM hang_hoa
            WHERE ma_loai_hang = ?
              AND (ma_hang LIKE ? OR ten_hang LIKE ?)
        ");
        $like = "%$q%";
        $stmt_count->execute([$ma_thanh_pham, $like, $like]);
        $total_items = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];

        $stmt2 = $pdo->prepare("
            SELECT ma_hang, ten_hang, don_vi_tinh, don_gia, muc_du_tru_min, muc_du_tru_max
            FROM hang_hoa
            WHERE ma_loai_hang = ?
              AND (ma_hang LIKE ? OR ten_hang LIKE ?)
            ORDER BY ma_hang ASC
            LIMIT " . intval($items_per_page) . " OFFSET " . intval($offset) . "
        ");
        $stmt2->execute([$ma_thanh_pham, $like, $like]);
    } else {
        $stmt_count = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM hang_hoa
            WHERE ma_loai_hang = ?
        ");
        $stmt_count->execute([$ma_thanh_pham]);
        $total_items = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];

        $stmt2 = $pdo->prepare("
            SELECT ma_hang, ten_hang, don_vi_tinh, don_gia, muc_du_tru_min, muc_du_tru_max
            FROM hang_hoa
            WHERE ma_loai_hang = ?
            ORDER BY ma_hang ASC
            LIMIT " . intval($items_per_page) . " OFFSET " . intval($offset) . "
        ");
        $stmt2->execute([$ma_thanh_pham]);
    }

    $thanh_pham_list = $stmt2->fetchAll(PDO::FETCH_ASSOC);
}

// Tạo HTML cho bảng
ob_start();
?>
<tbody class="divide-y divide-[#e5e7eb] dark:divide-[#2a3b4d]">
    <?php if (empty($thanh_pham_list)): ?>
    <tr>
        <td colspan="5" class="py-6 text-center text-gray-500">Chưa có thành phẩm nào.</td>
    </tr>
    <?php else: ?>
    <?php foreach ($thanh_pham_list as $tp): ?>
    <tr class="group hover:bg-[#f8fafc] dark:hover:bg-[#243447] transition-colors">
        <td class="py-4 px-6 text-sm font-medium text-primary">
            <?= htmlspecialchars($tp['ma_hang']) ?>
        </td>
        <td class="py-4 px-6 text-sm font-medium text-[#111418] dark:text-white">
            <?= htmlspecialchars($tp['ten_hang']) ?>
        </td>
        <td class="py-4 px-6 text-sm text-[#111418] dark:text-white">
            <?= htmlspecialchars($tp['don_vi_tinh']) ?>
        </td>
        <td class="py-4 px-6 text-sm text-[#111418] dark:text-white text-right">
            <?= number_format($tp['don_gia'], 0, ',', '.') ?> đ
        </td>
        <td class="py-4 px-6 text-right">
            <div class="flex items-center justify-end gap-2">
                <button
                    onclick="openEditModal('<?= htmlspecialchars($tp['ma_hang'], ENT_QUOTES) ?>', '<?= htmlspecialchars($tp['ten_hang'], ENT_QUOTES) ?>', '<?= htmlspecialchars($tp['don_vi_tinh'], ENT_QUOTES) ?>', '<?= htmlspecialchars($tp['don_gia'], ENT_QUOTES) ?>', '<?= htmlspecialchars($tp['muc_du_tru_min'] ?? 0, ENT_QUOTES) ?>', '<?= htmlspecialchars($tp['muc_du_tru_max'] ?? 0, ENT_QUOTES) ?>')"
                    class="p-1.5 rounded-md text-[#637588] hover:text-primary hover:bg-primary/10"
                    title="Chỉnh sửa">
                    <span class="material-symbols-outlined text-[20px]">edit</span>
                </button>
                <a href="xoa_thanhpham.php?ma_hang=<?= urlencode($tp['ma_hang']) ?>&page=<?= $current_page ?>&q=<?= urlencode($q) ?>"
                    onclick="return confirm('Bạn có chắc muốn xóa thành phẩm này không?')"
                    class="p-1.5 rounded-md text-[#637588] hover:text-red-500 hover:bg-red-50">
                    <span class="material-symbols-outlined text-[20px]">delete</span>
                </a>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php endif; ?>
</tbody>
<?php
$table_body = ob_get_clean();

// Tính tổng số trang cho ajax
$total_pages = $items_per_page > 0 ? ceil($total_items / $items_per_page) : 1;
$total_pages = $total_pages > 0 ? $total_pages : 1;

// Tạo HTML phân trang (dùng JS goToPage)
ob_start();
?>
<span class="text-sm text-[#637588] dark:text-[#9ca3af]">
    <?php
    $start_item = $total_items > 0 ? $offset + 1 : 0;
    $end_item = min($offset + $items_per_page, $total_items);
    if ($total_items > 0): ?>
        Hiển thị <?= $start_item ?>-<?= $end_item ?> trên <?= $total_items ?> kết quả
    <?php else: ?>
        Không có kết quả
    <?php endif; ?>
</span>
<?php if ($total_pages > 1): ?>
    <div class="flex items-center gap-1">
        <?php
        // Nút Previous
        $prev_page = $current_page > 1 ? $current_page - 1 : 1;
        ?>
        <button onclick="goToPage(<?= $prev_page ?>)"
            class="h-8 w-8 flex items-center justify-center rounded-lg text-[#637588] hover:bg-gray-100 dark:hover:bg-[#243447] <?= $current_page <= 1 ? 'opacity-50 cursor-not-allowed pointer-events-none' : '' ?>">
            <span class="material-symbols-outlined text-[20px]">chevron_left</span>
        </button>

        <?php
        // Các nút số trang
        $max_visible_pages = 5;
        $start_page = max(1, $current_page - floor($max_visible_pages / 2));
        $end_page = min($total_pages, $start_page + $max_visible_pages - 1);
        if ($end_page - $start_page < $max_visible_pages - 1) {
            $start_page = max(1, $end_page - $max_visible_pages + 1);
        }

        if ($start_page > 1): ?>
            <button onclick="goToPage(1)"
                class="h-8 w-8 flex items-center justify-center rounded-lg text-[#637588] dark:text-[#9ca3af] hover:bg-gray-100 dark:hover:bg-[#243447] text-sm font-medium">
                1
            </button>
            <?php if ($start_page > 2): ?>
                <span class="h-8 w-8 flex items-center justify-center text-[#637588] dark:text-[#9ca3af]">...</span>
            <?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
            <button onclick="goToPage(<?= $i ?>)"
                class="h-8 w-8 flex items-center justify-center rounded-lg <?= $i == $current_page ? 'bg-primary text-white' : 'text-[#637588] dark:text-[#9ca3af] hover:bg-gray-100 dark:hover:bg-[#243447]' ?> text-sm font-medium">
                <?= $i ?>
            </button>
        <?php endfor; ?>

        <?php if ($end_page < $total_pages): ?>
            <?php if ($end_page < $total_pages - 1): ?>
                <span class="h-8 w-8 flex items-center justify-center text-[#637588] dark:text-[#9ca3af]">...</span>
            <?php endif; ?>
            <button onclick="goToPage(<?= $total_pages ?>)"
                class="h-8 w-8 flex items-center justify-center rounded-lg text-[#637588] dark:text-[#9ca3af] hover:bg-gray-100 dark:hover:bg-[#243447] text-sm font-medium">
                <?= $total_pages ?>
            </button>
        <?php endif; ?>

        <?php
        // Nút Next
        $next_page = $current_page < $total_pages ? $current_page + 1 : $total_pages;
        ?>
        <button onclick="goToPage(<?= $next_page ?>)"
            class="h-8 w-8 flex items-center justify-center rounded-lg text-[#637588] hover:bg-gray-100 dark:hover:bg-[#243447] <?= $current_page >= $total_pages ? 'opacity-50 cursor-not-allowed pointer-events-none' : '' ?>">
            <span class="material-symbols-outlined text-[20px]">chevron_right</span>
        </button>
    </div>
<?php endif; ?>
<?php
$pagination_html = ob_get_clean();

// Trả về JSON
echo json_encode([
    'success' => empty($error_message),
    'error' => $error_message,
    'table_body' => $table_body,
    'total_items' => $total_items,
    'pagination_html' => $pagination_html,
    'current_page' => $current_page,
    'total_pages' => $total_pages
]);
?>
