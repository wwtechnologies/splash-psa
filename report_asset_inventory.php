<?php

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "includes/inc_all_reports.php";

// Ensure user has support module access since this is an asset report
enforceUserPermission('module_support');

//Get the asset type and status arrays
require_once "includes/get_settings.php";

//Initialize variables
$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : '';

//Set up query parts
$where_array = array();
if ($client_id > 0) {
    $where_array[] = "asset_client_id = $client_id";
}
if (!empty($status)) {
    $where_array[] = "asset_status = '$status'";
}
if (!empty($type)) {
    $where_array[] = "asset_type = '$type'";
}

//Asset history logs
$where = '';
if (!empty($where_array)) {
    $where = 'WHERE ' . implode(' AND ', $where_array);
}

$sql = mysqli_query($mysqli,"SELECT * FROM assets
    LEFT JOIN clients ON asset_client_id = client_id
    LEFT JOIN contacts ON asset_contact_id = contact_id
    LEFT JOIN locations ON asset_location_id = location_id
    LEFT JOIN vendors ON asset_vendor_id = vendor_id
    $where
    ORDER BY asset_name ASC"
) or die("SQL Error: " . mysqli_error($mysqli));

$sql_clients = mysqli_query($mysqli,"SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL ORDER BY client_name ASC")
    or die("SQL Error: " . mysqli_error($mysqli));

?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-barcode mr-2"></i>Asset Inventory Report</h3>
    </div>
    <div class="card-body">
        <form class="mb-4">
            <div class="row">
                <div class="col-sm-4">
                    <div class="form-group">
                        <label>Client</label>
                        <select class="form-control select2" name="client_id">
                            <option value="">- All Clients -</option>
                            <?php 
                            $sql_clients = mysqli_query($mysqli,"SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL ORDER BY client_name ASC");
                            while ($row = mysqli_fetch_array($sql_clients)) {
                                $selected = $row['client_id'] == $client_id ? 'selected' : '';
                                echo "<option value='{$row['client_id']}' $selected>{$row['client_name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        <label>Status</label>
                        <select class="form-control select2" name="status">
                            <option value="">- All Statuses -</option>
                            <?php
                            foreach($asset_status_array as $asset_status_select) {
                                $selected = $asset_status_select == $status ? 'selected' : '';
                                echo "<option $selected>$asset_status_select</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        <label>Type</label>
                        <select class="form-control select2" name="type">
                            <option value="">- All Types -</option>
                            <?php
                            foreach($asset_types_array as $asset_type => $asset_icon) {
                                $selected = $asset_type == $type ? 'selected' : '';
                                echo "<option $selected>$asset_type</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter mr-2"></i>Filter</button>
            <button type="button" class="btn btn-default" onclick="window.location.href='report_asset_inventory.php'"><i class="fas fa-times mr-2"></i>Clear</button>
        </form>

        <table class="table table-striped table-borderless table-hover" id="table_assets_inventory">
            <thead>
                <tr>
                    <th>Asset</th>
                    <th>Client</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Make</th>
                    <th>Model</th>
                    <th>Serial</th>
                    <th>Inventory Barcode</th>
                    <th>Assigned To</th>
                    <th>Location</th>
                    <th>Vendor</th>
                </tr>
            </thead>
            <tbody>
                <?php
                while ($row = mysqli_fetch_array($sql)) {
                    $asset_id = intval($row['asset_id']);
                    $asset_name = nullable_htmlentities($row['asset_name']);
                    $asset_type = nullable_htmlentities($row['asset_type']);
                    $asset_status = nullable_htmlentities($row['asset_status']);
                    $asset_make = nullable_htmlentities($row['asset_make']);
                    $asset_model = nullable_htmlentities($row['asset_model']);
                    $asset_serial = nullable_htmlentities($row['asset_serial']);
                    $asset_inventory_barcode = nullable_htmlentities($row['asset_inventory_barcode']);
                    $client_name = nullable_htmlentities($row['client_name']);
                    $contact_name = nullable_htmlentities($row['contact_name']);
                    $location_name = nullable_htmlentities($row['location_name']);
                    $vendor_name = nullable_htmlentities($row['vendor_name']);
                    $device_icon = getAssetIcon($asset_type);
                ?>
                    <tr>
                        <td><a href="asset_details.php?client_id=<?php echo intval($row['asset_client_id']); ?>&asset_id=<?php echo $asset_id; ?>"><i class="fas fa-fw fa-<?php echo $device_icon; ?> mr-2"></i><?php echo $asset_name; ?></a></td>
                        <td><?php echo $client_name; ?></td>
                        <td><?php echo $asset_type; ?></td>
                        <td><?php echo $asset_status; ?></td>
                        <td><?php echo $asset_make; ?></td>
                        <td><?php echo $asset_model; ?></td>
                        <td><?php echo $asset_serial; ?></td>
                        <td>
                            <?php echo $asset_inventory_barcode; ?>
                            <?php if (!empty($asset_inventory_barcode)): ?>
                                <br>
                                <img id="barcode-img-<?php echo $asset_id; ?>" src="plugins/barcode/barcode.php?s=code128&d=<?php echo urlencode($asset_inventory_barcode); ?>" alt="Barcode" height="40">
                                <br>
                                <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="printBarcode('<?php echo htmlspecialchars($asset_inventory_barcode, ENT_QUOTES); ?>')">
                                    <i class="fas fa-print"></i> Print Inventory Barcode
                                </button>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $contact_name; ?></td>
                        <td><?php echo $location_name; ?></td>
                        <td><?php echo $vendor_name; ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#table_assets_inventory').DataTable({
        "order": [[ 0, "asc" ]],
        "pageLength": 25,
        "stateSave": true,
        "dom": 'Bfrtip',
        "buttons": [
            {
                extend: 'copyHtml5',
                exportOptions: {
                    columns: [ 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10 ]
                }
            },
            {
                extend: 'csvHtml5',
                exportOptions: {
                    columns: [ 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10 ]
                }
            },
            {
                extend: 'excelHtml5',
                exportOptions: {
                    columns: [ 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10 ]
                }
            }
        ]
    });
});
</script>

<script>
function printBarcode(barcodeValue) {
    // Open a new window for printing
    var printWindow = window.open('', '', 'width=400,height=300');
    var barcodeUrl = 'plugins/barcode/barcode.php?s=code128&d=' + encodeURIComponent(barcodeValue);
    printWindow.document.write(`
        <html>
        <head>
            <title>Print Barcode</title>
            <style>
                body { text-align: center; margin: 0; padding: 40px 0; font-family: Arial, sans-serif; }
                .barcode-value { margin-top: 16px; font-size: 18px; letter-spacing: 2px; }
                @media print {
                    button { display: none; }
                    body { margin: 0; }
                }
            </style>
        </head>
        <body>
            <img src="` + barcodeUrl + `" alt="Barcode" style="height:80px; display:block; margin:0 auto;">
            <div class="barcode-value">` + barcodeValue + `</div>
            <button onclick="window.print()">Print</button>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();
}
</script>

<?php require_once "includes/footer.php"; ?>