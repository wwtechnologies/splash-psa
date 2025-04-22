<?php
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="sample_assets_import.csv"');

$output = fopen('php://output', 'w');

// Sample headers for asset import
fputcsv($output, [
    'asset_name',
    'asset_type',
    'asset_make',
    'asset_model',
    'asset_serial',
    'asset_os',
    'asset_description',
    'asset_location',
    'asset_client',
    'asset_contact',
    'asset_ip',
    'asset_mac'
]);

// Optionally, add a sample row
fputcsv($output, [
    'Sample Asset',
    'laptop',
    'Dell',
    'Latitude 5420',
    'ABC123456',
    'Windows 11 Pro',
    'Sample description',
    'Main Office',
    'Acme Corp',
    'John Doe',
    '192.168.1.10',
    '00:11:22:33:44:55'
]);

fclose($output);
exit;
?>
