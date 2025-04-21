<?php

// Enforce a Content Security Policy for security against cross-site scripting
header("Content-Security-Policy: default-src 'self'");

if (!file_exists('config.php')) {
    header("Location: setup.php");
    exit;
}

require_once "config.php";

// Set Timezone
require_once "includes/inc_set_timezone.php";

// Check if the application is configured for HTTPS-only access
if ($config_https_only && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') && (!isset($_SERVER['HTTP_X_FORWARDED_PROTO']) || $_SERVER['HTTP_X_FORWARDED_PROTO'] !== 'https')) {
    echo "Login is restricted as ITFlow defaults to HTTPS-only for enhanced security. To login using HTTP, modify the config.php file by setting config_https_only to false. However, this is strongly discouraged, especially when accessing from potentially unsafe networks like the internet.";
    exit;
}

require_once "functions.php";

require_once "plugins/totp/totp.php";


// IP & User Agent for logging
$session_ip = sanitizeInput(getIP());
$session_user_agent = sanitizeInput($_SERVER['HTTP_USER_AGENT']);

// Block brute force password attacks - check recent failed login attempts for this IP
//  Block access if more than 15 failed login attempts have happened in the last 10 minutes
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(log_id) AS failed_login_count FROM logs WHERE log_ip = '$session_ip' AND log_type = 'Login' AND log_action = 'Failed' AND log_created_at > (NOW() - INTERVAL 10 MINUTE)"));
$failed_login_count = intval($row['failed_login_count']);

if ($failed_login_count >= 15) {

    // Logging
    logAction("Login", "Blocked", "$session_ip was blocked access to login due to IP lockout");

    // Inform user & quit processing page
    header("HTTP/1.1 429 Too Many Requests");
    exit("<h2>$config_app_name</h2>Your IP address has been blocked due to repeated failed login attempts. Please try again later. <br><br>This action has been logged.");
}

// Query Settings for company
$sql_settings = mysqli_query($mysqli, "SELECT * FROM settings LEFT JOIN companies ON settings.company_id = companies.company_id WHERE settings.company_id = 1");
$row = mysqli_fetch_array($sql_settings);

// Company info
$company_name = $row['company_name'];
$company_logo = $row['company_logo'];
$config_start_page = nullable_htmlentities($row['config_start_page']);
$config_login_message = nullable_htmlentities($row['config_login_message']);

// Mail
$config_smtp_host = $row['config_smtp_host'];
$config_smtp_port = intval($row['config_smtp_port']);
$config_smtp_encryption = $row['config_smtp_encryption'];
$config_smtp_username = $row['config_smtp_username'];
$config_smtp_password = $row['config_smtp_password'];
$config_mail_from_email = sanitizeInput($row['config_mail_from_email']);
$config_mail_from_name = sanitizeInput($row['config_mail_from_name']);

// Client Portal Enabled
$config_client_portal_enable = intval($row['config_client_portal_enable']);

// Login key (if setup)
$config_login_key_required = $row['config_login_key_required'];
$config_login_key_secret = $row['config_login_key_secret'];

$config_login_remember_me_expire = intval($row['config_login_remember_me_expire']);

// Login key verification
//  If no/incorrect 'key' is supplied, send to client portal instead
if ($config_login_key_required) {
    if (!isset($_GET['key']) || $_GET['key'] !== $config_login_key_secret) {
        header("Location: client");
        exit();
    }
}

// HTTP-Only cookies
ini_set("session.cookie_httponly", true);

// Tell client to only send cookie(s) over HTTPS
if ($config_https_only || !isset($config_https_only)) {
    ini_set("session.cookie_secure", true);
}

// Handle POST login request
if (isset($_POST['login'])) {

    // Sessions should start after the user has POSTed data
    session_start();

    // Passed login brute force check
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];

    $current_code = 0; // Default value
    if (isset($_POST['current_code'])) {
        $current_code = intval($_POST['current_code']);
    }

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM users LEFT JOIN user_settings on users.user_id = user_settings.user_id WHERE user_email = '$email' AND user_archived_at IS NULL AND user_status = 1 AND user_type = 1"));

    // Check password
    if ($row && password_verify($password, $row['user_password'])) {

        // User password correct (partial login)

        // Set temporary user variables
        $user_name = sanitizeInput($row['user_name']);
        $user_id = intval($row['user_id']);
        $session_user_id = $user_id; // to pass the user_id to logAction function
        $user_email = sanitizeInput($row['user_email']);
        $token = sanitizeInput($row['user_token']);
        $force_mfa = intval($row['user_config_force_mfa']);
        $user_role_id = intval($row['user_role_id']);
        $user_encryption_ciphertext = $row['user_specific_encryption_ciphertext'];
        $user_extension_key = $row['user_extension_key'];

        $mfa_is_complete = false; // Default to requiring MFA
        $extended_log = ''; // Default value

        if (empty($token)) {
            // MFA is not configured
            $mfa_is_complete = true;
        }

        // Validate MFA via a remember-me cookie
        if (isset($_COOKIE['rememberme'])) {
            // Get remember tokens less than $config_login_remember_me_days_expire days old
            $remember_tokens = mysqli_query($mysqli, "SELECT remember_token_token FROM remember_tokens WHERE remember_token_user_id = $user_id AND remember_token_created_at > (NOW() - INTERVAL $config_login_remember_me_expire DAY)");
            while ($row = mysqli_fetch_assoc($remember_tokens)) {
                if (hash_equals($row['remember_token_token'], $_COOKIE['rememberme'])) {
                    $mfa_is_complete = true;
                    $extended_log = 'with 2FA remember-me cookie';
                    break;
                }
            }
        }

        // Validate MFA code
        if (!empty($current_code) && TokenAuth6238::verify($token, $current_code)) {
            $mfa_is_complete = true;
            $extended_log = 'with MFA';
        }

        if ($mfa_is_complete) {
            // MFA Completed successfully

            // FULL LOGIN SUCCESS

            // Create a remember me token, if requested
            if (isset($_POST['remember_me'])) {
                // TODO: Record the UA and IP a token is generated from so that can be shown later on
                $newRememberToken = bin2hex(random_bytes(64));
                setcookie('rememberme', $newRememberToken, time() + 86400*$config_login_remember_me_expire, "/", null, true, true);
                mysqli_query($mysqli, "INSERT INTO remember_tokens SET remember_token_user_id = $user_id, remember_token_token = '$newRememberToken'");

                $extended_log .= ", generated a new remember-me token";
            }

            // Check this login isn't suspicious
            $sql_ip_prev_logins = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(log_id) AS ip_previous_logins FROM logs WHERE log_type = 'Login' AND log_action = 'Success' AND log_ip = '$session_ip' AND log_user_id = $user_id"));
            $ip_previous_logins = sanitizeInput($sql_ip_prev_logins['ip_previous_logins']);

            $sql_ua_prev_logins = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(log_id) AS ua_previous_logins FROM logs WHERE log_type = 'Login' AND log_action = 'Success' AND log_user_agent = '$session_user_agent' AND log_user_id = $user_id"));
            $ua_prev_logins = sanitizeInput($sql_ua_prev_logins['ua_previous_logins']);

            // Notify if both the user agent and IP are different
            if (!empty($config_smtp_host) && $ip_previous_logins == 0 && $ua_prev_logins == 0) {
                $subject = "$config_app_name new login for $user_name";
                $body = "Hi $user_name, <br><br>A recent successful login to your $config_app_name account was considered a little unusual. If this was you, you can safely ignore this email!<br><br>IP Address: $session_ip<br> User Agent: $session_user_agent <br><br>If you did not perform this login, your credentials may be compromised. <br><br>Thanks, <br>ITFlow";

                $data = [
                    [
                        'from' => $config_mail_from_email,
                        'from_name' => $config_mail_from_name,
                        'recipient' => $user_email,
                        'recipient_name' => $user_name,
                        'subject' => $subject,
                        'body' => $body
                    ]
                ];
                addToMailQueue($data);
            }

            // Logging
            logAction("Login", "Success", "$user_name successfully logged in $extended_log", 0, $user_id);

            // Session info
            $_SESSION['user_id'] = $user_id;
            $_SESSION['csrf_token'] = randomString(156);
            $_SESSION['logged'] = true;

            // Forcing MFA
            if ($force_mfa == 1 && $token == NULL) {
                $config_start_page = "mfa_enforcement.php";
            }

            // Setup encryption session key
            if (isset($user_encryption_ciphertext)) {
                $site_encryption_master_key = decryptUserSpecificKey($user_encryption_ciphertext, $password);
                generateUserSessionKey($site_encryption_master_key);

                // Setup extension - currently unused
                //if (is_null($user_extension_key)) {
                    // Extension cookie
                    // Note: Browsers don't accept cookies with SameSite None if they are not HTTPS.
                    //setcookie("user_extension_key", "$user_extension_key", ['path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'None']);

                    // Set PHP session in DB, so we can access the session encryption data (above)
                    //$user_php_session = session_id();
                    //mysqli_query($mysqli, "UPDATE users SET user_php_session = '$user_php_session' WHERE user_id = $user_id");
                //}

            }
            if (isset($_GET['last_visited'])) {
                header("Location: ".$_SERVER["REQUEST_SCHEME"] . "://" . $config_base_url . base64_decode($_GET['last_visited']) );
            } else {
                header("Location: $config_start_page");
            }
        } else {

            // MFA is configured and needs to be confirmed, or was unsuccessful

            // HTML code for the token input field
            $token_field = "
                    <div class='input-group mb-3'>
                        <input type='text' inputmode='numeric' pattern='[0-9]*' maxlength='6' class='form-control' placeholder='Enter your 2FA code' name='current_code' required autofocus>
                        <div class='input-group-append'>
                          <div class='input-group-text'>
                            <span class='fas fa-key'></span>
                          </div>
                        </div>
                      </div>";

            // Log/notify if MFA was unsuccessful
            if ($current_code !== 0) {

                // Logging
                logAction("Login", "MFA Failed", "$user_name failed MFA", 0, $user_id);

                // Email the tech to advise their credentials may be compromised
                if (!empty($config_smtp_host)) {
                    $subject = "Important: $config_app_name failed 2FA login attempt for $user_name";
                    $body = "Hi $user_name, <br><br>A recent login to your $config_app_name account was unsuccessful due to an incorrect 2FA code. If you did not attempt this login, your credentials may be compromised. <br><br>Thanks, <br>ITFlow";
                    $data = [
                        [
                            'from' => $config_mail_from_email,
                            'from_name' => $config_mail_from_name,
                            'recipient' => $user_email,
                            'recipient_name' => $user_name,
                            'subject' => $subject,
                            'body' => $body
                        ]
                    ];
                    $mail = addToMailQueue($data);
                }

                // HTML feedback for incorrect 2FA code
                $response = "
                      <div class='alert alert-warning'>
                        Please Enter 2FA Code!
                        <button class='close' data-dismiss='alert'>&times;</button>
                      </div>";
            }
        }

    } else {

        // Password incorrect or user doesn't exist - show generic error

        header("HTTP/1.1 401 Unauthorized");

        // Logging
        logAction("Login", "Failed", "Failed login attempt using $email");

        $response = "
              <div class='alert alert-danger'>
                Incorrect username or password.
                <button class='close' data-dismiss='alert'>&times;</button>
              </div>";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo nullable_htmlentities($company_name); ?> | Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">

    <!-- Favicon -->
    <?php if(file_exists('uploads/favicon.ico')) { ?>
        <link rel="icon" type="image/x-icon" href="/uploads/favicon.ico">
    <?php } ?>

    <!-- Theme style -->
    <link rel="stylesheet" href="plugins/adminlte/css/adminlte.min.css">
    
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f5f5f5;
        }
        
        .login-container {
            width: 90%;
            max-width: 1000px;
            height: 600px;
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 10px 50px rgba(0, 0, 0, 0.1);
            display: flex;
        }
        
        .login-content {
            width: 100%;
            height: 100%;
            display: flex;
        }
        
        .login-form {
            width: 50%;
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-image {
            width: 50%;
            background-image: linear-gradient(to right, rgba(0, 0, 0, 0.8), rgba(0, 100, 50, 0.7)), url('https://images.unsplash.com/photo-1517694712202-14dd9538aa97?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80');
            background-size: cover;
            background-position: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            position: relative;
        }
        
        h1 {
            font-size: 32px;
            margin-bottom: 10px;
            color: #333;
        }
        
        p.subtitle {
            color: #666;
            margin-bottom: 40px;
            font-size: 16px;
            line-height: 1.5;
        }
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .form-control {
            width: 100%;
            height: 50px;
            border-radius: 5px;
            border: 1px solid #ddd;
            padding: 10px 15px;
            font-size: 16px;
            transition: all 0.3s;
            box-sizing: border-box;
        }
        
        .form-control:focus {
            border-color: #00c853;
            outline: none;
        }
        
        .input-icon {
            position: absolute;
            right: 15px;
            top: 15px;
            width: 25px;
            height: 25px;
            background-color: #00c853;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .remember-me input {
            margin-right: 10px;
        }
        
        .btn-login {
            width: 100%;
            height: 50px;
            border-radius: 25px;
            border: 2px solid #00c853;
            background-color: transparent;
            color: #00c853;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 20px;
        }
        
        .btn-login:hover {
            background-color: #00c853;
            color: white;
        }
        
        .login-links {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
        }
        
        .login-links a {
            color: #666;
            text-decoration: none;
        }
        
        .login-links a:hover {
            color: #00c853;
        }
        
        .brand-logo {
            font-size: 80px;
            font-weight: 300;
            letter-spacing: 2px;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .brand-logo span {
            display: inline-block;
            border: 4px solid white;
            padding: 10px 20px;
            border-radius: 10px;
        }
        
        .brand-tagline {
            font-size: 24px;
            font-weight: 300;
            text-align: center;
            max-width: 80%;
        }
        
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                height: auto;
            }
            
            .login-content {
                flex-direction: column-reverse;
            }
            
            .login-form, .login-image {
                width: 100%;
                padding: 40px;
            }
            
            .login-image {
                height: 300px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-content">
            <div class="login-form">
                <h1>Welcome!</h1>
                <p class="subtitle">Secure access to your credential & knowledge tracking companion. Login to manage your data.</p>
                
                <?php if (!empty($config_login_message)){ ?>
                <p class="mb-4"><?php echo nl2br($config_login_message); ?></p>
                <?php } ?>

                <?php if (isset($response)) { ?>
                <div class="mb-4"><?php echo $response; ?></div>
                <?php } ?>
                
                <form method="post">
                    <div class="form-group" <?php if (isset($token_field)) { echo "hidden"; } ?>>
                        <input type="text" class="form-control" placeholder="Agent Email" name="email" value="<?php if (isset($token_field)) { echo $email; }?>" required <?php if (!isset($token_field)) { echo "autofocus"; } ?>>
                        <div class="input-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                    </div>
                    
                    <div class="form-group" <?php if (isset($token_field)) { echo "hidden"; } ?>>
                        <input type="password" class="form-control" placeholder="Agent Password" name="password" value="<?php if (isset($token_field)) { echo $password; } ?>" required>
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                    </div>
                    
                    <?php
                    if (isset($token_field)) {
                        echo $token_field;
                    ?>
                    <div class="remember-me">
                        <input type="checkbox" id="remember_me" name="remember_me">
                        <label for="remember_me">Remember me</label>
                    </div>
                    <?php
                    } else {
                    ?>
                    <div class="remember-me">
                        <input type="checkbox" id="remember_me" name="remember_me">
                        <label for="remember_me">Remember me</label>
                    </div>
                    <?php
                    }
                    ?>
                    
                    <button type="submit" class="btn-login" name="login">Login</button>
                    
                    <div class="login-links">
                        <a href="#">New User? Sign Up</a>
                        <a href="#">Forgot Password</a>
                    </div>
                </form>
                
                <?php if($config_client_portal_enable == 1){ ?>
                <div style="text-align: center; margin-top: 30px;">
                    <a href="client" style="color: #00c853; text-decoration: none;">Looking for the Client Portal?</a>
                </div>
                <?php } ?>
            </div>
            
            <div class="login-image">
                <div class="brand-logo">
                    <span>CKTC</span>
                </div>
                <div class="brand-tagline">
                    Your Secure Credential & Knowledge Tracking Companion
                </div>
            </div>
        </div>
    </div>

<!-- jQuery -->
<script src="plugins/jquery/jquery.min.js"></script>

<!-- Bootstrap 4 -->
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- AdminLTE App -->
<script src="plugins/adminlte/js/adminlte.min.js"></script>

<!-- <script src="plugins/Show-Hide-Passwords-Bootstrap-4/bootstrap-show-password.min.js"></script> -->

<!-- Prevents resubmit on refresh or back -->
<script src="js/login_prevent_resubmit.js"></script>

</body>
</html>
