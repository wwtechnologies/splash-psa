<?php
require_once "includes/inc_all_admin.php";

// CSRF token setup
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$max_size = 100 * 1024 * 1024; // 100MB
$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = '<div class="alert alert-danger">Invalid CSRF token.</div>';
    } elseif (
        !isset($_FILES['sql_file']) ||
        $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK
    ) {
        $message = '<div class="alert alert-danger">File upload failed.</div>';
    } else {
        // Validate DB credentials
        $dbhost = trim($_POST['dbhost'] ?? '');
        $dbuser = trim($_POST['dbuser'] ?? '');
        $dbpass = $_POST['dbpass'] ?? '';
        $dbname = trim($_POST['dbname'] ?? '');
        $dbport = trim($_POST['dbport'] ?? '');
        if ($dbhost === '' || $dbuser === '' || $dbname === '') {
            $message = '<div class="alert alert-danger">Host, username, and database name are required.</div>';
        } elseif ($dbport !== '' && !ctype_digit($dbport)) {
            $message = '<div class="alert alert-danger">Port must be numeric.</div>';
        } else {
            $file = $_FILES['sql_file'];
            $filename = $file['name'];
            $filesize = $file['size'];
            $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            // Validate extension and size
            if ($file_ext !== 'sql') {
                $message = '<div class="alert alert-danger">Only .sql files are allowed.</div>';
            } elseif ($filesize > $max_size) {
                $message = '<div class="alert alert-danger">File exceeds 100MB limit.</div>';
            } else {
                // Move to temp directory
                $tmp_path = '/tmp/import_' . bin2hex(random_bytes(8)) . '.sql';
                if (!move_uploaded_file($file['tmp_name'], $tmp_path)) {
                    $message = '<div class="alert alert-danger">Failed to store uploaded file.</div>';
                } else {
                    // Escape shell args
                    $dbhost_esc = escapeshellarg($dbhost);
                    $dbuser_esc = escapeshellarg($dbuser);
                    $dbpass_esc = escapeshellarg($dbpass);
                    $dbname_esc = escapeshellarg($dbname);
                    $tmp_path_esc = escapeshellarg($tmp_path);
                    $port_part = ($dbport !== '') ? (' -P ' . escapeshellarg($dbport)) : '';

                    // Import command
                    $cmd = "mysql -h $dbhost_esc -u $dbuser_esc -p$dbpass $port_part $dbname_esc < $tmp_path_esc 2>&1";
                    // Use proc_open for better error capture
                    $descriptor = [
                        0 => ["pipe", "r"],
                        1 => ["pipe", "w"],
                        2 => ["pipe", "w"]
                    ];
                    $process = proc_open($cmd, $descriptor, $pipes);
                    $output = '';
                    if (is_resource($process)) {
                        fclose($pipes[0]);
                        $output = stream_get_contents($pipes[1]);
                        $error = stream_get_contents($pipes[2]);
                        fclose($pipes[1]);
                        fclose($pipes[2]);
                        $return_value = proc_close($process);

                        if ($return_value === 0) {
                            $message = '<div class="alert alert-success">Database import successful.</div>';
                            $success = true;
                        } else {
                            $message = '<div class="alert alert-danger">Import failed: ' . htmlspecialchars($error ?: $output) . '</div>';
                        }
                    } else {
                        $message = '<div class="alert alert-danger">Failed to execute import command.</div>';
                    }
                    // Delete temp file and clear credentials
                    unlink($tmp_path);
                    unset($dbhost, $dbuser, $dbpass, $dbname, $dbport);
                }
            }
        }
    }
}
?>

<div class="card card-dark mb-3">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-database mr-2"></i>Import MariaDB Backup</h3>
    </div>
    <div class="card-body">
        <?php echo $message; ?>
        <?php if (!$success): ?>
        <form method="POST" enctype="multipart/form-data" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <div class="form-group">
                <label for="dbhost">Database Host:</label>
                <input type="text" name="dbhost" id="dbhost" class="form-control" required placeholder="localhost">
            </div>
            <div class="form-group">
                <label for="dbuser">Database Username:</label>
                <input type="text" name="dbuser" id="dbuser" class="form-control" required placeholder="root">
            </div>
            <div class="form-group">
                <label for="dbpass">Database Password:</label>
                <input type="password" name="dbpass" id="dbpass" class="form-control" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label for="dbname">Database Name:</label>
                <input type="text" name="dbname" id="dbname" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="dbport">Port (optional):</label>
                <input type="text" name="dbport" id="dbport" class="form-control" placeholder="3306">
            </div>
            <div class="form-group">
                <label for="sql_file">Select .sql Backup File (max 100MB):</label>
                <input type="file" name="sql_file" id="sql_file" accept=".sql" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Import Backup</button>
        </form>
        <?php endif; ?>
        <div class="alert alert-warning mt-3">
            <strong>Warning:</strong> Importing a backup will overwrite existing database data. Proceed with caution.
        </div>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>