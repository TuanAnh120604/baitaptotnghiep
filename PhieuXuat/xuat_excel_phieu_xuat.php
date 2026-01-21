<?php
include '../include/connect.php';

$loai = $_GET['loai'] ?? '';
$ngay = $_GET['ngay'] ?? '';
$q    = trim($_GET['q'] ?? '');

$sql = "
    SELECT 
        px.ma_phieu_xuat,
        px.ngay_xuat,
        px.nguoi_nhan,
        px.don_vi_nhan,
        px.loai_xuat,
        k.ten_kho,
        dl.ten_dai_ly
    FROM phieu_xuat px
    LEFT JOIN kho k ON px.ma_kho = k.ma_kho
    LEFT JOIN dai_ly dl ON px.ma_dai_ly = dl.ma_dai_ly
    WHERE 1=1
";

$params = [];

if ($loai !== '') {
  $sql .= " AND px.loai_xuat = :loai";
  $params[':loai'] = $loai;
}

if ($ngay !== '') {
  $sql .= " AND DATE(px.ngay_xuat) = :ngay";
  $params[':ngay'] = $ngay;
}

if ($q !== '') {
  $sql .= " AND (
        px.ma_phieu_xuat LIKE :q OR
        px.nguoi_nhan LIKE :q OR
        k.ten_kho LIKE :q OR
        dl.ten_dai_ly LIKE :q
    )";
  $params[':q'] = "%$q%";
}

$sql .= " ORDER BY px.ngay_xuat DESC, px.ma_phieu_xuat DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=phieu_xuat_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");
echo "\xEF\xBB\xBF";
?>

<table border="1">
  <thead>
    <tr style="font-weight:bold;background:#eee">
      <th>Mã phiếu</th>
      <th>Ngày xuất</th>
      <th>Người nhận</th>
      <th>Đơn vị nhận</th>
      <th>Loại xuất</th>
      <th>Kho xuất</th>
      <th>Đại lý nhận</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($data as $row): ?>
      <tr>
        <td><?= $row['ma_phieu_xuat'] ?></td>
        <td><?= date('d/m/Y', strtotime($row['ngay_xuat'])) ?></td>
        <td><?= $row['nguoi_nhan'] ?></td>
        <td><?= $row['don_vi_nhan'] ?></td>
        <td><?= $row['loai_xuat'] ?></td>
        <td><?= $row['ten_kho'] ?></td>
        <td><?= $row['ten_dai_ly'] ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>