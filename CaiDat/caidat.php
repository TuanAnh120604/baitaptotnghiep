<?php
include '../include/connect.php';
include '../include/permissions.php';
checkAccess('caidat');
$user_id = $_SESSION['MaND'] ?? '';
$ho_ten = '';
$ten_vai_tro = '';
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ho_ten   = trim($_POST['ho_ten'] ?? '');
    $mat_khau = trim($_POST['mat_khau'] ?? '');

    if ($ho_ten === '') {
        $error = 'Họ và tên không được để trống';
    } else {

        if ($mat_khau !== '') {

            $mat_khau_hash = password_hash($mat_khau, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE nguoi_dung
                SET ten_nd = :ten_nd,
                    mat_khau = :mat_khau
                WHERE ma_nd = :ma_nd
            ");
            $stmt->execute([
                'ten_nd'   => $ho_ten,
                'mat_khau' => $mat_khau_hash,
                'ma_nd'    => $user_id
            ]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE nguoi_dung
                SET ten_nd = :ten_nd
                WHERE ma_nd = :ma_nd
            ");
            $stmt->execute([
                'ten_nd' => $ho_ten,
                'ma_nd'  => $user_id
            ]);
        }

        $success = 'Cập nhật thông tin thành công';
    }
}
$stmt = $pdo->prepare("
    SELECT nd.ten_nd, vt.ten_vai_tro
    FROM nguoi_dung nd
    LEFT JOIN vai_tro vt ON nd.ma_vai_tro = vt.ma_vai_tro
    WHERE nd.ma_nd = :ma_nd
");
$stmt->execute(['ma_nd' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    $ho_ten = htmlspecialchars($user['ten_nd']);
    $ten_vai_tro = htmlspecialchars($user['ten_vai_tro'] ?? 'Chưa xác định');
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Cài đặt tài khoản - Chỉnh sửa thông tin</title>

    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet" />
</head>
    <body
        class="bg-background-light dark:bg-background-dark text-gray-800 dark:text-gray-100 font-sans antialiased min-h-screen transition-colors duration-200">

    <?php include '../include/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">
    <?php include '../include/header.php'; ?>

    <main class="flex-1 overflow-x-hidden overflow-y-auto p-6 md:p-8">
    <div class="w-full">

    <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-8">
        Cài đặt
    </h1>

    <section
        class="bg-surface-light dark:bg-surface-dark rounded-lg shadow-sm border border-border-light dark:border-border-dark overflow-hidden">

    <div class="p-6 border-b border-border-light dark:border-border-dark flex items-center">
        <span class="material-icons-round text-primary text-2xl mr-3">
            person_outline
        </span>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">
            Thông tin tài khoản
        </h2>
    </div>

    <form method="POST" class="p-8">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

    <!-- MÃ NGƯỜI DÙNG -->
    <div class="space-y-2">
        <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">
            Mã người dùng
        </label>
        <input
            type="text"
            readonly
            value="<?php echo htmlspecialchars($user_id); ?>"
            class="w-full px-4 py-2.5 rounded-lg border border-border-light dark:border-border-dark
            bg-gray-100 dark:bg-gray-700 cursor-not-allowed
            text-sm text-gray-900 dark:text-gray-100" />
    </div>

    <!-- HỌ TÊN -->
    <div class="space-y-2">
        <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">
            Họ và tên
        </label>
        <input
            name="ho_ten"
            type="text"
            required
            value="<?php echo $ho_ten; ?>"
            class="w-full px-4 py-2.5 rounded-lg border border-border-light dark:border-border-dark
            bg-gray-50 dark:bg-gray-800 focus:ring-2 focus:ring-primary
            text-sm text-gray-900 dark:text-gray-100" />
    </div>

    <!-- CHỨC VỤ -->
    <div class="space-y-2">
        <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">
            Chức vụ
        </label>
        <input
            type="text"
            readonly
            value="<?php echo $ten_vai_tro; ?>"
            class="w-full px-4 py-2.5 rounded-lg border border-border-light dark:border-border-dark
            bg-gray-100 dark:bg-gray-700 cursor-not-allowed
            text-sm text-gray-900 dark:text-gray-100" />
    </div>

    <!-- MẬT KHẨU -->
    <div class="space-y-2 relative">
        <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">
            Mật khẩu (để trống nếu không đổi)
        </label>
        <input
            name="mat_khau"
            type="password"
            class="w-full px-4 py-2.5 rounded-lg border border-border-light dark:border-border-dark
            bg-gray-50 dark:bg-gray-800 focus:ring-2 focus:ring-primary
            text-sm text-gray-900 dark:text-gray-100" />
            <span
                class="material-icons-round absolute right-3 top-9 cursor-pointer text-gray-500"
                onclick="togglePassword()">
                lock
                </span>
            </div>
            <div class="md:col-span-2 flex justify-between items-center pt-4">
            <span class="text-sm">
            <?php if ($success): ?>
                <span class="text-green-600"><?php echo $success; ?></span>
            <?php elseif ($error): ?>
                <span class="text-red-600"><?php echo $error; ?></span>
            <?php endif; ?>
            </span>
        <button
            type="submit"
            class="px-8 py-2.5 bg-primary text-white font-medium rounded-lg
            hover:bg-primary-dark transition-colors shadow-sm">
            Lưu thay đổi
        </button>
    <script>
    function togglePassword() {
        const input = document.querySelector('input[name="mat_khau"]');
        if (!input) return;
        input.type = input.type === 'password' ? 'text' : 'password';
    }
    </script>

    </body>
</html>
