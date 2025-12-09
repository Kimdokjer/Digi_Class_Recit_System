<?php
session_start();
require_once "../classes/studentmanager.php";

$step = 1; // Default step
$error = "";
$success = "";
$email = $_SESSION['reset_email'] ?? ""; // Persist email across steps

$manager = new studentmanager();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- STEP 1: SEND CODE ---
    if (isset($_POST['action']) && $_POST['action'] === 'send_code') {
        $email = trim($_POST['email']);
        $result = $manager->sendPasswordResetEmail($email);
        
        if ($result === true) {
            $_SESSION['reset_email'] = $email; // Save for next steps
            $step = 2; // Move to Code verification
        } else {
            $error = $result;
        }
    }
    
    // --- STEP 2: VERIFY CODE ---
    elseif (isset($_POST['action']) && $_POST['action'] === 'verify_code') {
        $code = trim($_POST['code']);
        $_SESSION['reset_code'] = $code;
        $step = 3; 
    }

    // --- STEP 3: RESET PASSWORD ---
    elseif (isset($_POST['action']) && $_POST['action'] === 'reset_password') {
        $pass = $_POST['password'];
        $confirm = $_POST['confirm_password'];
        $code = $_SESSION['reset_code'] ?? "";
        $email = $_SESSION['reset_email'] ?? "";
        
        if ($pass !== $confirm) {
            $error = "Passwords do not match.";
            $step = 3;
        } elseif (strlen($pass) < 8) {
            $error = "Password must be at least 8 characters.";
            $step = 3;
        } else {
            $result = $manager->resetStudentPassword($email, $code, $pass);
            if ($result === true) {
                $step = 4; // Success Screen
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_code']);
            } else {
                $error = $result;
                $step = 2; // Go back to code entry if code was wrong
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        body { 
            font-family: Arial, sans-serif; 
            background-color: #f2f2f2; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            margin: 0; 
            padding: 20px;
            box-sizing: border-box;
        }
        .container { 
            background-color: #fff; 
            padding: 40px; 
            border-radius: 8px; 
            box-shadow: 0 4px 8px rgba(0,0,0,0.1); 
            width: 100%; 
            max-width: 400px; 
            text-align: center; 
        }
        .container img.logo { 
            width: 150px; 
            height: auto; 
            margin-bottom: 20px; 
        }
        h2 { 
            color: #d9534f; 
            margin-bottom: 10px; 
            margin-top: 0;
        }
        p { 
            color: #666; 
            margin-bottom: 25px; 
            line-height: 1.5;
            font-size: 0.95em;
        }
        .form-group {
            text-align: left;
            margin-bottom: 20px;
        }
        input[type="text"], input[type="email"], input[type="password"] { 
            width: 100%; 
            padding: 12px; 
            border: 1px solid #ccc; 
            border-radius: 4px; 
            font-size: 1em; 
            box-sizing: border-box; 
            transition: border-color 0.3s;
        }
        input:focus {
            border-color: #d9534f;
            outline: none;
        }
        button { 
            width: 100%; 
            padding: 12px; 
            background-color: #d9534f; 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 1.1em; 
            transition: background-color 0.3s; 
        }
        button:hover { 
            background-color: #c9302c; 
        }
        .error { 
            color: #d9534f; 
            background-color: #FFEBEE; 
            padding: 12px; 
            border-radius: 4px; 
            margin-bottom: 20px; 
            text-align: center; 
            font-size: 0.9em; 
            border: 1px solid #ffcdd2;
        }
        .back-link { 
            display: inline-block; 
            margin-top: 25px; 
            color: #777; 
            text-decoration: none; 
            font-size: 0.9em; 
        }
        .back-link:hover { 
            color: #333; 
            text-decoration: underline; 
        }
        .code-input { 
            letter-spacing: 8px; 
            font-size: 1.5em !important; 
            text-align: center; 
            font-weight: bold;
            color: #333;
        }
    </style>
</head>
<body>

<div class="container">
    <img src="../images/logowmsu.jpg" alt="WMSU Logo" class="logo">
    
    <?php if ($step === 1): ?>
        <h2>Forgot Password?</h2>
        <p>Enter your email address to receive a 6-digit reset code.</p>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="action" value="send_code">
            <div class="form-group">
                <input type="email" name="email" placeholder="e.g., email@gmail.com" required>
            </div>
            <button type="submit">Send Code</button>
        </form>

    <?php elseif ($step === 2): ?>
        <h2>Verify Code</h2>
        <p>We sent a 6-digit code to <br><strong><?= htmlspecialchars($email) ?></strong></p>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="verify_code">
            <div class="form-group">
                <input type="text" name="code" class="code-input" maxlength="6" placeholder="000000" pattern="\d{6}" required autocomplete="off">
            </div>
            <button type="submit">Verify Code</button>
        </form>
        <a href="forgot_password.php" class="back-link">Entered wrong email?</a>

    <?php elseif ($step === 3): ?>
        <h2>Reset Password</h2>
        <p>Please create a new password for your account.</p>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <div class="form-group">
                <input type="password" name="password" placeholder="New Password" required minlength="8">
            </div>
            <div class="form-group">
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
            </div>
            <button type="submit">Update Password</button>
        </form>

    <?php elseif ($step === 4): ?>
        <h2 style="color: #d9534f; font-size: 1.8em;">Success!</h2>
        <p>Your password has been successfully reset. You can now log in with your new credentials.</p>
        
        <div style="margin-top: 30px;">
            <a href="login.php" style="text-decoration: none;">
                <button>Go to Login</button>
            </a>
        </div>
    <?php endif; ?>

    <?php if ($step !== 4): ?>
        <br>
        <a href="login.php" class="back-link">Back to Login</a>
    <?php endif; ?>
</div>

</body>
</html>