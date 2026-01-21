<?php
include '../include/connect.php';

header('Content-Type: application/json');

$vat_tu_list = [];
$error_message = '';
$q = '';
$total_items = 0;
$current_page = 1;
$items_per_page = 10;

// Nhận từ khóa tìm kiếm
$q = trim($_GET['q'] ?? '');

// Phân trang - luôn về trang 1 khi tìm kiếm
$current_page = 1;
$offset = 0;

// Đếm tổng số bản ghi
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

// Lấy danh sách vật tư - khi tìm kiếm thì lấy tất cả, không phân trang
if ($q !== '') {
    // Khi có từ khóa tìm kiếm, lấy tất cả kết quả không giới hạn
    $stmt2 = $pdo->prepare("
        SELECT ma_hang, ten_hang, don_gia, don_vi_tinh, muc_du_tru_min, muc_du_tru_max
        FROM hang_hoa
        WHERE ma_loai_hang != 'M004'
          AND (ma_hang LIKE ? OR ten_hang LIKE ?)
        ORDER BY ma_hang ASC
    ");
    $like = "%$q%";
    $stmt2->execute([$like, $like]);
} else {
    // Khi không tìm kiếm, chỉ lấy trang 1
    $items_per_page = intval($items_per_page);
    $offset = intval($offset);
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

// Tạo HTML cho bảng
ob_start();
?>
<tbody class="divide-y divide-[#e5e7eb] dark:divide-[#2a3b4d]">
    <?php if (empty($vat_tu_list)): ?>
    <tr>
        <td colspan="6" class="py-6 text-center text-gray-500">Chưa có vật tư nào.</td>
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
                <span class="px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">
                    Max: <?= htmlspecialchars($vt['muc_du_tru_max']) ?>
                </span>
                <span class="px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">
                    Min: <?= htmlspecialchars($vt['muc_du_tru_min']) ?>
                </span>
            </div>
        </td>
        <td class="py-4 px-6 text-right">
            <div class="flex items-center justify-end gap-2">
                <button onclick="openEditModal(this.closest('tr'))"
                    class="p-1.5 rounded-md text-[#637588] hover:text-primary hover:bg-primary/10">
                    <span class="material-symbols-outlined text-[20px]">edit</span>
                </button>
                <a href="xoa_vattu.php?ma_hang=<?= urlencode($vt['ma_hang']) ?>"
                    onclick="return confirm('Bạn có chắc muốn xóa vật tư này không?')"
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

// Trả về JSON - chỉ trả về bảng
echo json_encode([
    'success' => empty($error_message),
    'error' => $error_message,
    'table_body' => $table_body,
    'total_items' => $total_items
]);
?>
