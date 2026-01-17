<?php
include '../include/connect.php';

// Lấy filter từ GET
$loai_filter = $_GET['loai'] ?? '';
$keyword     = trim($_GET['q'] ?? '');
$ngay        = $_GET['ngay'] ?? '';

$sql = "
    SELECT 
        pn.ma_phieu_nhap,
        pn.ngay_nhap,
        pn.nguoi_giao,
        pn.don_vi_giao,
        pn.loai_nhap,
        k.ten_kho,
        ncc.ten_ncc
    FROM phieu_nhap pn
    LEFT JOIN kho k ON pn.ma_kho = k.ma_kho
    LEFT JOIN nha_cung_cap ncc ON pn.ma_ncc = ncc.ma_ncc
    WHERE 1=1
";

$params = [];

if (!empty($loai_filter)) {
  $sql .= " AND pn.loai_nhap = :loai";
  $params[':loai'] = $loai_filter;
}

if (!empty($keyword)) {
  $sql .= " AND (
        pn.nguoi_giao LIKE :kw OR 
        pn.don_vi_giao LIKE :kw OR 
        ncc.ten_ncc LIKE :kw
    )";
  $params[':kw'] = "%$keyword%";
}

if ($ngay !== '') {
  $sql .= " AND DATE(pn.ngay_nhap) = :ngay";
  $params[':ngay'] = $ngay;
}

$sql .= " ORDER BY pn.ngay_nhap DESC, pn.ma_phieu_nhap DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Xuất header Excel
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=phieu_nhap_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// BOM UTF-8 để Excel không lỗi font
echo "\xEF\xBB\xBF";
?>

<table border="1">
  <thead>
    <tr style="font-weight:bold;background:#eee">
      <th>Mã phiếu</th>
      <th>Ngày nhập</th>
      <th>Người giao</th>
      <th>Đơn vị giao</th>
      <th>Loại nhập</th>
      <th>Kho nhập</th>
      <th>Nhà cung cấp</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($data as $row): ?>
      <tr>
        <td><?= $row['ma_phieu_nhap'] ?></td>
        <td><?= date('d/m/Y', strtotime($row['ngay_nhap'])) ?></td>
        <td><?= $row['nguoi_giao'] ?></td>
        <td><?= $row['don_vi_giao'] ?></td>
        <td><?= $row['loai_nhap'] ?></td>
        <td><?= $row['ten_kho'] ?></td>
        <td><?= $row['ten_ncc'] ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>