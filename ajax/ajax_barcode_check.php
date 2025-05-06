<?php

require_once '../includes/ajax_header.php';

header('Content-Type: application/json');

$barcode = trim($_POST['barcode'] ?? '');

if (empty($barcode)) {
    echo json_encode(['unique' => false, 'error' => 'No barcode provided']);
    exit;
}

$stmt = $mysqli->prepare("SELECT asset_id FROM assets WHERE asset_asset_inventory_barcode = ? LIMIT 1");
$stmt->bind_param('s', $barcode);
$stmt->execute();
$stmt->store_result();

$is_unique = $stmt->num_rows === 0;

echo json_encode(['unique' => $is_unique]);
$stmt->close();