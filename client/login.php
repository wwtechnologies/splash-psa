<?php
/*
 * Client Portal
 * Landing / Home page for the client portal
 */

header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'");

require_once '../config.php';
require_once '../functions.php';
require_once '../includes/get_settings.php';

if (!isset($_SESSION)) {
    // HTTP Only cookies
    ini_set("session.cookie_httponly", true);
    if ($config_https_only) {
        // Tell client to only send cookie(s) over HTTPS
        ini_set("session.cookie_secure", true);
    }
    session_start();
}

// Set Timezone after session_start
require_once "../includes/inc_set_timezone.php";

// Check to see if client portal is enabled
if($config_client_portal_enable == 0) {
    echo "Client Portal is Disabled";
    exit();
}

$session_ip = sanitizeInput(getIP());
$session_user_agent = sanitizeInput($_SERVER['HTTP_USER_AGENT']);

$sql_settings = mysqli_query($mysqli, "SELECT config_azure_client_id, config_login_message FROM settings WHERE company_id = 1");
$settings = mysqli_fetch_array($sql_settings);
$azure_client_id = $settings['config_azure_client_id'];
$config_login_message = nullable_htmlentities($settings['config_login_message']);

$company_sql = mysqli_query($mysqli, "SELECT company_name, company_logo FROM companies WHERE company_id = 1");
$company_results = mysqli_fetch_array($company_sql);
$company_name = $company_results['company_name'];
$company_logo = $company_results['company_logo'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {

    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        header("HTTP/1.1 401 Unauthorized");

        $_SESSION['login_message'] = 'Invalid e-mail';

    } else {

        $sql = mysqli_query($mysqli, "SELECT * FROM users LEFT JOIN contacts ON user_id = contact_user_id WHERE user_email = '$email' AND user_archived_at IS NULL AND user_type = 2 AND user_status = 1 LIMIT 1");
        $row = mysqli_fetch_array($sql);
        $client_id = intval($row['contact_client_id']);
        $user_id = intval($row['user_id']);
        $session_user_id = $user_id; // to pass the user_id to logAction function
        $contact_id = intval($row['contact_id']);
        $user_email = sanitizeInput($row['user_email']);
        $user_auth_method = sanitizeInput($row['user_auth_method']);

        if ($user_auth_method == 'local') {
            if (password_verify($password, $row['user_password'])) {

                $_SESSION['client_logged_in'] = true;
                $_SESSION['client_id'] = $client_id;
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_type'] = 2;
                $_SESSION['contact_id'] = $contact_id;
                $_SESSION['login_method'] = "local";

                header("Location: index.php");

                // Logging
                logAction("Client Login", "Success", "Client contact $user_email successfully logged in locally", $client_id, $user_id);

            } else {

                // Logging
                logAction("Client Login", "Failed", "Failed client portal login attempt using $email (incorrect password for contact ID $contact_id)", $client_id, $user_id);

                header("HTTP/1.1 401 Unauthorized");
                $_SESSION['login_message'] = 'Incorrect username or password.';

            }

        } else {

            // Logging
            logAction("Client Login", "Failed", "Failed client portal login attempt using $email (invalid email/not allowed local auth)");

            header("HTTP/1.1 401 Unauthorized");

            $_SESSION['login_message'] = 'Incorrect username or password.';

        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo $company_name; ?> | Client Portal Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">

    <!-- Favicon -->
    <?php if(file_exists('../uploads/favicon.ico')) { ?>
        <link rel="icon" type="image/x-icon" href="../uploads/favicon.ico">
    <?php } ?>

    <!-- Theme style -->
    <link rel="stylesheet" href="../plugins/adminlte/css/adminlte.min.css">

    <!-- Custom Login CSS -->
    <link rel="stylesheet" href="../css/login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-content">
            <div class="login-form">
                <h1>Welcome</h1>
                <p class="subtitle">Secure access to your IT documentation and asset management portal. Login to manage your infrastructure.</p>
                
                <?php if (!empty($config_login_message)){ ?>
                <p class="mb-4"><?php echo nl2br($config_login_message); ?></p>
                <?php } ?>

                <?php if (!empty($_SESSION['login_message'])) { ?>
                <div class="mb-4">
                    <div class="alert alert-danger">
                        <?php
                        echo $_SESSION['login_message'];
                        unset($_SESSION['login_message']);
                        ?>
                        <button class="close" data-dismiss="alert">&times;</button>
                    </div>
                </div>
                <?php } ?>

                <form method="post">
                    <div class="form-group">
                        <input type="text" class="form-control" placeholder="Registered Client Email" name="email" required autofocus>
                        <div class="input-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <input type="password" class="form-control" placeholder="Client Password" name="password" required>
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                    </div>
                    
                    <div class="remember-me">
                        <input type="checkbox" id="remember_me" name="remember_me">
                        <label for="remember_me">Remember me</label>
                    </div>
                    
                    <button type="submit" class="btn-login" name="login">Login</button>
                    
                    <div class="login-links">
                        <a href="#">Request Access</a>
                        <?php if (!empty($config_smtp_host)) { ?>
                        <a href="login_reset.php">Forgot Password?</a>
                        <?php } ?>
                    </div>
                </form>
                
                <?php if (!empty($azure_client_id)) { ?>
                <hr>
                <div class="col text-center">
                    <a href="login_microsoft.php">
                        <button type="button" class="btn btn-secondary">Login with Microsoft Entra</button>
                    </a>
                </div>
                <?php } ?>
            </div>
            
            <div class="login-image">
                <div class="brand-logo">
                    <?php if (!empty($company_logo)) { ?>
                        <img src="<?php echo "../uploads/settings/$company_logo"; ?>" alt="<?php echo htmlspecialchars($company_name); ?> Logo" class="logo-img">
                    <?php } else { ?>
                        <span><?php echo htmlspecialchars($company_name); ?></span>
                    <?php } ?>
                </div>
                <div class="brand-tagline">
                    Comprehensive IT Documentation & Asset Management Portal
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="../plugins/jquery/jquery.min.js"></script>

    <!-- Bootstrap 4 -->
    <script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- AdminLTE App -->
    <script src="../plugins/adminlte/js/adminlte.min.js"></script>

    <!-- Prevents resubmit on refresh or back -->
    <script src="../js/login_prevent_resubmit.js"></script>
</body>
</html>
