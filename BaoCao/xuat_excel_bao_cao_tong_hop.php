<?php
/* =================================================================================
   FILE: export_bao_cao_day_du.php
   MÔ TẢ: Xuất báo cáo Tổng hợp và Chi tiết Kho ra cùng 1 file Excel
================================================================================= */

include '../include/connect.php';
include '../include/permissions.php';
checkAccess('thongke');

/* --------------------------------------------------------------------------
   1. CẤU HÌNH THAM SỐ VÀ QUYỀN HẠN
-------------------------------------------------------------------------- */
$role  = $_SESSION['role'] ?? '';
$ma_nd = $_SESSION['MaND'] ?? null;

if (!$ma_nd) die('Vui lòng đăng nhập để sử dụng tính năng này.');

$ngay_bd = $_GET['ngay_bat_dau'] ?? date('Y-01-01');
$ngay_kt = $_GET['ngay_ket_thuc'] ?? date('Y-m-d');

// --- A. Xác định Loại kho ---
$stmtLK = $pdo->prepare("SELECT DISTINCT ma_loai_kho FROM phan_quyen WHERE ma_nd = ? LIMIT 1");
$stmtLK->execute([$ma_nd]);
$ma_loai_kho = $stmtLK->fetchColumn();

// Fallback cho thủ kho nếu chưa có trong bảng phân quyền
if (!$ma_loai_kho && $role === 'Thủ kho') {
    $stmtKho = $pdo->prepare("SELECT ma_loai_kho FROM kho WHERE ma_nd = ? LIMIT 1");
    $stmtKho->execute([$ma_nd]);
    $ma_loai_kho = $stmtKho->fetchColumn();
}
if (!$ma_loai_kho) die('Tài khoản chưa được gán loại kho quản lý.');

// --- B. Mapping Loại Hàng ---
$mapping = ['L001' => 'M001', 'L002' => 'M002', 'L003' => 'M003', 'L004' => 'M004'];
$ma_loai_hang = $mapping[$ma_loai_kho] ?? null;
if (!$ma_loai_hang) die("Lỗi: Loại kho $ma_loai_kho chưa ánh xạ loại hàng.");

// --- C. Xây dựng bộ lọc quyền (WHERE clause) ---
$kho_filter = ""; 
$params_common = [
    'ma_loai_hang' => $ma_loai_hang,
    'ma_loai_kho'  => $ma_loai_kho,
    'ngay_bd'      => $ngay_bd,
    'ngay_kt'      => $ngay_kt
];

if ($role === 'Thủ kho') {
    $kho_filter = " AND k.ma_nd = :ma_nd ";
    $params_common['ma_nd'] = $ma_nd;
} elseif ($role === 'Quản lý kho') {
    $kho_filter = " AND k.ma_vung IN (SELECT pq.ma_vung FROM phan_quyen pq WHERE pq.ma_nd = :ma_nd) ";
    $params_common['ma_nd'] = $ma_nd;
}

// Lấy tên loại kho để hiển thị
$stmtName = $pdo->prepare("SELECT ten_loai_kho FROM loai_kho WHERE ma_loai_kho = ?");
$stmtName->execute([$ma_loai_kho]);
$ten_loai_kho = $stmtName->fetchColumn();

/* --------------------------------------------------------------------------
   2. THIẾT LẬP HEADER EXCEL & CSS
-------------------------------------------------------------------------- */
$filename = "Bao_cao_kho_tong_hop_" . date('Ymd_His') . ".xls";
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=$filename");
echo "\xEF\xBB\xBF"; // BOM UTF-8
?>

<style>
    body { font-family: 'Times New Roman', Arial, serif; font-size: 14px; }
    table { border-collapse: collapse; width: 100%; margin-bottom: 30px; }
    th, td { border: 1px solid #000; padding: 5px; text-align: center; vertical-align: middle; }
    th { background-color: #d9edf7; font-weight: bold; height: 35px; }
    
    .section-title { font-size: 16px; font-weight: bold; background-color: #f0f0f0; text-align: left; padding: 10px; border: none; }
    .text-left { text-align: left; }
    .text-bold { font-weight: bold; }
    .text-red { color: red; }
    
    /* Màu sắc trạng thái */
    .status-safe { color: #008000; font-weight: bold; } /* Xanh lá */
    .status-low { color: #ff0000; font-weight: bold; }  /* Đỏ */
    .status-high { color: #d9534f; font-weight: bold; } /* Đỏ cam */
    
    .bg-header-1 { background-color: #cce5ff; } /* Màu xanh nhạt cho bảng 1 */
    .bg-header-2 { background-color: #fff3cd; } /* Màu vàng nhạt cho bảng 2 */
</style>

<div style="font-size: 20px; font-weight: bold; text-align: center; margin-bottom: 20px;">
    BÁO CÁO QUẢN TRỊ KHO HÀNG - <?php echo mb_strtoupper($ten_loai_kho); ?>
</div>
<div style="font-size: 16px; text-align: center; margin-bottom: 20px;">
    (Từ ngày <?php echo date('d/m/Y', strtotime($ngay_bd)); ?> đến ngày <?php echo date('d/m/Y', strtotime($ngay_kt)); ?>)
</div>

<?php
/* --------------------------------------------------------------------------
   PHẦN 3: BẢNG 1 - TỔNG HỢP TOÀN HỆ THỐNG
   (Cộng gộp tất cả các kho lại để xem tổng quan)
-------------------------------------------------------------------------- */

$sql1 = "
SELECT 
    hh.ma_hang, hh.ten_hang, hh.don_vi_tinh,
    
    /* Tổng Nhập Trong Kỳ (Tất cả kho user được xem) */
    (SELECT COALESCE(SUM(ct.so_luong_nhap), 0)
     FROM ct_phieu_nhap ct JOIN phieu_nhap pn ON ct.ma_phieu_nhap = pn.ma_phieu_nhap
     JOIN kho k ON pn.ma_kho = k.ma_kho
     WHERE ct.ma_hang = hh.ma_hang AND pn.ngay_nhap BETWEEN :ngay_bd AND :ngay_kt
       AND k.ma_loai_kho = :ma_loai_kho $kho_filter
    ) AS nhap_tk,

    /* Tổng Xuất Trong Kỳ */
    (SELECT COALESCE(SUM(ct.so_luong_xuat), 0)
     FROM ct_phieu_xuat ct JOIN phieu_xuat px ON ct.ma_phieu_xuat = px.ma_phieu_xuat
     JOIN kho k ON px.ma_kho = k.ma_kho
     WHERE ct.ma_hang = hh.ma_hang AND px.ngay_xuat BETWEEN :ngay_bd AND :ngay_kt
       AND k.ma_loai_kho = :ma_loai_kho $kho_filter
    ) AS xuat_tk,

    /* Tổng Nhập Lũy Kế (Tính tồn đầu) */
    (SELECT COALESCE(SUM(ct.so_luong_nhap), 0)
     FROM ct_phieu_nhap ct JOIN phieu_nhap pn ON ct.ma_phieu_nhap = pn.ma_phieu_nhap
     JOIN kho k ON pn.ma_kho = k.ma_kho
     WHERE ct.ma_hang = hh.ma_hang AND pn.ngay_nhap < :ngay_bd
       AND k.ma_loai_kho = :ma_loai_kho $kho_filter
    ) AS nhap_dau_ky,

    /* Tổng Xuất Lũy Kế (Tính tồn đầu) */
    (SELECT COALESCE(SUM(ct.so_luong_xuat), 0)
     FROM ct_phieu_xuat ct JOIN phieu_xuat px ON ct.ma_phieu_xuat = px.ma_phieu_xuat
     JOIN kho k ON px.ma_kho = k.ma_kho
     WHERE ct.ma_hang = hh.ma_hang AND px.ngay_xuat < :ngay_bd
       AND k.ma_loai_kho = :ma_loai_kho $kho_filter
    ) AS xuat_dau_ky

FROM hang_hoa hh
WHERE hh.ma_loai_hang = :ma_loai_hang
ORDER BY hh.ten_hang ASC
";

$stmt1 = $pdo->prepare($sql1);
$stmt1->execute($params_common);
?>

<div class="section-title">I. BẢNG TỔNG HỢP (TOÀN BỘ CÁC KHO)</div>
<table border='1'>
    <thead>
        <tr class="bg-header-1">
            <th>STT</th>
            <th>Mã hàng</th>
            <th>Tên hàng</th>
            <th>ĐVT</th>
            <th>Tổng Tồn đầu</th>
            <th>Tổng Nhập</th>
            <th>Tổng Xuất</th>
            <th>Tổng Tồn cuối</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $stt = 1;
    while ($r = $stmt1->fetch(PDO::FETCH_ASSOC)) {
        $ton_dau  = $r['nhap_dau_ky'] - $r['xuat_dau_ky'];
        $nhap     = $r['nhap_tk'];
        $xuat     = $r['xuat_tk'];
        $ton_cuoi = $ton_dau + $nhap - $xuat;

        if ($ton_dau == 0 && $nhap == 0 && $xuat == 0 && $ton_cuoi == 0) continue;

        echo "<tr style='font-size: 20px;'>
            <td>{$stt}</td>
            <td>{$r['ma_hang']}</td>
            <td class='text-left'>{$r['ten_hang']}</td>
            <td>{$r['don_vi_tinh']}</td>
            <td>" . number_format($ton_dau) . "</td>
            <td>" . number_format($nhap) . "</td>
            <td>" . number_format($xuat) . "</td>
            <td class='text-bold'>" . number_format($ton_cuoi) . "</td>
        </tr>";
        $stt++;
    }
    ?>
    </tbody>
</table>

<br>

<?php
/* --------------------------------------------------------------------------
   PHẦN 4: BẢNG 2 - CHI TIẾT TỪNG KHO & TRẠNG THÁI
   (Tách riêng từng kho, có cảnh báo Min/Max)
-------------------------------------------------------------------------- */

$sql2 = "
SELECT 
    k.ma_kho, k.ten_kho,
    hh.ma_hang, hh.ten_hang, hh.don_vi_tinh, hh.muc_du_tru_min, hh.muc_du_tru_max,

    /* Nhập trong kỳ (Theo từng kho) */
    (SELECT COALESCE(SUM(ct.so_luong_nhap), 0)
     FROM ct_phieu_nhap ct JOIN phieu_nhap pn ON ct.ma_phieu_nhap = pn.ma_phieu_nhap
     WHERE ct.ma_hang = hh.ma_hang AND pn.ma_kho = k.ma_kho 
       AND pn.ngay_nhap BETWEEN :ngay_bd AND :ngay_kt
    ) AS nhap_tk,

    /* Xuất trong kỳ */
    (SELECT COALESCE(SUM(ct.so_luong_xuat), 0)
     FROM ct_phieu_xuat ct JOIN phieu_xuat px ON ct.ma_phieu_xuat = px.ma_phieu_xuat
     WHERE ct.ma_hang = hh.ma_hang AND px.ma_kho = k.ma_kho
       AND px.ngay_xuat BETWEEN :ngay_bd AND :ngay_kt
    ) AS xuat_tk,

    /* Nhập Lũy kế đầu kỳ */
    (SELECT COALESCE(SUM(ct.so_luong_nhap), 0)
     FROM ct_phieu_nhap ct JOIN phieu_nhap pn ON ct.ma_phieu_nhap = pn.ma_phieu_nhap
     WHERE ct.ma_hang = hh.ma_hang AND pn.ma_kho = k.ma_kho AND pn.ngay_nhap < :ngay_bd
    ) AS nhap_dau_ky,

    /* Xuất Lũy kế đầu kỳ */
    (SELECT COALESCE(SUM(ct.so_luong_xuat), 0)
     FROM ct_phieu_xuat ct JOIN phieu_xuat px ON ct.ma_phieu_xuat = px.ma_phieu_xuat
     WHERE ct.ma_hang = hh.ma_hang AND px.ma_kho = k.ma_kho AND px.ngay_xuat < :ngay_bd
    ) AS xuat_dau_ky

FROM kho k
CROSS JOIN hang_hoa hh
WHERE k.ma_loai_kho = :ma_loai_kho
  AND hh.ma_loai_hang = :ma_loai_hang
  $kho_filter
ORDER BY k.ten_kho ASC, hh.ten_hang ASC
";

$stmt2 = $pdo->prepare($sql2);
$stmt2->execute($params_common);
?>

<div class="section-title">II. BẢNG CHI TIẾT TỒN KHO & CẢNH BÁO AN TOÀN</div>
<table border='1'>
    <thead>
        <tr class="bg-header-2">
            <th>STT</th>
            <th>Tên Kho</th>
            <th>Mã hàng</th>
            <th>Tên hàng</th>
            <th>ĐVT</th>
            <th>Tồn đầu</th>
            <th>Nhập</th>
            <th>Xuất</th>
            <th>Tồn cuối</th>
            <th>Min</th>
            <th>Max</th>
            <th>Trạng thái</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $stt = 1;
    $hasData = false;
    
    while ($r = $stmt2->fetch(PDO::FETCH_ASSOC)) {
        // Tính toán
        $ton_dau  = $r['nhap_dau_ky'] - $r['xuat_dau_ky'];
        $nhap     = $r['nhap_tk'];
        $xuat     = $r['xuat_tk'];
        $ton_cuoi = $ton_dau + $nhap - $xuat;

        if ($ton_dau == 0 && $nhap == 0 && $xuat == 0 && $ton_cuoi == 0) continue;
        
        $hasData = true;

        // Logic Trạng thái
        $min = $r['muc_du_tru_min'];
        $max = $r['muc_du_tru_max'];
        $status_text = "An toàn";
        $status_class = "status-safe";

        if ($ton_cuoi < $min) {
            $status_text = "Thấp hơn Min";
            $status_class = "status-low";
        } elseif ($ton_cuoi > $max) {
            $status_text = "Vượt quá Max";
            $status_class = "status-high";
        }

        echo "<tr style='font-size: 20px;'>
            <td>{$stt}</td>
            <td class='text-left text-bold'>{$r['ten_kho']}</td>
            <td>{$r['ma_hang']}</td>
            <td class='text-left'>{$r['ten_hang']}</td>
            <td>{$r['don_vi_tinh']}</td>
            <td>" . number_format($ton_dau) . "</td>
            <td>" . number_format($nhap) . "</td>
            <td>" . number_format($xuat) . "</td>
            <td class='text-bold'>" . number_format($ton_cuoi) . "</td>
            <td>" . number_format($min) . "</td>
            <td>" . number_format($max) . "</td>
            <td class='{$status_class}'>{$status_text}</td>
        </tr>";
        $stt++;
    }

    if (!$hasData) {
        echo "<tr><td colspan='12'>Không có dữ liệu phát sinh trong kỳ này.</td></tr>";
    }
    ?>
    </tbody>
</table>