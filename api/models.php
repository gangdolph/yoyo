<?php
require '../includes/db.php';
header('Content-Type: application/json');

$brand_id = isset($_GET['brand_id']) ? (int)$_GET['brand_id'] : 0;
$models = [];
if ($brand_id > 0 && $stmt = $conn->prepare('SELECT id, name FROM service_models WHERE brand_id=? ORDER BY name')) {
    $stmt->bind_param('i', $brand_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $models[] = ['id' => (int)$row['id'], 'name' => $row['name']];
    }
    $stmt->close();
}
echo json_encode($models);
