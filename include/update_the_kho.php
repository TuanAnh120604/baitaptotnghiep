<?php
// includes/update_the_kho.php
// Hàm tự động cập nhật bảng the_kho - Mã thẻ kho riêng theo từng mặt hàng (toàn cục)

/**
 * Cập nhật thẻ kho cho một mặt hàng cụ thể (toàn hệ thống, không phân biệt kho)
 * Chỉ lưu số lượng tồn kho, không lưu riêng số lượng nhập/xuất
 *
 * @param PDO $pdo          Kết nối database
 * @param string $ma_kho    Mã kho (vẫn cần để lấy dữ liệu phát sinh trong kho đó)
 * @param string $ma_hang   Mã hàng hóa
 */
function cap_nhat_the_kho_theo_hang(PDO $pdo, string $ma_kho, string $ma_hang): void
{
    // Lấy số lượng tồn hiện tại (từ bản ghi mới nhất)
    $stmt_ton = $pdo->prepare("
        SELECT so_luong_ton
        FROM the_kho
        WHERE ma_kho = ? AND ma_hang = ?
        ORDER BY ngay DESC, ma_the_kho DESC
        LIMIT 1
    ");
    $stmt_ton->execute([$ma_kho, $ma_hang]);
    $current_ton = $stmt_ton->fetch(PDO::FETCH_ASSOC);
    $so_luong_ton_hien_tai = (int)($current_ton['so_luong_ton'] ?? 0);

    // Sinh prefix MTKxxx từ ma_hang (toàn cục)
    preg_match('/\d+$/', $ma_hang, $matches);
    if (!empty($matches)) {
        $so_hang = (int)$matches[0];
    } else {
        // Nếu mã hàng không có số, dùng hash ổn định
        $so_hang = crc32($ma_hang) % 1000;
    }
    $prefix = 'MTK' . str_pad($so_hang, 3, '0', STR_PAD_LEFT);

    // Lấy các phát sinh CHƯA được ghi nhận trong thẻ kho (tức là phát sinh mới nhất)
    $sql_new_events = "
        SELECT
            e.ngay,
            e.so_ct,
            e.loai_phat_sinh,
            e.sl_nhap,
            e.sl_xuat
        FROM (
            (SELECT
                pn.ngay_nhap AS ngay,
                pn.ma_phieu_nhap AS so_ct,
                COALESCE(pn.loai_nhap, 'Nhập kho') AS loai_phat_sinh,
                ctpn.so_luong_nhap AS sl_nhap,
                0 AS sl_xuat
            FROM ct_phieu_nhap ctpn
            JOIN phieu_nhap pn ON ctpn.ma_phieu_nhap = pn.ma_phieu_nhap
            WHERE pn.ma_kho = ? AND ctpn.ma_hang = ?)

            UNION ALL

            (SELECT
                px.ngay_xuat AS ngay,
                px.ma_phieu_xuat AS so_ct,
                COALESCE(px.loai_xuat, 'Xuất kho') AS loai_phat_sinh,
                0 AS sl_nhap,
                ctpx.so_luong_xuat AS sl_xuat
            FROM ct_phieu_xuat ctpx
            JOIN phieu_xuat px ON ctpx.ma_phieu_xuat = px.ma_phieu_xuat
            WHERE px.ma_kho = ? AND ctpx.ma_hang = ?)
        ) e
        LEFT JOIN the_kho tk ON tk.ma_kho = ? AND tk.ma_hang = ? AND tk.so_ct = e.so_ct
        WHERE tk.ma_the_kho IS NULL  -- Chỉ lấy những phát sinh chưa có trong thẻ kho
        ORDER BY e.ngay ASC, e.so_ct ASC
    ";

    $stmt_new = $pdo->prepare($sql_new_events);
    $stmt_new->execute([$ma_kho, $ma_hang, $ma_kho, $ma_hang, $ma_kho, $ma_hang]);
    $new_events = $stmt_new->fetchAll(PDO::FETCH_ASSOC);

    if (empty($new_events)) {
        return; // Không có phát sinh mới
    }

    $insert = $pdo->prepare("
        INSERT INTO the_kho
        (ma_the_kho, ma_kho, ma_hang, ngay, so_ct, loai_phat_sinh, so_luong_ton)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $ton = $so_luong_ton_hien_tai;

    foreach ($new_events as $e) {
        $ton += $e['sl_nhap'] - $e['sl_xuat'];

        // Sinh mã thẻ kho mới (tăng dần)
        $stmt_max_stt = $pdo->prepare("
            SELECT MAX(CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(ma_the_kho, '-', -1), '-', 1) AS UNSIGNED)) as max_stt
            FROM the_kho
            WHERE ma_the_kho LIKE ?
        ");
        $stmt_max_stt->execute([$prefix . '-%']);
        $max_stt_result = $stmt_max_stt->fetch(PDO::FETCH_ASSOC);
        $next_stt = ($max_stt_result['max_stt'] ?? 0) + 1;

        $ma_hang_clean = strtoupper(trim($ma_hang));
        $stt_str = str_pad($next_stt, 3, '0', STR_PAD_LEFT);
        $ma_the_kho = $prefix . '-' . $ma_hang_clean . '-' . $stt_str;

        $insert->execute([
            $ma_the_kho,
            $ma_kho,
            $ma_hang,
            $e['ngay'],
            $e['so_ct'],
            $e['loai_phat_sinh'],
            $ton
        ]);
    }
}

/**
 * Cập nhật thẻ kho cho nhiều mặt hàng trong một phiếu
 */
function cap_nhat_the_kho_theo_phieu(PDO $pdo, string $ma_kho, array $danh_sach_ma_hang): void
{
    $hang_unique = array_unique(array_filter($danh_sach_ma_hang));

    foreach ($hang_unique as $ma_hang) {
        cap_nhat_the_kho_theo_hang($pdo, $ma_kho, $ma_hang);
    }
}