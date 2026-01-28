<?php
include '../include/connect.php';
include '../include/permissions.php';
checkAccess('phieunhap');

$role = trim($_SESSION['role'] ?? '');
$ma_nd = $_SESSION['MaND'] ?? null;

$q    = trim($_GET['q'] ?? '');
$ngay = $_GET['ngay'] ?? '';

$where = 'WHERE 1=1';
$params = [];

// Phân quyền
if ($role === 'Thủ kho' && $ma_nd) {
  $where .= ' AND pn.ma_kho IN (SELECT ma_kho FROM kho WHERE ma_nd = :ma_nd)';
  $params[':ma_nd'] = $ma_nd;
} elseif ($role === 'Quản lý kho' && $ma_nd) {
  $where .= ' AND pn.ma_kho IN (
        SELECT k.ma_kho
        FROM kho k
        JOIN phan_quyen pq ON k.ma_vung = pq.ma_vung AND k.ma_loai_kho = pq.ma_loai_kho
        WHERE pq.ma_nd = :ma_nd
    )';
  $params[':ma_nd'] = $ma_nd;
}

// Search text
if ($q !== '') {
  $where .= ' AND (
        pn.ma_phieu_nhap LIKE :kw
        OR pn.nguoi_giao LIKE :kw
        OR pn.don_vi_giao LIKE :kw
        OR ncc.ten_ncc LIKE :kw
    )';
  $params[':kw'] = "%$q%";
}

// Filter ngày
if ($ngay !== '') {
  $where .= ' AND DATE(pn.ngay_nhap) = :ngay';
  $params[':ngay'] = $ngay;
}

$sql = "
    SELECT pn.*, k.ten_kho, ncc.ten_ncc
    FROM phieu_nhap pn
    LEFT JOIN kho k ON pn.ma_kho = k.ma_kho
    LEFT JOIN nha_cung_cap ncc ON pn.ma_ncc = ncc.ma_ncc
    $where
    ORDER BY pn.ngay_nhap DESC, pn.ma_phieu_nhap DESC
    LIMIT 50
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$list = $stmt->fetchAll(PDO::FETCH_ASSOC);

function formatDate($d)
{
  return $d ? date('d/m/Y', strtotime($d)) : '-';
}

if (!$list) {
  echo '<tr><td colspan="9" class="px-4 py-8 text-center text-gray-500">Không có dữ liệu</td></tr>';
  exit;
}

foreach ($list as $row):
?>
  <tr class="group hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
    <td class="px-4 py-4 text-center"><input class="rounded border-gray-300 text-primary focus:ring-primary dark:border-gray-600 dark:bg-gray-700" type="checkbox"></td>
    <td class="px-4 py-4 font-medium text-primary"><?= htmlspecialchars($row['ma_phieu_nhap']) ?></td>
    <td class="px-4 py-4 text-center"><?= formatDate($row['ngay_nhap']) ?></td>
    <td class="px-4 py-4"><?= htmlspecialchars($row['nguoi_giao']) ?></td>
    <td class="px-4 py-4"><?= htmlspecialchars($row['ten_ncc'] ?: $row['don_vi_giao']) ?></td>
    <td class="px-4 py-4"><?= htmlspecialchars($row['ten_kho']) ?></td>
    <td class="px-4 py-4"><?= htmlspecialchars($row['ten_ncc']) ?></td>
    <td class="px-4 py-4 text-center">
      <?php if ($row['trang_thai'] === 'da_xac_nhan'): ?>
        <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">Đã xác nhận</span>
      <?php else: ?>
        <span class="px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded-full">Chờ xác nhận</span>
      <?php endif; ?>
    </td>
    <td class="px-4 py-4 text-right sticky right-0 bg-white dark:bg-surface-dark group-hover:bg-gray-50 dark:group-hover:bg-transparent shadow-l">
      <div class="flex items-center justify-end gap-2">
        <button onclick="openDetailModal('nhap', '<?= htmlspecialchars($row['ma_phieu_nhap']) ?>')" class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 transition-colors" title="Xem chi tiết">
          <span class="material-symbols-outlined text-lg">visibility</span>
        </button>
        <?php if (canEdit('phieunhap')): ?>
          <button onclick="openEditModal('<?= htmlspecialchars($row['ma_phieu_nhap']) ?>')"
            class="p-1.5 rounded-md text-gray-500 hover:bg-blue-50 hover:text-primary dark:text-gray-400 dark:hover:bg-blue-900/30 dark:hover:text-blue-300 transition-colors"
            title="Sửa">
            <span class="material-symbols-outlined text-lg">edit</span>
          </button>
        <?php endif; ?>
        <?php if ($role === 'Thủ kho' && ($row['trang_thai'] ?? 'cho_xac_nhan') === 'cho_xac_nhan'): ?>
          <button onclick="xacNhanPhieu('nhap', '<?= htmlspecialchars($row['ma_phieu_nhap']) ?>')"
            class="p-1.5 rounded-md text-gray-500 hover:bg-green-50 hover:text-green-600 dark:text-gray-400 dark:hover:bg-green-900/30 dark:hover:text-green-300 transition-colors"
            title="Xác nhận phiếu">
            <span class="material-symbols-outlined text-lg">check_circle</span>
          </button>
        <?php endif; ?>
      </div>
    </td>
  </tr>
<?php endforeach; ?>