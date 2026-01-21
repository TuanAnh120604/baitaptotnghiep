<?php
include '../include/connect.php';
include '../include/permissions.php';
checkAccess('donvitinh');

$don_vi_tinh = [];
  try {
    $stmt = $pdo->prepare('SELECT DISTINCT don_vi_tinh FROM hang_hoa WHERE don_vi_tinh IS NOT NULL AND don_vi_tinh != "" ORDER BY don_vi_tinh');
    $stmt->execute();
    $don_vi_tinh = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (Exception $e) {
    // Nếu có lỗi, hiển thị thông báo
    $error_message = 'Lỗi khi lấy dữ liệu: ' . $e->getMessage();
  }
?>
<!DOCTYPE html>
<html class="light" lang="vi">

<head>
  <meta charset="utf-8" />
  <meta content="width=device-width, initial-scale=1.0" name="viewport" />
  <title>Quản lý Đơn vị tính</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
  <script>
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          colors: {
            "primary": "#137fec",
            "background-light": "#f6f7f8",
            "background-dark": "#101922",
          },
          fontFamily: {
            "display": ["Inter", "sans-serif"]
          },
          borderRadius: {
            "DEFAULT": "0.25rem",
            "lg": "0.5rem",
            "xl": "0.75rem",
            "full": "9999px"
          },
        },
      },
    }
  </script>
</head>

<body class="bg-background-light dark:bg-background-dark font-display text-[#111418] dark:text-white min-h-screen min-h-0">

  <?php include '../include/sidebar.php'; ?>
  <div class="flex-1 flex flex-col min-h-screen relative">
    <?php include '../include/header.php'; ?>
    <main class="flex-1 overflow-y-auto bg-background-light dark:bg-background-dark p-8 min-h-0">

      <div class="max-w-[1200px] mx-auto flex flex-col gap-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div class="flex flex-col gap-1">
            <h1 class="text-[#111418] dark:text-white text-2xl font-black tracking-tight">Quản lý đơn vị tính</h1>
          </div>
        </div>
        <div class="bg-white dark:bg-[#1a2632] border border-[#e5e7eb] dark:border-[#2a3642] rounded-xl shadow-sm overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="bg-[#f9fafb] dark:bg-[#24303f] border-b border-[#e5e7eb] dark:border-[#2a3642]">
                  <th class="py-3 px-6 text-xs font-semibold uppercase tracking-wider text-[#617589] w-16">STT</th>
                  <th class="py-3 px-6 text-xs font-semibold uppercase tracking-wider text-[#617589]">Đơn vị tính</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[#e5e7eb] dark:divide-[#2a3642]">
                <?php if (!empty($don_vi_tinh)): ?>
                  <?php foreach ($don_vi_tinh as $index => $item): ?>
                  <tr class="hover:bg-gray-50 dark:hover:bg-[#24303f] transition-colors group">
                    <td class="py-4 px-6 text-sm font-medium text-[#111418] dark:text-white"><?php echo $index + 1; ?></td>
                    <td class="py-4 px-6 text-sm font-medium text-[#111418] dark:text-white"><?php echo htmlspecialchars($item['don_vi_tinh']); ?></td>
                  </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="2" class="py-8 px-6 text-center text-sm text-[#617589] dark:text-[#9ca3af]">Không có dữ liệu đơn vị tính</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

        </div>
      </div>
    </main>
  </div>
  </div>

</body>

</html>
