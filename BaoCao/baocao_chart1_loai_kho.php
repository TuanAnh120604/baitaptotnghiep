<?php
include '../include/connect.php';
include '../include/permissions.php';
checkAccess('thongke');

// --- 1. KHỞI TẠO VÀ LẤY THAM SỐ FILTER ---
$role = trim($_SESSION['role'] ?? '');
$ma_nd = $_SESSION['MaND'] ?? null;

// Lấy tham số
$chart2_ma_vung = isset($_GET['chart2_ma_vung']) ? $_GET['chart2_ma_vung'] : '';
$chart2_loai_kho = isset($_GET['chart2_loai_kho']) ? $_GET['chart2_loai_kho'] : '';
$chart2_ma_hang = isset($_GET['chart2_ma_hang']) ? $_GET['chart2_ma_hang'] : ''; 
$chart2_ngay_bat_dau = isset($_GET['chart2_ngay_bat_dau']) ? $_GET['chart2_ngay_bat_dau'] : date('Y-01-01');
$chart2_ngay_ket_thuc = isset($_GET['chart2_ngay_ket_thuc']) ? $_GET['chart2_ngay_ket_thuc'] : date('Y-m-d');

// --- 2. LẤY DATA CHO DROPDOWN (Vùng, Loại kho, Hàng) ---

// A. Danh sách Vùng
$sql_vung = "SELECT * FROM vung_mien WHERE 1=1";
$params_vung = [];
if ($role === 'Thủ kho' && $ma_nd) {
    $sql_vung .= " AND ma_vung IN (SELECT DISTINCT ma_vung FROM kho WHERE ma_nd = ?)";
    $params_vung[] = $ma_nd;
} elseif ($role === 'Quản lý kho' && $ma_nd) {
    $sql_vung .= " AND ma_vung IN (SELECT DISTINCT ma_vung FROM phan_quyen WHERE ma_nd = ?)";
    $params_vung[] = $ma_nd;
}
$sql_vung .= " ORDER BY ten_vung";
$stmt_vung = $pdo->prepare($sql_vung);
$stmt_vung->execute($params_vung);
$danh_sach_vung = $stmt_vung->fetchAll(PDO::FETCH_ASSOC);

// B. Danh sách Loại kho
$sql_loai_kho = "SELECT DISTINCT lk.* FROM loai_kho lk JOIN kho k ON lk.ma_loai_kho = k.ma_loai_kho WHERE 1=1";
$params_loai_kho = [];
if ($role === 'Thủ kho' && $ma_nd) {
    $sql_loai_kho .= " AND k.ma_nd = ?";
    $params_loai_kho[] = $ma_nd;
} elseif ($role === 'Quản lý kho' && $ma_nd) {
    $sql_loai_kho .= " AND k.ma_kho IN (SELECT k2.ma_kho FROM kho k2 JOIN phan_quyen pq ON k2.ma_vung = pq.ma_vung AND k2.ma_loai_kho = pq.ma_loai_kho WHERE pq.ma_nd = ?)";
    $params_loai_kho[] = $ma_nd;
}
if (!empty($chart2_ma_vung)) {
    $sql_loai_kho .= " AND k.ma_vung = ?";
    $params_loai_kho[] = $chart2_ma_vung;
}
$sql_loai_kho .= " ORDER BY lk.ten_loai_kho";
$stmt_loai_kho = $pdo->prepare($sql_loai_kho);
$stmt_loai_kho->execute($params_loai_kho);
$danh_sach_loai_kho = $stmt_loai_kho->fetchAll(PDO::FETCH_ASSOC);

// C. Danh sách Hàng hóa
// Chỉ lấy những mặt hàng có tồn kho > 0 tại ngày kết thúc (lọc theo kho/vùng/loại và quyền)
// Trước hết lấy danh sách kho hợp lệ
$params_kho = [];
$sql_kho_ids = "SELECT ma_kho FROM kho WHERE 1=1";
if ($role === 'Thủ kho' && $ma_nd) {
    $sql_kho_ids .= " AND ma_nd = ?";
    $params_kho[] = $ma_nd;
} elseif ($role === 'Quản lý kho' && $ma_nd) {
    $sql_kho_ids .= " AND ma_kho IN (SELECT k2.ma_kho FROM kho k2 JOIN phan_quyen pq ON k2.ma_vung = pq.ma_vung AND k2.ma_loai_kho = pq.ma_loai_kho WHERE pq.ma_nd = ?)";
    $params_kho[] = $ma_nd;
}
if (!empty($chart2_ma_vung)) { $sql_kho_ids .= " AND ma_vung = ?"; $params_kho[] = $chart2_ma_vung; }
if (!empty($chart2_loai_kho)) { $sql_kho_ids .= " AND ma_loai_kho = ?"; $params_kho[] = $chart2_loai_kho; }
$stmt_kids = $pdo->prepare($sql_kho_ids);
$stmt_kids->execute($params_kho);
$valid_kho_ids = $stmt_kids->fetchAll(PDO::FETCH_COLUMN);
if (empty($valid_kho_ids)) $valid_kho_ids = ['0'];
$placeholders = implode(',', array_fill(0, count($valid_kho_ids), '?'));
$where_kho_sql = " AND t.ma_kho IN ($placeholders) ";

// Tham số cho truy vấn hàng: 2 ngày (đến ngày kết thúc) + danh sách kho
$params_hang = [$chart2_ngay_ket_thuc, $chart2_ngay_ket_thuc];
foreach ($valid_kho_ids as $id) $params_hang[] = $id;

$sql_hang = "SELECT DISTINCT h.ma_hang, h.ten_hang, h.don_vi_tinh
FROM hang_hoa h
JOIN (
    SELECT t.ma_hang, SUM(t.qty) AS ton
    FROM (
        SELECT ct.ma_hang, ct.so_luong_nhap AS qty, pn.ma_kho, pn.ngay_nhap AS ngay
        FROM ct_phieu_nhap ct JOIN phieu_nhap pn ON ct.ma_phieu_nhap = pn.ma_phieu_nhap
        WHERE pn.trang_thai = 'da_xac_nhan' AND pn.ngay_nhap <= ?
        UNION ALL
        SELECT ct.ma_hang, -ct.so_luong_xuat AS qty, px.ma_kho, px.ngay_xuat AS ngay
        FROM ct_phieu_xuat ct JOIN phieu_xuat px ON ct.ma_phieu_xuat = px.ma_phieu_xuat
        WHERE px.trang_thai = 'da_xac_nhan' AND px.ngay_xuat <= ?
    ) t
    WHERE 1=1 $where_kho_sql
    GROUP BY t.ma_hang
    HAVING SUM(t.qty) > 0
) s ON h.ma_hang = s.ma_hang
ORDER BY h.ten_hang";

$stmt_hang = $pdo->prepare($sql_hang);
$stmt_hang->execute($params_hang);
$danh_sach_hang = $stmt_hang->fetchAll(PDO::FETCH_ASSOC);

// --- 3. LẤY DANH SÁCH KHO ---
$sql_kho = "SELECT k.ma_kho, k.ten_kho FROM kho k WHERE 1=1";
$params_kho = [];
if ($role === 'Thủ kho' && $ma_nd) {
    $sql_kho .= " AND k.ma_nd = ?";
    $params_kho[] = $ma_nd;
} elseif ($role === 'Quản lý kho' && $ma_nd) {
    $sql_kho .= " AND k.ma_kho IN (SELECT k2.ma_kho FROM kho k2 JOIN phan_quyen pq ON k2.ma_vung = pq.ma_vung AND k2.ma_loai_kho = pq.ma_loai_kho WHERE pq.ma_nd = ?)";
    $params_kho[] = $ma_nd;
}
if (!empty($chart2_ma_vung)) { $sql_kho .= " AND k.ma_vung = ?"; $params_kho[] = $chart2_ma_vung; }
if (!empty($chart2_loai_kho)) { $sql_kho .= " AND k.ma_loai_kho = ?"; $params_kho[] = $chart2_loai_kho; }
$sql_kho .= " ORDER BY k.ma_kho";
$stmt_kho = $pdo->prepare($sql_kho);
$stmt_kho->execute($params_kho);
$danh_sach_kho_filtered = $stmt_kho->fetchAll(PDO::FETCH_ASSOC);


// --- 4. TÍNH TOÁN DỮ LIỆU BIỂU ĐỒ & THẺ TỔNG HỢP ---
$labels_ngay = [];
$ts_cur = strtotime($chart2_ngay_bat_dau);
$ts_end = strtotime($chart2_ngay_ket_thuc);
while ($ts_cur <= $ts_end) {
    $labels_ngay[] = date('Y-m-d', $ts_cur);
    $ts_cur = strtotime('+1 day', $ts_cur);
}

// Bảng màu cố định (Đưa lên PHP để đồng bộ màu giữa Thẻ hiển thị và Đường biểu đồ)
$chart_colors = [
    '#2563eb', '#dc2626', '#16a34a', '#d97706', '#9333ea', 
    '#0891b2', '#db2777', '#4b5563', '#ea580c', '#84cc16'
];

$datasets = [];
$summary_cards = []; // Mảng chứa dữ liệu hiển thị thẻ
$ten_hang_selected = "";
$don_vi_tinh_selected = "";
$tong_ton_tat_ca_kho = 0; // Biến tính tổng toàn bộ

if (!empty($chart2_ma_hang) && !empty($danh_sach_kho_filtered)) {
    // Lấy thông tin hàng
    foreach ($danh_sach_hang as $h) {
        if ($h['ma_hang'] == $chart2_ma_hang) {
            $ten_hang_selected = $h['ten_hang'];
            $don_vi_tinh_selected = $h['don_vi_tinh'];
            break;
        }
    }
    // Fallback nếu không tìm thấy trong list
    if (empty($ten_hang_selected)) {
        $stmt_one = $pdo->prepare("SELECT ten_hang, don_vi_tinh FROM hang_hoa WHERE ma_hang = ?");
        $stmt_one->execute([$chart2_ma_hang]);
        $row_one = $stmt_one->fetch();
        if ($row_one) {
            $ten_hang_selected = $row_one['ten_hang'];
            $don_vi_tinh_selected = $row_one['don_vi_tinh'];
        }
    }

    $color_index = 0;
    foreach ($danh_sach_kho_filtered as $kho) {
        $mk = $kho['ma_kho'];
        
        // A. Tồn đầu kỳ
        $sql_nhap_dk = "SELECT SUM(ct.so_luong_nhap) FROM phieu_nhap pn JOIN ct_phieu_nhap ct ON pn.ma_phieu_nhap = ct.ma_phieu_nhap WHERE pn.ma_kho = ? AND ct.ma_hang = ? AND pn.ngay_nhap < ? AND pn.trang_thai = 'da_xac_nhan'";
        $stmt = $pdo->prepare($sql_nhap_dk);
        $stmt->execute([$mk, $chart2_ma_hang, $chart2_ngay_bat_dau]);
        $nhap_dk = $stmt->fetchColumn() ?: 0;

        $sql_xuat_dk = "SELECT SUM(ct.so_luong_xuat) FROM phieu_xuat px JOIN ct_phieu_xuat ct ON px.ma_phieu_xuat = ct.ma_phieu_xuat WHERE px.ma_kho = ? AND ct.ma_hang = ? AND px.ngay_xuat < ? AND px.trang_thai = 'da_xac_nhan'";
        $stmt = $pdo->prepare($sql_xuat_dk);
        $stmt->execute([$mk, $chart2_ma_hang, $chart2_ngay_bat_dau]);
        $xuat_dk = $stmt->fetchColumn() ?: 0;
        
        $ton_dau_ky = $nhap_dk - $xuat_dk;

        // B. Biến động
        $changes = array_fill_keys($labels_ngay, 0);
        
        $sql_n = "SELECT DATE(pn.ngay_nhap) as ngay, SUM(ct.so_luong_nhap) as sl FROM phieu_nhap pn JOIN ct_phieu_nhap ct ON pn.ma_phieu_nhap = ct.ma_phieu_nhap WHERE pn.ma_kho = ? AND ct.ma_hang = ? AND pn.ngay_nhap BETWEEN ? AND ? AND pn.trang_thai = 'da_xac_nhan' GROUP BY DATE(pn.ngay_nhap)";
        $stmt = $pdo->prepare($sql_n);
        $stmt->execute([$mk, $chart2_ma_hang, $chart2_ngay_bat_dau, $chart2_ngay_ket_thuc]);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $changes[$r['ngay']] += $r['sl'];

        $sql_x = "SELECT DATE(px.ngay_xuat) as ngay, SUM(ct.so_luong_xuat) as sl FROM phieu_xuat px JOIN ct_phieu_xuat ct ON px.ma_phieu_xuat = ct.ma_phieu_xuat WHERE px.ma_kho = ? AND ct.ma_hang = ? AND px.ngay_xuat BETWEEN ? AND ? AND px.trang_thai = 'da_xac_nhan' GROUP BY DATE(px.ngay_xuat)";
        $stmt = $pdo->prepare($sql_x);
        $stmt->execute([$mk, $chart2_ma_hang, $chart2_ngay_bat_dau, $chart2_ngay_ket_thuc]);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $changes[$r['ngay']] -= $r['sl'];

        // C. Lũy kế
        $data_points = [];
        $curr = $ton_dau_ky;
        foreach ($labels_ngay as $d) {
            $curr += $changes[$d];
            $data_points[] = $curr;
        }

        // Lấy màu cho kho này
        $this_color = $chart_colors[$color_index % count($chart_colors)];

        // Đẩy vào dataset vẽ biểu đồ
        $datasets[] = [
            'label' => $kho['ten_kho'],
            'data' => $data_points,
            'borderColor' => $this_color, // Gán màu từ PHP
            'backgroundColor' => $this_color
        ];

        // Đẩy vào mảng Summary Card (Lấy giá trị cuối cùng trong mảng data_points làm tồn cuối kỳ)
        $ton_cuoi_ky_nay = end($data_points);
        $summary_cards[] = [
            'ten_kho' => $kho['ten_kho'],
            'ton_kho' => $ton_cuoi_ky_nay,
            'color' => $this_color
        ];
        
        $tong_ton_tat_ca_kho += $ton_cuoi_ky_nay;
        $color_index++;
    }
}
?>
<!DOCTYPE html>
<html lang="vi" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biến Động Tồn Kho - Báo Cáo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
</head>

<body class="bg-background-light dark:bg-background-dark text-slate-800 dark:text-slate-200 transition-colors duration-200 h-screen flex flex-col overflow-hidden">
    <?php include '../include/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        <?php include '../include/header.php'; ?>

        <main class="flex-1 overflow-y-auto bg-white dark:bg-gray-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8">

                <div class="mb-6">
                    <a href="baocao_bancandoi.php"
                       class="inline-flex items-center px-4 py-2 bg-blue-500 hover:bg-blue-700 text-white font-medium rounded-lg shadow transition-colors">
                        ← Quay lại
                    </a>
                </div>

                <div class="text-center mb-10">
                    <h1 class="text-3xl md:text-4xl font-bold text-blue-500 dark:text-blue-400 tracking-tight uppercase">
                        📊 Biến động tồn của từng loại kho
                    </h1>
                    <p class="mt-3 text-lg text-gray-600 dark:text-gray-400">
                        Biển đồ tồn kho theo thời gian của: <span class="font-bold text-blue-600"><?= $ten_hang_selected ?></span>
                    </p>
                </div>

                <div class="bg-gray-100 dark:bg-gray-700 rounded-xl shadow-md p-6 mb-10">
                    <h3 class="text-xl font-semibold mb-5 text-gray-800 dark:text-gray-100">Bộ lọc biểu đồ</h3>
                    <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4" id="filterForm">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Vùng miền</label>
                            <select name="chart2_ma_vung" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Tất cả</option>
                                <?php foreach ($danh_sach_vung as $v): ?>
                                    <option value="<?= $v['ma_vung'] ?>" <?= $chart2_ma_vung == $v['ma_vung'] ? 'selected' : '' ?>><?= $v['ten_vung'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Loại kho</label>
                            <select name="chart2_loai_kho" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Tất cả</option>
                                <?php foreach ($danh_sach_loai_kho as $lk): ?>
                                    <option value="<?= $lk['ma_loai_kho'] ?>" <?= $chart2_loai_kho == $lk['ma_loai_kho'] ? 'selected' : '' ?>><?= $lk['ten_loai_kho'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="xl:col-span-1">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Mặt hàng</label>
                            <select name="chart2_ma_hang" required class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Chọn mặt hàng</option>
                                <?php foreach ($danh_sach_hang as $h): ?>
                                    <option value="<?= $h['ma_hang'] ?>" <?= $chart2_ma_hang == $h['ma_hang'] ? 'selected' : '' ?>><?= $h['ten_hang'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Từ ngày</label>
                            <input type="date" name="chart2_ngay_bat_dau" value="<?= $chart2_ngay_bat_dau ?>" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Đến ngày</label>
                            <input type="date" name="chart2_ngay_ket_thuc" value="<?= $chart2_ngay_ket_thuc ?>" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div class="flex flex-col justify-end">
                            <button type="submit" class="px-6 py-2.5 bg-blue-500 hover:bg-blue-700 text-white font-medium rounded-lg shadow transition-colors">Lọc dữ liệu</button>
                        </div>
                    </form>
                </div>

                <?php if (!empty($summary_cards)): ?>
                <div class="mb-10">
                    <h3 class="text-xl font-semibold mb-5 text-gray-800 dark:text-gray-100">Tổng hợp tồn kho cuối kỳ:</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">
                        <div class="bg-blue-50 dark:bg-gray-700 p-4 rounded-xl border border-blue-200 shadow-sm">
                            <span class="text-sm text-blue-600 dark:text-blue-300 uppercase font-bold">Tổng tất cả</span>
                            <div class="text-2xl font-bold text-slate-800 dark:text-white mt-2">
                                <?= number_format($tong_ton_tat_ca_kho, 0, ',', '.') ?> <span class="text-sm font-normal"><?= $don_vi_tinh_selected ?></span>
                            </div>
                        </div>
                        <?php foreach ($summary_cards as $card): ?>
                            <div class="bg-white dark:bg-gray-700 p-4 rounded-xl shadow-sm border-l-4" style="border-left-color: <?= $card['color'] ?>;">
                                <span class="text-sm font-semibold text-gray-600 dark:text-gray-300 truncate block"><?= $card['ten_kho'] ?></span>
                                <div class="mt-2 text-xl font-bold text-slate-800 dark:text-white">
                                    <?= number_format($card['ton_kho'], 0, ',', '.') ?> <span class="text-xs font-normal"><?= $don_vi_tinh_selected ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="bg-white dark:bg-gray-700 rounded-xl shadow-lg p-6">
                    <div class="h-[450px] md:h-[550px]">
                        <?php if (empty($chart2_ma_hang)): ?>
                            <div class="flex flex-col items-center justify-center h-full text-gray-400">
                                <p class="text-lg">Vui lòng chọn mặt hàng để hiển thị biểu đồ</p>
                            </div>
                        <?php else: ?>
                            <canvas id="chartHangHoa"></canvas>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    <?php if (!empty($datasets)): ?>
        const ctx = document.getElementById('chartHangHoa').getContext('2d');
        const labels = <?= json_encode($labels_ngay) ?>;
        const rawDatasets = <?= json_encode($datasets) ?>;
        const donVi = "<?= $don_vi_tinh_selected ?>";

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: rawDatasets.map(ds => ({
                    label: ds.label,
                    data: ds.data,
                    borderColor: ds.borderColor,
                    // Thêm phần đổ màu và các điểm nút theo yêu cầu
                    backgroundColor: ds.borderColor.replace(')', ', 0.1)').replace('rgb', 'rgba'), // Làm mờ màu nền 10%
                    // fill: true,             // Đổ màu vùng dưới (giống hình mẫu)
                    tension: 0.3,           // Độ cong của đường
                    borderWidth: 2,
                    pointRadius: 4,          // Kích thước điểm nút (giống hình mẫu)
                    pointHoverRadius: 6,
                    pointBackgroundColor: ds.borderColor,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 1
                }))
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y.toLocaleString('vi-VN') + ' ' + donVi;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        title: { display: true, text: 'Ngày' },
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Số lượng (' + donVi + ')' },
                        ticks: { callback: v => v.toLocaleString('vi-VN') }
                    }
                }
            }
        });
    <?php endif; ?>
    </script>
</body>
</html>