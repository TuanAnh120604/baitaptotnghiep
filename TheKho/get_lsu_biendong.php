<?php
// get_lsu_biendong.php (phiên bản đã sửa – hiển thị đầy đủ nhập + xuất)
include '../include/connect.php';

$ma_hang = $_GET['ma_hang'] ?? '';
$ma_kho  = $_GET['ma_kho'] ?? '';

if (empty($ma_hang)) {
    exit('<div class="text-center py-10 text-slate-500">Thiếu thông tin mặt hàng</div>');
}

// Lấy lịch sử biến động từ chi tiết phiếu nhập và phiếu xuất
$sql = "
    SELECT
        e.ngay,
        DATE_FORMAT(e.ngay, '%d/%m/%Y') AS ngay_format,
        e.loai_phat_sinh,
        e.so_ct,
        e.so_luong,
        h.don_vi_tinh,
        k.ten_kho,
        e.don_gia,
        tk.ma_the_kho,
        e.sort_order
    FROM (
        -- Dữ liệu nhập từ ct_phieu_nhap
        SELECT
            pn.ngay_nhap AS ngay,
            pn.ma_phieu_nhap AS so_ct,
            'Nhập' AS loai_phat_sinh,
            ctpn.so_luong_nhap AS so_luong,
            ctpn.don_gia,
            pn.ma_kho,
            ctpn.ma_hang,
            CONCAT('A', LPAD(CAST(SUBSTRING(pn.ma_phieu_nhap, 6) AS UNSIGNED), 10, '0')) AS sort_order
        FROM ct_phieu_nhap ctpn
        JOIN phieu_nhap pn ON ctpn.ma_phieu_nhap = pn.ma_phieu_nhap
        WHERE ctpn.ma_hang = :ma_hang
          " . ($ma_kho ? " AND pn.ma_kho = :ma_kho" : "") . "

        UNION ALL

        -- Dữ liệu xuất từ ct_phieu_xuat
        SELECT
            px.ngay_xuat AS ngay,
            px.ma_phieu_xuat AS so_ct,
            'Xuất' AS loai_phat_sinh,
            ctpx.so_luong_xuat AS so_luong,
            ctpx.don_gia_xuat AS don_gia,
            px.ma_kho,
            ctpx.ma_hang,
            CONCAT('B', LPAD(CAST(SUBSTRING(px.ma_phieu_xuat, 6) AS UNSIGNED), 10, '0')) AS sort_order
        FROM ct_phieu_xuat ctpx
        JOIN phieu_xuat px ON ctpx.ma_phieu_xuat = px.ma_phieu_xuat
        WHERE ctpx.ma_hang = :ma_hang
          " . ($ma_kho ? " AND px.ma_kho = :ma_kho" : "") . "
    ) e
    LEFT JOIN hang_hoa h ON e.ma_hang = h.ma_hang
    LEFT JOIN kho k ON e.ma_kho = k.ma_kho
    LEFT JOIN the_kho tk ON tk.so_ct = e.so_ct AND tk.ma_hang = e.ma_hang AND tk.ma_kho = e.ma_kho AND tk.loai_phat_sinh = CASE WHEN e.loai_phat_sinh = 'Nhập' THEN 'Nhập kho' ELSE 'Xuất kho' END
    ORDER BY e.ngay DESC
    LIMIT 100
";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':ma_hang', $ma_hang);
if ($ma_kho) {
    $stmt->bindValue(':ma_kho', $ma_kho);
}
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    echo '<div class="text-center py-10 text-slate-500">Chưa có biến động nào</div>';
    exit;
}
?>

<ol class="relative border-l border-slate-200 dark:border-slate-700 ml-2">
    <?php foreach ($rows as $row): ?>
        <?php
        $isNhap = $row['loai_phat_sinh'] === 'Nhập';
        $color = $isNhap ? 'green' : 'blue';
        $soLuong = $row['so_luong'] ?? 0;
        $sign = $isNhap ? '+' : '-';
        $loai = $row['loai_phat_sinh'];

        // Hiển thị số chứng từ, nếu không có thì ghi "Nhập kho thủ công" hoặc "Điều chỉnh"
        $so_ct = trim($row['so_ct'] ?? '');
        if ($so_ct === '') {
            $so_ct = $isNhap ? 'Nhập thủ công / Điều chỉnh' : 'Xuất thủ công';
        }
        ?>
        <li class="mb-8 ml-4">
            <div class="absolute w-3 h-3 bg-<?=$color?>-500 rounded-full mt-1.5 -left-1.5 border border-white"></div>
            
            <time class="mb-1 text-xs font-normal text-slate-400">
                <?= $row['ngay_format'] ?>
                <?php if (!empty($row['ten_kho'])): ?>
                    <span class="text-xs text-slate-500">- <?= htmlspecialchars($row['ten_kho']) ?></span>
                <?php endif; ?>
            </time>
            
            <h3 class="text-sm font-semibold text-slate-900">
                <?= $loai ?>
            </h3>
            
            <p class="mb-1 text-sm font-normal text-slate-500">
                <?= htmlspecialchars($so_ct) ?>
            </p>
            
            <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium
                        bg-<?=$color?>-100 text-<?=$color?>-800"
                        title="<?php
                            $tooltip = '';
                            if ($row['don_gia']) {
                                $tooltip = 'Đơn giá: ' . number_format($row['don_gia']) . ' VNĐ';
                                if ($row['don_gia'] && $soLuong) {
                                    $thanh_tien = $row['don_gia'] * $soLuong;
                                    $tooltip .= ' | Thành tiền: ' . number_format($thanh_tien) . ' VNĐ';
                                }
                            }
                            echo htmlspecialchars($tooltip);
                        ?>">
                <?= $sign ?><?= number_format($soLuong) ?> <?= htmlspecialchars($row['don_vi_tinh'] ?? 'Kg') ?>
            </span>
        </li>
    <?php endforeach; ?>
</ol>