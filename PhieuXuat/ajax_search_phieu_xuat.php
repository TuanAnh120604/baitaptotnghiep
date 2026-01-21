<?php
include '../include/connect.php';
include '../include/permissions.php';
checkAccess('phieuxuat');

$role = trim($_SESSION['role'] ?? '');
$ma_nd = $_SESSION['MaND'] ?? null;

$q    = trim($_GET['q'] ?? '');
$ngay = $_GET['ngay'] ?? '';

$where = 'WHERE 1=1';
$params = [];

// Phân quyền
if ($role === 'Thủ kho' && $ma_nd) {
  $where .= ' AND px.ma_kho IN (SELECT ma_kho FROM kho WHERE ma_nd = :ma_nd)';
  $params[':ma_nd'] = $ma_nd;
} elseif ($role === 'Quản lý kho' && $ma_nd) {
  $where .= ' AND px.ma_kho IN (
        SELECT k.ma_kho
        FROM kho k
        JOIN phan_quyen pq 
            ON k.ma_vung = pq.ma_vung 
           AND k.ma_loai_kho = pq.ma_loai_kho
        WHERE pq.ma_nd = :ma_nd
    )';
  $params[':ma_nd'] = $ma_nd;
}

// Tìm kiếm realtime
if ($q !== '') {
  $where .= ' AND (
        px.ma_phieu_xuat LIKE :kw
        OR px.nguoi_nhan LIKE :kw
        OR px.don_vi_nhan LIKE :kw
        OR k.ten_kho LIKE :kw
        OR dl.ten_dai_ly LIKE :kw
    )';
  $params[':kw'] = "%$q%";
}

// Lọc theo ngày
if ($ngay !== '') {
  $where .= ' AND DATE(px.ngay_xuat) = :ngay';
  $params[':ngay'] = $ngay;
}

$sql = "
    SELECT px.*, k.ten_kho, dl.ten_dai_ly
    FROM phieu_xuat px
    LEFT JOIN kho k ON px.ma_kho = k.ma_kho
    LEFT JOIN dai_ly dl ON px.ma_dai_ly = dl.ma_dai_ly
    $where
    ORDER BY px.ngay_xuat DESC, px.ma_phieu_xuat DESC
    LIMIT 50
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$list = $stmt->fetchAll(PDO::FETCH_ASSOC);

function formatDate($d)
{
  return $d ? date('d/m/Y', strtotime($d)) : '-';
}

function getDVNhan($row)
{
  return !empty($row['ten_dai_ly']) ? $row['ten_dai_ly'] : ($row['don_vi_nhan'] ?? '-');
}

if (!$list) {
  echo '<tr>
        <td colspan="9" class="px-4 py-8 text-center text-gray-500">
            Không có dữ liệu phiếu xuất
        </td>
    </tr>';
  exit;
}

foreach ($list as $row):
?>
  <tr class="group hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
    <td class="px-4 py-4 text-center"><input class="rounded border-gray-300 text-primary focus:ring-primary dark:border-gray-600 dark:bg-gray-700" type="checkbox"></td>
    <td class="px-4 py-4 font-medium text-primary"><?= htmlspecialchars($row['ma_phieu_xuat']) ?></td>
    <td class="px-4 py-4 text-center"><?= formatDate($row['ngay_xuat']) ?></td>
    <td class="px-4 py-4"><?= htmlspecialchars($row['nguoi_nhan']) ?></td>
    <td class="px-4 py-4"><?= htmlspecialchars(getDVNhan($row)) ?></td>
    <td class="px-4 py-4"><?= htmlspecialchars($row['ten_kho']) ?></td>
    <td class="px-4 py-4"><?= htmlspecialchars($row['ten_dai_ly'] ?? '-') ?></td>
    <td class="px-4 py-4 text-center">
      <?php if (($row['trang_thai'] ?? '') === 'da_xac_nhan'): ?>
        <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">
          Đã xác nhận
        </span>
      <?php else: ?>
        <span class="px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded-full">
          Chờ xác nhận
        </span>
      <?php endif; ?>
    </td>
    <td class="px-4 py-4 text-right">…</td>
  </tr>
<?php endforeach; ?>