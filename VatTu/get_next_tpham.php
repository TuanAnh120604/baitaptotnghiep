<?php
include '../include/connect.php';

header('Content-Type: application/json');

try {
    // Lấy mã hàng lớn nhất có định dạng H###
    $sql_max_ma = "SELECT MAX(ma_hang) AS max_ma FROM hang_hoa WHERE ma_hang LIKE 'H%' AND LENGTH(ma_hang) = 4";
    $stmt_max = $pdo->prepare($sql_max_ma);
    $stmt_max->execute();
    $result = $stmt_max->fetch(PDO::FETCH_ASSOC);
    
    $max_ma = $result['max_ma'] ?? 'H000';
    
    // Trích xuất số và tăng lên 1
    $next_number = 1;
    if (preg_match('/H(\d+)/', $max_ma, $matches)) {
        $next_number = (int)$matches[1] + 1;
    }
    
    // Định dạng mã hàng: H001, H002, ...
    $ma_hang = 'H' . str_pad($next_number, 3, '0', STR_PAD_LEFT);
    
    // Kiểm tra trùng mã hàng (phòng trường hợp có mã không theo định dạng)
    do {
        $check = $pdo->prepare("SELECT COUNT(*) FROM hang_hoa WHERE ma_hang = ?");
        $check->execute([$ma_hang]);
        $exists = $check->fetchColumn() > 0;
        
        if ($exists) {
            // Nếu trùng, tìm mã tiếp theo
            $next_number++;
            $ma_hang = 'H' . str_pad($next_number, 3, '0', STR_PAD_LEFT);
        }
    } while ($exists);
    
    echo json_encode([
        'success' => true,
        'ma_hang' => $ma_hang
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
