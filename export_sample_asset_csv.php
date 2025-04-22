<?php
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="sample_assets_import.csv"');

$output = fopen('php://output', 'w');

// Sample headers for asset import
fputcsv($output, [
    'Name',
    'Description',
    'Type',
    'Make',
    'Model',
    'Serial',
    'OS',
    'Assigned To',
    'Location',
    'Physical Location'
]);

// Optionally, add a sample row
fputcsv($output, [
    'Sample Asset',
    'Sample description',
    'Laptop',
    'Dell',
    'Latitude 5420',
    'ABC123456',
    'Windows 11 Pro',
    'John Doe',
    'Main Office',
    'HQ Storage'
]);

fclose($output);
exit;
?>
