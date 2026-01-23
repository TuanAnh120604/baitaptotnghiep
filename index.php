<?php
session_start();
include './include/connect.php';
include './include/permissions.php';

// Kiểm tra quyền
if (!canView('baocao') && !canView('thongke')) {
    http_response_code(403);
    echo 'Bạn không có quyền truy cập báo cáo. Vui lòng liên hệ quản trị viên.';
    exit;
}

// Chuyển hướng đến báo cáo chính
header('Location: /baitaptotnghiep-main/BaoCao/baocao_bancandoi.php');
exit;
?>
