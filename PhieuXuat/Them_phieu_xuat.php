<?php

include '../include/connect.php';
include '../include/permissions.php';
include '../include/update_the_kho.php';

checkAccess('phieuxuat');

$error_message = '';
$success_message = '';

// Hàm tự động tạo mã phiếu xuất
function taoMaPhieuXuatTuDong($pdo, $loai)
{
    $prefix = ($loai === 'vat_tu') ? 'PX-VT-' : 'PX-TP-';

    // Lấy mã phiếu lớn nhất có prefix tương ứng
    $stmt = $pdo->prepare("
        SELECT ma_phieu_xuat 
        FROM phieu_xuat 
        WHERE ma_phieu_xuat LIKE ? 
        ORDER BY ma_phieu_xuat DESC 
        LIMIT 1
    ");
    $stmt->execute([$prefix . '%']);
    $lastMa = $stmt->fetchColumn();

    if ($lastMa) {
        $lastNumber = intval(substr($lastMa, strlen($prefix)));
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }

    return $prefix . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
}

// Tạo mã phiếu tự động cho cả hai loại
$ma_phieu_vat_tu     = taoMaPhieuXuatTuDong($pdo, 'vat_tu');
$ma_phieu_thanh_pham = taoMaPhieuXuatTuDong($pdo, 'thanh_pham');

// Lấy danh sách đại lý (chỉ dùng cho xuất thành phẩm)
$dai_ly_list = $pdo->query("SELECT ma_dai_ly, ten_dai_ly FROM dai_ly ORDER BY ten_dai_ly")->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách LOẠI KHO
$loai_kho_list = $pdo->query("
    SELECT ma_loai_kho, ten_loai_kho 
    FROM loai_kho 
    ORDER BY ten_loai_kho
")->fetchAll(PDO::FETCH_ASSOC);

// Lấy kho theo quyền user
$role = trim($_SESSION['role'] ?? '');
$ma_nd = $_SESSION['MaND'] ?? null;
$sql_kho = "
    SELECT k.ma_kho, k.ten_kho, k.ma_loai_kho 
    FROM kho k
    WHERE 1=1
";
$params_kho = [];
if ($role === 'Thủ kho' && $ma_nd) {
    $sql_kho .= ' AND k.ma_nd = :ma_nd';
    $params_kho[':ma_nd'] = $ma_nd;
} elseif ($role === 'Quản lý kho' && $ma_nd) {
    $sql_kho .= ' AND EXISTS (
        SELECT 1 FROM phan_quyen pq 
        WHERE pq.ma_nd = :ma_nd 
        AND pq.ma_vung = k.ma_vung 
        AND pq.ma_loai_kho = k.ma_loai_kho
    )';
    $params_kho[':ma_nd'] = $ma_nd;
}
// Admin và Ban giám đốc thấy hết
$sql_kho .= ' ORDER BY k.ten_kho';
$stmt_kho = $pdo->prepare($sql_kho);
$stmt_kho->execute($params_kho);
$kho_list = $stmt_kho->fetchAll(PDO::FETCH_ASSOC);
// Xác định quyền theo loại kho từ $kho_list
$has_vat_tu     = false;
$has_thanh_pham = false;

$vat_tu_loai = ['L001', 'L002', 'L003'];

foreach ($kho_list as $kho) {
    if (in_array($kho['ma_loai_kho'], $vat_tu_loai)) {
        $has_vat_tu = true;
    }
    if ($kho['ma_loai_kho'] === 'L004') {
        $has_thanh_pham = true;
    }
}

if (!$has_vat_tu && !$has_thanh_pham) {
    $error_message = 'Bạn chưa được phân quyền quản lý kho nào để thêm phiếu xuất.';
}
// Lấy TẤT CẢ mặt hàng
$hang_hoa_list = $pdo->query("
    SELECT ma_hang, ten_hang, don_gia, ma_loai_hang 
    FROM hang_hoa 
    ORDER BY ten_hang
")->fetchAll(PDO::FETCH_ASSOC);

// AJAX endpoint để lấy mã phiếu mới
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax_get_ma_phieu'])) {
    $loai = $_GET['loai'] ?? 'vat_tu';
    $ma_phieu = taoMaPhieuXuatTuDong($pdo, $loai);
    echo json_encode(['ma_phieu' => $ma_phieu]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loai_phieu     = $_POST['loai_phieu'] ?? 'vat_tu'; // 'vat_tu' hoặc 'thanh_pham'
    $ma_phieu_xuat  = trim($_POST['ma_phieu_xuat'] ?? '');
    $ngay_xuat      = $_POST['ngay_xuat'] ?? '';
    $ma_kho         = $_POST['ma_kho'] ?? null;
    $nguoi_nhan     = trim($_POST['nguoi_nhan'] ?? '');
    $don_vi_nhan    = trim($_POST['don_vi_nhan'] ?? '');
    $ghi_chu        = trim($_POST['ghi_chu'] ?? '');

    // Nếu là xuất thành phẩm thì bắt buộc có đại lý
    $ma_dai_ly = ($loai_phieu === 'thanh_pham') ? ($_POST['ma_dai_ly'] ?? null) : null;

    $hang_hoa = $_POST['hang_hoa'] ?? [];

    // Validate
    if (empty($ma_phieu_xuat) || empty($ngay_xuat) || empty($ma_kho) || empty($hang_hoa)) {
        $error_message = 'Vui lòng điền đầy đủ thông tin bắt buộc.';
    } elseif ($loai_phieu === 'thanh_pham' && empty($ma_dai_ly)) {
        $error_message = 'Vui lòng chọn đại lý cho phiếu xuất thành phẩm.';
    } else {
        try {
            $pdo->beginTransaction();

            // Kiểm tra mã phiếu trùng
            $check = $pdo->prepare("SELECT ma_phieu_xuat FROM phieu_xuat WHERE ma_phieu_xuat = ?");
            $check->execute([$ma_phieu_xuat]);
            if ($check->fetch()) {
                throw new Exception('Mã phiếu xuất đã tồn tại.');
            }

            // Thêm phiếu xuất
            // Lưu ý: Cần thêm cột 'trang_thai' vào bảng phieu_xuat với giá trị mặc định 'cho_xac_nhan'
            // Nếu cột chưa tồn tại, hãy chạy: ALTER TABLE phieu_xuat ADD COLUMN trang_thai VARCHAR(20) DEFAULT 'cho_xac_nhan'
            $sql_px = "INSERT INTO phieu_xuat 
                       (ma_phieu_xuat, ma_nd, ngay_xuat, nguoi_nhan, don_vi_nhan, loai_xuat, ma_kho, ma_dai_ly, trang_thai)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'cho_xac_nhan')";
            $stmt_px = $pdo->prepare($sql_px);
            $stmt_px->execute([$ma_phieu_xuat, $_SESSION['MaND'], $ngay_xuat, $nguoi_nhan, $don_vi_nhan, $loai_phieu, $ma_kho, $ma_dai_ly]);

            // Thêm chi tiết phiếu xuất
            $sql_ct = "INSERT INTO ct_phieu_xuat 
                       (ma_ctpx, ma_phieu_xuat, ma_hang, so_luong_xuat, don_gia_xuat, thanh_tien)
                       VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_ct = $pdo->prepare($sql_ct);

            $ds_ma_hang = [];

            foreach ($hang_hoa as $item) {
                if (empty($item['ma_hang']) || empty($item['so_luong']) || $item['so_luong'] <= 0) continue;

                $ma_hang    = $item['ma_hang'];
                $so_luong   = (int)$item['so_luong'];
                $don_gia    = (float)($item['don_gia'] ?? 0);
                $thanh_tien = $so_luong * $don_gia;

                // Kiểm tra số lượng tồn kho - Lấy số lượng tồn MỚI NHẤT, không phải SUM
                $stmt_check = $pdo->prepare("
                    SELECT tk.so_luong_ton, h.muc_du_tru_min
                    FROM the_kho tk
                    JOIN hang_hoa h ON tk.ma_hang = h.ma_hang
                    WHERE tk.ma_kho = ? AND tk.ma_hang = ?
                    ORDER BY tk.ngay DESC, tk.ma_the_kho DESC
                    LIMIT 1
                ");
                $stmt_check->execute([$ma_kho, $ma_hang]);
                $ton_kho = $stmt_check->fetch(PDO::FETCH_ASSOC);

                $so_luong_ton_hien_tai = (int)($ton_kho['so_luong_ton'] ?? 0);
                $muc_du_tru_min = (int)($ton_kho['muc_du_tru_min'] ?? 0);

                // Debug: Ghi log kiểm tra tồn kho
                error_log("DEBUG - Xuất hàng: ma_hang={$ma_hang}, ma_kho={$ma_kho}, so_luong_ton={$so_luong_ton_hien_tai}, so_luong_xuat={$so_luong}, muc_du_tru_min={$muc_du_tru_min}");

                // Kiểm tra số lượng tồn phải > 0
                if ($so_luong_ton_hien_tai <= 0) {
                    error_log("ERROR - Mặt hàng không còn tồn kho: {$so_luong_ton_hien_tai}");
                    throw new Exception("Mặt hàng hiện không còn tồn kho (còn: {$so_luong_ton_hien_tai}). Không thể xuất.");
                }

                // Kiểm tra số lượng xuất phải ≤ số lượng tồn
                if ($so_luong > $so_luong_ton_hien_tai) {
                    error_log("ERROR - Xuất quá tồn kho: tồn={$so_luong_ton_hien_tai}, xuất={$so_luong}");
                    throw new Exception("Mặt hàng chỉ còn {$so_luong_ton_hien_tai} trong kho. Không thể xuất {$so_luong}.");
                }

                // Kiểm tra nếu xuất sẽ vượt quá mức dự trữ tối thiểu
                // $so_luong_sau_xuat = $so_luong_ton_hien_tai - $so_luong;
                // if ($so_luong_sau_xuat < $muc_du_tru_min && $muc_du_tru_min > 0) {
                //     // Lấy tên hàng để hiển thị trong cảnh báo
                //     $hang_info = $pdo->prepare("SELECT ten_hang FROM hang_hoa WHERE ma_hang = ?");
                //     $hang_info->execute([$ma_hang]);
                //     $hang = $hang_info->fetch(PDO::FETCH_ASSOC);
                //     $ten_hang = $hang['ten_hang'] ?? 'Unknown';

                //     // Chỉ cảnh báo, không chặn
                //     $warning_message = "Cảnh báo: Sau khi xuất, mặt hàng '{$ten_hang}' sẽ còn {$so_luong_sau_xuat} (dưới mức dự trữ tối thiểu {$muc_du_tru_min}).";
                //     // Có thể lưu warning vào session để hiển thị
                //     if (!isset($_SESSION['export_warnings'])) {
                //         $_SESSION['export_warnings'] = [];
                //     }
                //     $_SESSION['export_warnings'][] = $warning_message;
                //     error_log("WARNING - Xuất vượt mức min: {$warning_message}");
                // }

                $ma_ctpx = $ma_phieu_xuat . '-' . $ma_hang;

                $stmt_ct->execute([$ma_ctpx, $ma_phieu_xuat, $ma_hang, $so_luong, $don_gia, $thanh_tien]);

                $ds_ma_hang[] = $ma_hang;
            }

            $pdo->commit();

            // KHÔNG tự động cập nhật thẻ kho - chỉ cập nhật khi thủ kho xác nhận
            // cap_nhat_the_kho_theo_phieu($pdo, $ma_kho, $ds_ma_hang);

            $success_message = 'Thêm phiếu xuất thành công! Phiếu đang chờ thủ kho xác nhận.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = 'Lỗi: ' . $e->getMessage();
        }
    }
}
?>


<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Thêm Phiếu Xuất Kho</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#135bec",
                        "primary-hover": "#0e4bce",
                        "background-light": "#f6f6f8",
                        "background-dark": "#101622",
                        "surface-light": "#ffffff",
                        "surface-dark": "#1a2230",
                        "border-light": "#e7ebf3",
                        "border-dark": "#2d3748",
                        "text-primary": "#0d121b",
                        "text-secondary": "#4c669a",
                    },
                },
            },
        }
    </script>
</head>

<body class="bg-background-light dark:bg-background-dark text-text-primary dark:text-gray-100 min-h-screen font-display">

    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="h-16 bg-surface-light dark:bg-surface-dark border-b border-border-light dark:border-border-dark flex items-center justify-between px-6">
            <div class="flex items-center justify-center gap-4">
                <a href="phieuxuat.php" class="text-text-secondary hover:text-primary">
                    <span class="material-symbols-outlined">arrow_back</span>
                </a>
                <h1 class="text-xl font-bold">Thêm phiếu xuất Kho</h1>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6">
            <div class="max-w-5xl mx-auto">
                <!-- Tab Navigation -->
                <!-- Tab Navigation -->
                <?php if ($has_vat_tu || $has_thanh_pham): ?>
                    <div class="mb-6 bg-surface-light dark:bg-surface-dark rounded-xl border border-border-light dark:border-border-dark p-2 flex gap-2">
                        <?php if ($has_vat_tu): ?>
                            <button type="button" onclick="switchTab('vat_tu')" id="tab-vat-tu"
                                class="flex-1 px-6 py-3 rounded-lg font-medium transition-all duration-200 bg-primary text-white shadow-sm">
                                <span class="flex items-center justify-center gap-2">
                                    <span class="material-symbols-outlined">input</span>
                                    Phiếu xuất vật tư
                                </span>
                            </button>
                        <?php endif; ?>

                        <?php if ($has_thanh_pham): ?>
                            <button type="button" onclick="switchTab('thanh_pham')" id="tab-thanh-pham"
                                class="flex-1 px-6 py-3 rounded-lg font-medium transition-all duration-200 bg-transparent text-text-secondary hover:bg-gray-100 dark:hover:bg-gray-800">
                                <span class="flex items-center justify-center gap-2">
                                    <span class="material-symbols-outlined">output</span>
                                    Phiếu xuất thành phẩm
                                </span>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="mb-6 p-6 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-xl text-center text-red-700 dark:text-red-300">
                        Bạn không có quyền thêm phiếu xuất kho nào.
                    </div>
                <?php endif; ?>

                <div class="bg-surface-light dark:bg-surface-dark rounded-xl border border-border-light dark:border-border-dark shadow-sm p-6 md:p-8">

                    <?php if ($error_message): ?>
                        <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg flex gap-3">
                            <span class="material-symbols-outlined text-red-600">error</span>
                            <div class="text-sm text-red-800 dark:text-red-300"><?= htmlspecialchars($error_message) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($success_message): ?>
                        <div id="successModal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
                            <div class="bg-white dark:bg-surface-dark rounded-xl shadow-2xl max-w-md w-full p-8 text-center">
                                <div class="mb-4 flex justify-center">
                                    <span class="material-symbols-outlined text-6xl text-green-500">check_circle</span>
                                </div>
                                <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Thành công!</h3>
                                <p class="text-gray-600 dark:text-gray-300 mb-6"><?= htmlspecialchars($success_message) ?></p>
                                <div class="flex gap-3">
                                    <a href="phieuxuat.php" class="flex-1 px-4 py-3 bg-primary hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                                        Xem danh sách
                                    </a>
                                    <button onclick="resetFormAndCloseModal()" class="flex-1 px-4 py-3 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg font-medium transition-colors">
                                        Thêm tiếp
                                    </button>
                                </div>
                            </div>
                        </div>
                        <script>
                            // Tự động hide modal sau 10 giây nếu user không bấm
                            setTimeout(() => {
                                const modal = document.getElementById('successModal');
                                if (modal) {
                                    modal.style.opacity = '0';
                                    modal.style.transition = 'opacity 0.3s ease-out';
                                    setTimeout(() => {
                                        window.location.href = 'phieuxuat.php';
                                    }, 300);
                                }
                            }, 10000);
                        </script>
                    <?php endif; ?>

                    <?php if (!empty($_SESSION['export_warnings'])): ?>
                        <div class="mb-6 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                            <div class="flex gap-3 mb-2">
                                <span class="material-symbols-outlined text-yellow-600">warning</span>
                                <h4 class="font-medium text-yellow-800 dark:text-yellow-300">Cảnh báo về mức tồn kho tối thiểu</h4>
                            </div>
                            <ul class="text-sm text-yellow-700 dark:text-yellow-300 space-y-1">
                                <?php foreach ($_SESSION['export_warnings'] as $warning): ?>
                                    <li>• <?= htmlspecialchars($warning) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php unset($_SESSION['export_warnings']); // Clear warnings after displaying ?>
                    <?php endif; ?>

                    <form method="POST" id="phieuXuatForm">
                        <input type="hidden" name="loai_phieu" id="loai_phieu" value="vat_tu">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                            <div>
                                <label class="block text-sm font-medium mb-2">Mã phiếu xuất <span class="text-red-500">*</span></label>
                                <div class="flex gap-2">
                                    <input type="text"
                                        name="ma_phieu_xuat"
                                        id="ma_phieu_xuat"
                                        required
                                        readonly
                                        value="<?= htmlspecialchars($ma_phieu_vat_tu) ?>"
                                        class="flex-1 px-4 py-2.5 border rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-primary cursor-not-allowed" />
                                    <button type="button"
                                        onclick="generateMaPhieu()"
                                        class="px-4 py-2.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 border rounded-lg transition-colors"
                                        title="Tạo mã tự động">
                                        <!-- <span class="material-symbols-outlined text-[20px]">refresh</span> -->
                                    </button>
                                </div>
                                <p class="text-xs text-text-secondary mt-1">Mã tự động theo loại phiếu</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-2">Ngày xuất <span class="text-red-500">*</span></label>
                                <input type="date" name="ngay_xuat" required value="<?= date('Y-m-d') ?>" class="w-full px-4 py-2.5 border rounded-lg bg-background-light dark:bg-gray-800 focus:ring-2 focus:ring-primary" />
                            </div>

                            <!-- Đại lý - chỉ hiện với thành phẩm -->
                            <div id="daily-field" style="display: none;">
                                <label class="block text-sm font-medium mb-2">Đại lý nhận <span class="text-red-500">*</span></label>
                                <select name="ma_dai_ly" id="ma_dai_ly" class="w-full px-4 py-2.5 border rounded-lg bg-background-light dark:bg-gray-800 focus:ring-2 focus:ring-primary">
                                    <option value="">-- Chọn đại lý --</option>
                                    <?php foreach ($dai_ly_list as $dl): ?>
                                        <option value="<?= htmlspecialchars($dl['ma_dai_ly']) ?>"><?= htmlspecialchars($dl['ten_dai_ly']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="relative">
                                <label class="block text-sm font-medium mb-2">Đơn vị nhận</label>
                                <input
                                    type="text"
                                    name="don_vi_nhan"
                                    value="Phân xưởng"
                                    readonly
                                    class="w-full px-4 py-2.5 pr-10 border rounded-lg 
                                    bg-gray-100 dark:bg-gray-700 
                                    text-gray-700 dark:text-gray-300 
                                    cursor-not-allowed" />
                                <!-- <span class="material-symbols-outlined absolute right-3 top-[42px] text-gray-400">
                                    lock
                                </span> -->
                            </div>


                            <div>
                                <label class="block text-sm font-medium mb-2">Loại kho <span class="text-red-500">*</span></label>
                                <select name="ma_loai_kho" id="ma_loai_kho" required class="w-full px-4 py-2.5 border rounded-lg bg-background-light dark:bg-gray-800 focus:ring-2 focus:ring-primary" onchange="filterKhoAndHang()">
                                    <option value="">-- Chọn loại kho --</option>
                                    <?php foreach ($loai_kho_list as $lk): ?>
                                        <option value="<?= htmlspecialchars($lk['ma_loai_kho']) ?>"><?= htmlspecialchars($lk['ten_loai_kho']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-2">Kho xuất <span class="text-red-500">*</span></label>
                                <select name="ma_kho" id="ma_kho" required class="w-full px-4 py-2.5 border rounded-lg bg-background-light dark:bg-gray-800 focus:ring-2 focus:ring-primary">
                                    <option value="">-- Chọn kho --</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-2">Người nhận</label>
                                <input type="text" name="nguoi_nhan" class="w-full px-4 py-2.5 border rounded-lg bg-background-light dark:bg-gray-800 focus:ring-2 focus:ring-primary" placeholder="Tên người nhận" />
                            </div>
                        </div>

                        <div class="mb-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold">Chi tiết mặt hàng xuất</h3>
                                <button type="button" onclick="addRow()" class="flex items-center gap-2 px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg text-sm font-medium">
                                    <span class="material-symbols-outlined text-[18px]">add</span> Thêm hàng
                                </button>
                            </div>

                            <div class="overflow-x-auto border rounded-lg">
                                <table class="w-full" id="chiTietTable">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-text-secondary uppercase">Mặt hàng</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-text-secondary uppercase w-32">Số lượng xuất</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-text-secondary uppercase w-40">Đơn giá xuất (VNĐ)</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-text-secondary uppercase w-40">Thành tiền</th>
                                            <th class="px-4 py-3 text-center text-xs font-medium text-text-secondary uppercase w-20">Xóa</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="5" class="py-8 text-center text-text-secondary">Chọn loại kho để hiển thị danh sách mặt hàng...</td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" class="px-4 py-3 text-right font-semibold">Tổng tiền:</td>
                                            <td class="px-4 py-3 text-right font-bold text-lg" id="tongTien">0 đ</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div class="mb-8">
                            <label class="block text-sm font-medium mb-2">Ghi chú</label>
                            <textarea name="ghi_chu" rows="3" class="w-full px-4 py-3 border rounded-lg bg-background-light dark:bg-gray-800 focus:ring-2 focus:ring-primary"></textarea>
                        </div>

                        <div class="flex justify-end gap-4">
                            <a href="phieuxuat.php" class="px-6 py-3 border rounded-lg bg-surface-light dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                Hủy
                            </a>
                            <button type="button" onclick="kiemTraVaLuu()" class="px-6 py-3 bg-primary hover:bg-primary-hover text-white rounded-lg font-medium flex items-center gap-2 shadow-sm">
                                <span class="material-symbols-outlined">save</span>
                                Lưu phiếu xuất
                            </button>
                        </div>
                    </form>

                    <!-- Modal cảnh báo mức tồn kho tối thiểu -->
                    <div id="warningModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4 animate-fade-in">
                        <div class="w-full max-w-md bg-white dark:bg-surface-dark rounded-xl shadow-2xl">
                            <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                    <span class="material-symbols-outlined text-yellow-600">warning</span>
                                    Cảnh báo tồn kho
                                </h3>
                            </div>
                            <div class="p-6">
                                <div id="warningContent" class="space-y-4">
                                    <!-- Nội dung cảnh báo sẽ được thêm bằng JS -->
                                </div>
                                <div class="flex justify-end gap-3 mt-6">
                                    <button onclick="cancelExport()" class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                        Hủy
                                    </button>
                                    <button onclick="proceedExport()" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg font-medium transition-colors">
                                        Tiếp tục xuất
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Dữ liệu từ PHP
        const loaiKhoToHangMap = {
            'L001': 'M001',
            'L002': 'M002',
            'L003': 'M003',
            'L004': 'M004'
        };
        const hangHoaList = <?= json_encode($hang_hoa_list) ?>;
        const khoList = <?= json_encode($kho_list) ?>;

        // Mã phiếu tự động
        const maPhieuVatTu = '<?= $ma_phieu_vat_tu ?>';
        const maPhieuThanhPham = '<?= $ma_phieu_thanh_pham ?>';

        let rowIndex = 0;
        let currentTab = 'vat_tu';

        // Quyền từ PHP
        const hasVatTu = <?= $has_vat_tu ? 'true' : 'false' ?>;
        const hasThanhPham = <?= $has_thanh_pham ? 'true' : 'false' ?>;

        // Tự động chọn tab khi load
        document.addEventListener('DOMContentLoaded', () => {
            if (hasVatTu) {
                switchTab('vat_tu');
            } else if (hasThanhPham) {
                switchTab('thanh_pham');
            }
        });

        function switchTab(tabName) {
            if ((tabName === 'vat_tu' && !hasVatTu) || (tabName === 'thanh_pham' && !hasThanhPham)) {
                return;
            }

            currentTab = tabName;
            document.getElementById('loai_phieu').value = tabName;

            // Reset style
            const tabVatTu = document.getElementById('tab-vat-tu');
            const tabThanhPham = document.getElementById('tab-thanh-pham');

            if (tabVatTu) tabVatTu.className = 'flex-1 px-6 py-3 rounded-lg font-medium transition-all duration-200 bg-transparent text-text-secondary hover:bg-gray-100 dark:hover:bg-gray-800';
            if (tabThanhPham) tabThanhPham.className = 'flex-1 px-6 py-3 rounded-lg font-medium transition-all duration-200 bg-transparent text-text-secondary hover:bg-gray-100 dark:hover:bg-gray-800';

            const activeTab = document.getElementById(`tab-${tabName}`);
            if (activeTab) activeTab.className = 'flex-1 px-6 py-3 rounded-lg font-medium transition-all duration-200 bg-primary text-white shadow-sm';

            // Hiện/ẩn trường đại lý (chỉ thành phẩm cần đại lý)
            document.getElementById('daily-field').style.display = (tabName === 'thanh_pham') ? 'block' : 'none';
            document.getElementById('ma_dai_ly').required = (tabName === 'thanh_pham');

            // Cập nhật mã phiếu
            document.getElementById('ma_phieu_xuat').value = (tabName === 'vat_tu') ? maPhieuVatTu : maPhieuThanhPham;

            // Lọc loại kho
            const allowedCodes = (tabName === 'vat_tu') ? ['L001', 'L002', 'L003'] : ['L004'];
            filterLoaiKhoOptions(allowedCodes);

            // Reset form
            document.getElementById('ma_loai_kho').value = '';
            filterKhoAndHang();
        }

        // Các hàm còn lại giữ nguyên
        // Lọc options loại kho
        function filterLoaiKhoOptions(allowedCodes) {
            const select = document.getElementById('ma_loai_kho');
            const options = select.querySelectorAll('option');

            options.forEach(opt => {
                if (opt.value === '') return;
                opt.style.display = allowedCodes.includes(opt.value) ? 'block' : 'none';
            });
        }

        function filterKhoAndHang() {
            const maLoaiKho = document.getElementById('ma_loai_kho').value;
            const khoSelect = document.getElementById('ma_kho');

            khoSelect.innerHTML = '<option value="">-- Chọn kho --</option>';
            if (maLoaiKho) {
                const filteredKho = khoList.filter(k => k.ma_loai_kho === maLoaiKho);
                filteredKho.forEach(k => {
                    const opt = document.createElement('option');
                    opt.value = k.ma_kho;
                    opt.textContent = k.ten_kho;
                    khoSelect.appendChild(opt);
                });
            }

            document.querySelector('#chiTietTable tbody').innerHTML = '<tr><td colspan="5" class="py-8 text-center text-text-secondary">Chọn loại kho để hiển thị danh sách mặt hàng...</td></tr>';
            rowIndex = 0;
        }

        function getFilteredHangHoa(maLoaiKho) {
            const maLoaiHang = loaiKhoToHangMap[maLoaiKho];
            if (!maLoaiHang) return [];
            return hangHoaList.filter(h => h.ma_loai_hang === maLoaiHang);
        }

        function addRow(ma_hang = '', so_luong = '', don_gia = '') {
            const tbody = document.querySelector('#chiTietTable tbody');
            const maLoaiKho = document.getElementById('ma_loai_kho').value;

            if (!maLoaiKho) {
                alert('Vui lòng chọn loại kho trước!');
                return;
            }

            const filteredHang = getFilteredHangHoa(maLoaiKho);

            if (filteredHang.length === 0) {
                alert('Không có mặt hàng nào thuộc loại kho này!');
                return;
            }

            if (tbody.querySelector('tr td[colspan]')) {
                tbody.innerHTML = '';
            }

            const row = document.createElement('tr');
            row.innerHTML = `
        <td class="px-4 py-3">
            <select name="hang_hoa[${rowIndex}][ma_hang]" required class="w-full px-3 py-2 border rounded-lg bg-background-light dark:bg-gray-800 focus:ring-2 focus:ring-primary" onchange="updateDonGia(this)">
                <option value="">-- Chọn mặt hàng --</option>
                ${filteredHang.map(h => `
                    <option value="${h.ma_hang}" ${h.ma_hang === ma_hang ? 'selected' : ''}>
                        ${h.ten_hang}
                    </option>
                `).join('')}
            </select>
        </td>
        <td class="px-4 py-3 text-right">
            <input type="number" name="hang_hoa[${rowIndex}][so_luong]" value="${so_luong}" min="1" required class="w-full px-3 py-2 border rounded-lg text-right" onchange="tinhThanhTien(this)" />
        </td>
        <td class="px-4 py-3 text-right">
            <input type="text" name="hang_hoa[${rowIndex}][don_gia]" value="${don_gia}" readonly class="w-full px-3 py-2 border rounded-lg bg-gray-50 dark:bg-gray-700 text-right" />
        </td>
        <td class="px-4 py-3 text-right font-medium" data-thanh-tien="0">0 đ</td>
        <td class="px-4 py-3 text-center">
            <button type="button" onclick="this.closest('tr').remove(); tinhTong()" class="text-red-600 hover:text-red-800">
                <span class="material-symbols-outlined text-[20px]">delete</span>
            </button>
        </td>
    `;
            tbody.appendChild(row);

            if (ma_hang) updateDonGia(row.querySelector('select'));

            rowIndex++;
        }

        function updateDonGia(select) {
            const row = select.closest('tr');
            const ma_hang = select.value;
            const hang = hangHoaList.find(h => h.ma_hang === ma_hang);
            const donGiaInput = row.querySelector('input[name$="[don_gia]"]');
            donGiaInput.value = hang ? Number(hang.don_gia).toLocaleString('vi-VN') : '';
            tinhThanhTien(row.querySelector('input[name$="[so_luong]"]'));
        }

        function tinhThanhTien(input) {
            const row = input.closest('tr');
            const so_luong = parseFloat(input.value) || 0;
            const don_gia_str = row.querySelector('input[name$="[don_gia]"]').value.replace(/\./g, '');
            const don_gia = parseFloat(don_gia_str) || 0;
            const thanh_tien = so_luong * don_gia;

            row.querySelector('[data-thanh-tien]').textContent = thanh_tien.toLocaleString('vi-VN') + ' đ';
            row.querySelector('[data-thanh-tien]').dataset.thanhTien = thanh_tien;
            tinhTong();
        }

        function tinhTong() {
            let tong = 0;
            document.querySelectorAll('[data-thanh-tien]').forEach(el => {
                tong += parseFloat(el.dataset.thanhTien || 0);
            });
            document.getElementById('tongTien').textContent = tong.toLocaleString('vi-VN') + ' đ';
        }

        // Hàm kiểm tra mức tồn kho tối thiểu trước khi lưu
        async function kiemTraVaLuu() {
            const form = document.getElementById('phieuXuatForm');
            const maKho = document.getElementById('ma_kho').value;

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            if (!maKho) {
                alert('Vui lòng chọn kho xuất!');
                return;
            }

            // Thu thập tất cả mặt hàng trong form
            const hangHoaRows = document.querySelectorAll('#chiTietTable tbody tr');
            const warnings = [];

            for (const row of hangHoaRows) {
                const select = row.querySelector('select[name$="[ma_hang]"]');
                const soLuongInput = row.querySelector('input[name$="[so_luong]"]');

                if (!select || !soLuongInput) continue;

                const maHang = select.value;
                const soLuong = parseInt(soLuongInput.value) || 0;

                if (!maHang || soLuong <= 0) continue;

                try {
                    // Kiểm tra tồn kho qua AJAX
                    const response = await fetch('kiem_tra_ton_kho.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            ma_kho: maKho,
                            ma_hang: maHang,
                            so_luong_xuat: soLuong
                        })
                    });

                    const result = await response.json();

                    if (result.warning) {
                        warnings.push(result.warning);
                    }
                } catch (error) {
                    console.error('Lỗi kiểm tra tồn kho:', error);
                }
            }

            // Nếu có cảnh báo, hiển thị modal
            if (warnings.length > 0) {
                showWarningModal(warnings);
                return;
            }

            // Nếu không có cảnh báo, submit ngay
            form.submit();
        }

        // Hiển thị modal cảnh báo
        function showWarningModal(warnings) {
            const modal = document.getElementById('warningModal');
            const content = document.getElementById('warningContent');

            // Tạo nội dung cảnh báo
            let html = `
                <div class="flex items-start gap-3 mb-4">
                    <span class="material-symbols-outlined text-yellow-600 text-2xl mt-1">warning</span>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white mb-2">Phát hiện mặt hàng sẽ xuống dưới mức tồn kho tối thiểu sau khi xuất:</p>
                        <ul class="space-y-2">
                            ${warnings.map(warning => `<li class="text-sm text-gray-700 dark:text-gray-300">• ${warning}</li>`).join('')}
                        </ul>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-3">
                            Bạn có muốn tiếp tục xuất không? Hệ thống sẽ vẫn cho phép tạo phiếu xuất.
                        </p>
                    </div>
                </div>
            `;

            content.innerHTML = html;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        // Hủy xuất
        function cancelExport() {
            const modal = document.getElementById('warningModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        // Tiếp tục xuất
        function proceedExport() {
            const modal = document.getElementById('warningModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');

            // Submit form
            const form = document.getElementById('phieuXuatForm');
            form.submit();
        }

        // Reset form và cập nhật mã phiếu khi click "Thêm tiếp"
        async function resetFormAndCloseModal() {
            const modal = document.getElementById('successModal');
            const loaiPhieu = document.getElementById('loai_phieu').value;
            
            try {
                // Lấy mã phiếu mới từ server
                const response = await fetch(`?ajax_get_ma_phieu=1&loai=${loaiPhieu}`);
                const data = await response.json();
                
                if (data.ma_phieu) {
                    // Cập nhật mã phiếu trong form
                    document.getElementById('ma_phieu_xuat').value = data.ma_phieu;
                }
            } catch (error) {
                console.error('Lỗi khi lấy mã phiếu mới:', error);
            }
            
            // Ẩn modal
            modal.style.display = 'none';
            
            // Reset form
            const form = document.getElementById('phieuXuatForm');
            
            // Reset các field nhập liệu (giữ lại ngày hiện tại)
            document.getElementById('ma_dai_ly').value = '';
            document.getElementById('ma_loai_kho').value = '';
            document.getElementById('ma_kho').value = '';
            document.getElementById('nguoi_nhan').value = '';
            document.getElementById('don_vi_nhan').value = '';
            document.getElementById('ghi_chu').value = '';
            
            // Reset table chi tiết
            const tbody = document.querySelector('#chiTietTable tbody');
            tbody.innerHTML = '<tr><td colspan="5" class="py-8 text-center text-text-secondary">Chọn loại kho để hiển thị danh sách mặt hàng...</td></tr>';
            
            // Reset tổng tiền
            document.getElementById('tongTien').textContent = '0 đ';
            rowIndex = 0;
        }

        // Khởi tạo
        document.addEventListener('DOMContentLoaded', () => {
            switchTab('vat_tu'); // Mặc định tab vật tư
        });
    </script>

</body>

</html>