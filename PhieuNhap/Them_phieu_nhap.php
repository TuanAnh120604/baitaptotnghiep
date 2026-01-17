<?php
include '../include/connect.php';
include '../include/permissions.php';
include '../include/update_the_kho.php';

checkAccess('phieunhap');

$error_message = '';
$success_message = '';

// Hàm tự động tạo mã phiếu nhập
function taoMaPhieuNhapTuDong($pdo, $loai)
{
    $prefix = ($loai === 'vat_tu') ? 'PN-VT-' : 'PN-TP-';

    // Lấy mã phiếu lớn nhất có prefix tương ứng
    $stmt = $pdo->prepare("
        SELECT ma_phieu_nhap 
        FROM phieu_nhap 
        WHERE ma_phieu_nhap LIKE ? 
        ORDER BY ma_phieu_nhap DESC 
        LIMIT 1
    ");
    $stmt->execute([$prefix . '%']);
    $lastMa = $stmt->fetchColumn();

    if ($lastMa) {
        // Tách số thứ tự từ mã cuối cùng
        $lastNumber = intval(substr($lastMa, strlen($prefix)));
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }

    // Format số thành 3 chữ số: 001, 002, ...
    return $prefix . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
}

// Lấy danh sách nhà cung cấp
$ncc_list = $pdo->query("SELECT ma_ncc, ten_ncc FROM nha_cung_cap ORDER BY ten_ncc")->fetchAll(PDO::FETCH_ASSOC);

// Tạo mã phiếu tự động cho cả hai loại
$ma_phieu_vat_tu = taoMaPhieuNhapTuDong($pdo, 'vat_tu');
$ma_phieu_thanh_pham = taoMaPhieuNhapTuDong($pdo, 'thanh_pham');

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

// Lấy TẤT CẢ mặt hàng
$hang_hoa_list = $pdo->query("
    SELECT ma_hang, ten_hang, don_gia, ma_loai_hang 
    FROM hang_hoa 
    ORDER BY ten_hang
")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loai_phieu    = $_POST['loai_phieu'] ?? 'vat_tu'; // 'vat_tu' hoặc 'thanh_pham'
    $ma_phieu_nhap = trim($_POST['ma_phieu_nhap'] ?? '');
    $ngay_nhap     = $_POST['ngay_nhap'] ?? '';
    $ma_kho        = $_POST['ma_kho'] ?? null;
    $nguoi_giao    = trim($_POST['nguoi_giao'] ?? '');
    $don_vi_giao   = trim($_POST['don_vi_giao'] ?? '');
    $ghi_chu       = trim($_POST['ghi_chu'] ?? '');

    // Nếu là vật tư thì bắt buộc có NCC, nếu là thành phẩm thì không
    $ma_ncc = ($loai_phieu === 'vat_tu') ? ($_POST['ma_ncc'] ?? null) : null;

    $hang_hoa = $_POST['hang_hoa'] ?? [];

    // Validate
    if (empty($ma_phieu_nhap) || empty($ngay_nhap) || empty($ma_kho) || empty($hang_hoa)) {
        $error_message = 'Vui lòng điền đầy đủ thông tin bắt buộc.';
    } elseif ($loai_phieu === 'vat_tu' && empty($ma_ncc)) {
        $error_message = 'Vui lòng chọn nhà cung cấp cho phiếu nhập vật tư.';
    } else {
        try {
            $pdo->beginTransaction();

            // Kiểm tra mã phiếu trùng
            $check = $pdo->prepare("SELECT ma_phieu_nhap FROM phieu_nhap WHERE ma_phieu_nhap = ?");
            $check->execute([$ma_phieu_nhap]);
            if ($check->fetch()) {
                throw new Exception('Mã phiếu nhập đã tồn tại.');
            }

            // Thêm phiếu nhập
            $sql_pn = "INSERT INTO phieu_nhap 
                       (ma_phieu_nhap, ma_nd, ngay_nhap, nguoi_giao, don_vi_giao, loai_nhap, ma_kho, ma_ncc)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_pn = $pdo->prepare($sql_pn);
            $stmt_pn->execute([$ma_phieu_nhap, $_SESSION['MaND'], $ngay_nhap, $nguoi_giao, $don_vi_giao, $loai_phieu, $ma_kho, $ma_ncc]);

            // Thêm chi tiết phiếu
            $sql_ct = "INSERT INTO ct_phieu_nhap 
                       (ma_ctpn, ma_phieu_nhap, ma_hang, so_luong_nhap, don_gia, thanh_tien)
                       VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_ct = $pdo->prepare($sql_ct);

            $ds_ma_hang = [];

            foreach ($hang_hoa as $item) {
                if (empty($item['ma_hang']) || empty($item['so_luong']) || $item['so_luong'] <= 0) continue;

                $ma_hang    = $item['ma_hang'];
                $so_luong   = (int)$item['so_luong'];
                $don_gia    = (float)($item['don_gia'] ?? 0);
                $thanh_tien = $so_luong * $don_gia;

                $ma_ctpn = $ma_phieu_nhap . '-' . $ma_hang;

                $stmt_ct->execute([$ma_ctpn, $ma_phieu_nhap, $ma_hang, $so_luong, $don_gia, $thanh_tien]);

                $ds_ma_hang[] = $ma_hang;
            }

            $pdo->commit();

            cap_nhat_the_kho_theo_phieu($pdo, $ma_kho, $ds_ma_hang);

            $success_message = 'Thêm phiếu nhập thành công!';
            header("refresh:2;url=phieunhap.php");
        } catch (Exception $e) {
            // $pdo->rollBack();
            $error_message = 'Lỗi: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Thêm Phiếu Nhập Kho</title>
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
            <div class="flex items-center gap-4">
                <a href="phieunhap.php" class="text-text-secondary hover:text-primary">
                    <span class="material-symbols-outlined">arrow_back</span>
                </a>
                <h1 class="text-xl font-bold">Thêm phiếu nhập kho</h1>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6">
            <div class="max-w-5xl mx-auto">
                <!-- Tab Navigation -->
                <div class="mb-6 bg-surface-light dark:bg-surface-dark rounded-xl border border-border-light dark:border-border-dark p-2 flex gap-2">
                    <button type="button"
                        onclick="switchTab('vat_tu')"
                        id="tab-vat-tu"
                        class="flex-1 px-6 py-3 rounded-lg font-medium transition-all duration-200 bg-primary text-white shadow-sm">
                        <span class="flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-[20px]">inventory_2</span>
                            Nhập vật tư
                        </span>
                    </button>
                    <button type="button"
                        onclick="switchTab('thanh_pham')"
                        id="tab-thanh-pham"
                        class="flex-1 px-6 py-3 rounded-lg font-medium transition-all duration-200 bg-transparent text-text-secondary hover:bg-gray-100 dark:hover:bg-gray-800">
                        <span class="flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-[20px]">category</span>
                            Nhập thành phẩm
                        </span>
                    </button>
                </div>

                <div class="bg-surface-light dark:bg-surface-dark rounded-xl border border-border-light dark:border-border-dark shadow-sm p-6 md:p-8">

                    <?php if ($error_message): ?>
                        <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg flex gap-3">
                            <span class="material-symbols-outlined text-red-600">error</span>
                            <div class="text-sm text-red-800 dark:text-red-300"><?= htmlspecialchars($error_message) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($success_message): ?>
                        <div class="mb-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg flex gap-3">
                            <span class="material-symbols-outlined text-green-600">check_circle</span>
                            <div>
                                <p class="font-medium text-green-800 dark:text-green-300"><?= htmlspecialchars($success_message) ?></p>
                                <p class="text-xs text-green-600 dark:text-green-500 mt-1">Đang chuyển về danh sách...</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="phieuNhapForm">
                        <input type="hidden" name="loai_phieu" id="loai_phieu" value="vat_tu">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                            <div>
                                <label class="block text-sm font-medium mb-2">Mã phiếu nhập <span class="text-red-500">*</span></label>
                                <div class="flex gap-2">
                                    <input type="text"
                                        name="ma_phieu_nhap"
                                        id="ma_phieu_nhap"
                                        required
                                        value="<?= htmlspecialchars($ma_phieu_vat_tu) ?>"
                                        class="flex-1 px-4 py-2.5 border rounded-lg bg-background-light dark:bg-gray-800 focus:ring-2 focus:ring-primary"
                                        placeholder="VD: PN-VT-001" />
                                    <button type="button"
                                        onclick="generateMaPhieu()"
                                        class="px-4 py-2.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 border rounded-lg transition-colors"
                                        title="Tạo mã tự động">
                                        <span class="material-symbols-outlined text-[20px]">refresh</span>
                                    </button>
                                </div>
                                <p class="text-xs text-text-secondary mt-1">Mã tự động theo loại phiếu</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-2">Ngày nhập <span class="text-red-500">*</span></label>
                                <input type="date" name="ngay_nhap" required value="<?= date('Y-m-d') ?>" class="w-full px-4 py-2.5 border rounded-lg bg-background-light dark:bg-gray-800 focus:ring-2 focus:ring-primary" />
                            </div>

                            <!-- Nhà cung cấp - chỉ hiện với vật tư -->
                            <div id="ncc-field">
                                <label class="block text-sm font-medium mb-2">Nhà cung cấp <span class="text-red-500">*</span></label>
                                <select name="ma_ncc" id="ma_ncc" class="w-full px-4 py-2.5 border rounded-lg bg-background-light dark:bg-gray-800 focus:ring-2 focus:ring-primary">
                                    <option value="">-- Chọn nhà cung cấp --</option>
                                    <?php foreach ($ncc_list as $ncc): ?>
                                        <option value="<?= htmlspecialchars($ncc['ma_ncc']) ?>"><?= htmlspecialchars($ncc['ten_ncc']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-2">Đơn vị giao</label>
                                <input type="text" name="don_vi_giao" class="w-full px-4 py-2.5 border rounded-lg bg-background-light dark:bg-gray-800 focus:ring-2 focus:ring-primary" placeholder="VD: Phòng sản xuất" />
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
                                <label class="block text-sm font-medium mb-2">Kho nhập <span class="text-red-500">*</span></label>
                                <select name="ma_kho" id="ma_kho" required class="w-full px-4 py-2.5 border rounded-lg bg-background-light dark:bg-gray-800 focus:ring-2 focus:ring-primary">
                                    <option value="">-- Chọn kho --</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-2">Người giao</label>
                                <input type="text" name="nguoi_giao" class="w-full px-4 py-2.5 border rounded-lg bg-background-light dark:bg-gray-800 focus:ring-2 focus:ring-primary" placeholder="Tên người giao hàng" />
                            </div>
                        </div>

                        <div class="mb-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold">Chi tiết mặt hàng nhập</h3>
                                <button type="button" onclick="addRow()" class="flex items-center gap-2 px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg text-sm font-medium">
                                    <span class="material-symbols-outlined text-[18px]">add</span> Thêm hàng
                                </button>
                            </div>

                            <div class="overflow-x-auto border rounded-lg">
                                <table class="w-full" id="chiTietTable">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-text-secondary uppercase">Mặt hàng</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-text-secondary uppercase w-32">Số lượng</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-text-secondary uppercase w-40">Đơn giá (VNĐ)</th>
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
                            <a href="phieunhap.php" class="px-6 py-3 border rounded-lg bg-surface-light dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                Hủy
                            </a>
                            <button type="submit" class="px-6 py-3 bg-primary hover:bg-primary-hover text-white rounded-lg font-medium flex items-center gap-2 shadow-sm">
                                <span class="material-symbols-outlined">save</span>
                                Lưu phiếu nhập
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Dữ liệu từ PHP
        const loaiKhoToHangMap = {
            'L001': 'M001', // Kho nguyên liệu  → Nguyên liệu
            'L002': 'M002', // Kho nhiên liệu   → Nhiên liệu
            'L003': 'M003', // Kho phụ tùng     → Phụ tùng
            'L004': 'M004' // Kho thành phẩm   → Thành phẩm
        };

        const hangHoaList = <?= json_encode($hang_hoa_list) ?>;
        const khoList = <?= json_encode($kho_list) ?>;

        // Mã phiếu tự động từ PHP
        const maPhieuVatTu = '<?= $ma_phieu_vat_tu ?>';
        const maPhieuThanhPham = '<?= $ma_phieu_thanh_pham ?>';

        let rowIndex = 0;
        let currentTab = 'vat_tu';

        // Hàm tạo mã phiếu tự động
        function generateMaPhieu() {
            const input = document.getElementById('ma_phieu_nhap');
            if (currentTab === 'vat_tu') {
                input.value = maPhieuVatTu;
            } else {
                input.value = maPhieuThanhPham;
            }
        }

        // Chuyển đổi tab
        function switchTab(tabName) {
            currentTab = tabName;
            document.getElementById('loai_phieu').value = tabName;

            // Cập nhật UI tab
            const tabVatTu = document.getElementById('tab-vat-tu');
            const tabThanhPham = document.getElementById('tab-thanh-pham');

            if (tabName === 'vat_tu') {
                tabVatTu.className = 'flex-1 px-6 py-3 rounded-lg font-medium transition-all duration-200 bg-primary text-white shadow-sm';
                tabThanhPham.className = 'flex-1 px-6 py-3 rounded-lg font-medium transition-all duration-200 bg-transparent text-text-secondary hover:bg-gray-100 dark:hover:bg-gray-800';

                // Hiện trường nhà cung cấp
                document.getElementById('ncc-field').style.display = 'block';
                document.getElementById('ma_ncc').required = true;

                // Cập nhật mã phiếu và placeholder
                document.getElementById('ma_phieu_nhap').value = maPhieuVatTu;
                document.getElementById('ma_phieu_nhap').placeholder = 'VD: PN-VT-001';

                // Lọc loại kho vật tư (L001, L002, L003)
                filterLoaiKhoOptions(['L001', 'L002', 'L003']);
            } else {
                tabThanhPham.className = 'flex-1 px-6 py-3 rounded-lg font-medium transition-all duration-200 bg-primary text-white shadow-sm';
                tabVatTu.className = 'flex-1 px-6 py-3 rounded-lg font-medium transition-all duration-200 bg-transparent text-text-secondary hover:bg-gray-100 dark:hover:bg-gray-800';

                // Ẩn trường nhà cung cấp
                document.getElementById('ncc-field').style.display = 'none';
                document.getElementById('ma_ncc').required = false;

                // Cập nhật mã phiếu và placeholder
                document.getElementById('ma_phieu_nhap').value = maPhieuThanhPham;
                document.getElementById('ma_phieu_nhap').placeholder = 'VD: PN-TP-001';

                // Lọc loại kho thành phẩm (L004)
                filterLoaiKhoOptions(['L004']);
            }

            // Reset form
            document.getElementById('ma_loai_kho').value = '';
            filterKhoAndHang();
        }

        // Lọc options loại kho
        function filterLoaiKhoOptions(allowedCodes) {
            const select = document.getElementById('ma_loai_kho');
            const options = select.querySelectorAll('option');

            options.forEach(opt => {
                if (opt.value === '') return; // Giữ option "-- Chọn loại kho --"
                if (allowedCodes.includes(opt.value)) {
                    opt.style.display = 'block';
                } else {
                    opt.style.display = 'none';
                }
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

        // Khởi tạo
        document.addEventListener('DOMContentLoaded', () => {
            switchTab('vat_tu'); // Mặc định tab vật tư
        });
    </script>

</body>

</html>