<?php

include '../include/connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Vui lòng nhập đầy đủ tài khoản và mật khẩu.';
    } else {
        $sql = "
            SELECT nd.ma_nd, nd.ten_nd, nd.mat_khau, vt.ten_vai_tro
            FROM nguoi_dung nd
            JOIN vai_tro vt ON nd.ma_vai_tro = vt.ma_vai_tro
            WHERE nd.ma_nd = :username
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Kiểm tra mật khẩu đã được hash bằng password_verify
        if ($user && password_verify($password, $user['mat_khau'])) {
            $_SESSION['MaND']      = $user['ma_nd'];
            $_SESSION['user_name'] = $user['ten_nd'];
            $_SESSION['role']      = trim($user['ten_vai_tro']);

            header('Location: ../index.php');
            exit;
        } else {
            $error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <title>Đăng nhập - Quản lý Kho</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">

    <script>
    tailwind.config = {
        theme: {
            fontFamily: {
                sans: ['Inter', 'sans-serif'],
            },
            extend: {
                colors: {
                    primary: '#2563EB'
                }
            }
        }
    }
    </script>
</head>

<body class="bg-slate-100 min-h-screen flex items-center justify-center">

    <div class="w-full max-w-md bg-white rounded-xl shadow-lg p-8">
        <div class="text-center mb-6">
            <span class="material-icons-round text-primary text-4xl">inventory_2</span>
            <h1 class="text-2xl font-bold mt-2">Quản lý Kho Hàng</h1>
            <p class="text-sm text-slate-500 mt-1">Đăng nhập hệ thống</p>
        </div>

        <?php if ($error): ?>
        <div class="mb-4 text-sm text-red-600 bg-red-50 border border-red-200 px-4 py-2 rounded">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-sm font-medium mb-1">Tên đăng nhập</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <span class="material-icons-round text-slate-400 text-xl">person</span>
                    </span>
                    <input
                        class="block w-full pl-10 pr-3 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-border-light dark:border-border-dark rounded-lg text-sm text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                        id="username" name="username" placeholder="VD: ND001" type="text" required
                        value="<?php echo htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES); ?>" />
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Mật khẩu</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <span class="material-icons-round text-slate-400 text-xl">lock</span>
                    </span>
                    <input
                        class="block w-full pl-10 pr-3 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-border-light dark:border-border-dark rounded-lg text-sm text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                        + id="password" name="password" placeholder="••••••••" type="password" required
                        value="<?php echo htmlspecialchars($_POST['password'] ?? '', ENT_QUOTES); ?>" />
                    <button type="button" onclick="togglePassword()"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer hover:text-primary transition-colors">
                        <span class="material-icons-round text-slate-400 text-xl"
                            id="passwordIcon">visibility_off</span>
                    </button>
                </div>
            </div>

            <button type="submit"
                class="w-full bg-primary hover:bg-blue-700 text-white font-semibold py-2.5 rounded-lg transition">
                Đăng nhập
            </button>
        </form>
    </div>
    <script>
    // Toggle password visibility
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const passwordIcon = document.getElementById('passwordIcon');

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            passwordIcon.textContent = 'visibility';
        } else {
            passwordInput.type = 'password';
            passwordIcon.textContent = 'visibility_off';
        }
    }

    // Check for dark mode preference initially
    const html = document.documentElement;
    if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia(
            '(prefers-color-scheme: dark)').matches)) {
        html.classList.add('dark');
    } else {
        html.classList.remove('dark');
    }
    </script>
</body>

</html>