<?php
include '../include/connect.php';

header('Content-Type: application/json');

// Lấy tham số phân trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

// Lấy tham số lọc
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    // Đếm tổng số bản ghi
    $count_query = 'SELECT COUNT(*) as total FROM nguoi_dung nd';
    $count_params = [];

    if ($role_filter !== 'all') {
        $count_query .= ' LEFT JOIN vai_tro vt ON nd.ma_vai_tro = vt.ma_vai_tro WHERE ';
        if ($role_filter === 'quan-ly-kho') {
            $count_query .= 'vt.ten_vai_tro LIKE :role';
            $count_params[':role'] = '%Quản lý kho%';
        } elseif ($role_filter === 'thu-kho') {
            $count_query .= 'vt.ten_vai_tro LIKE :role';
            $count_params[':role'] = '%Thủ kho%';
        }
    }

    if (!empty($search)) {
        if (strpos($count_query, 'WHERE') === false) {
            $count_query .= ' WHERE ';
        } else {
            $count_query .= ' AND ';
        }
        $count_query .= '(nd.ten_nd LIKE :search OR nd.ma_nd LIKE :search)';
        $count_params[':search'] = '%' . $search . '%';
    }

    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($count_params);
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $limit);

    // Lấy dữ liệu với phân trang
    $data_query = '
        SELECT nd.ma_nd, nd.ten_nd, nd.ma_vai_tro, vt.ten_vai_tro
        FROM nguoi_dung nd
        LEFT JOIN vai_tro vt ON nd.ma_vai_tro = vt.ma_vai_tro
    ';

    $where_conditions = [];
    $query_params = [];

    if ($role_filter !== 'all') {
        if ($role_filter === 'quan-ly-kho') {
            $where_conditions[] = 'vt.ten_vai_tro LIKE :role';
            $query_params[':role'] = '%Quản lý kho%';
        } elseif ($role_filter === 'thu-kho') {
            $where_conditions[] = 'vt.ten_vai_tro LIKE :role';
            $query_params[':role'] = '%Thủ kho%';
        }
    }

    if (!empty($search)) {
        $where_conditions[] = '(nd.ten_nd LIKE :search OR nd.ma_nd LIKE :search)';
        $query_params[':search'] = '%' . $search . '%';
    }

    if (!empty($where_conditions)) {
        $data_query .= ' WHERE ' . implode(' AND ', $where_conditions);
    }

    $data_query .= ' ORDER BY nd.ma_nd LIMIT :limit OFFSET :offset';

    $data_stmt = $pdo->prepare($data_query);
    $data_stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $data_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    foreach ($query_params as $key => $value) {
        $data_stmt->bindValue($key, $value);
    }

    $data_stmt->execute();
    $users = $data_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Trả về dữ liệu JSON
    echo json_encode([
        'success' => true,
        'data' => $users,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_records' => $total_records,
            'limit' => $limit,
            'has_prev' => $page > 1,
            'has_next' => $page < $total_pages
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>