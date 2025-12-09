<?php
session_start();

require_once "../classes/database.php"; 

$login_error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input_login = trim($_POST['username'] ?? ''); 
    $input_password = trim($_POST['password'] ?? '');

    if (empty($input_login) || empty($input_password)) {
        $login_error = "Email/Student ID and password are required.";
    } else {
        try {
            $database = new Database();
            $conn = $database->connect();

            $stmt = $conn->prepare("
                SELECT u.user_id, u.username, u.password_hash, u.role, u.student_id, u.is_verified 
                FROM users u 
                LEFT JOIN students s ON u.student_id = s.student_id 
                WHERE (u.username = :login_identifier OR s.email = :login_identifier)
                LIMIT 1
            ");
            
            $stmt->bindParam(':login_identifier', $input_login);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($input_password, $user['password_hash'])) {
                
                if ($user['role'] === 'student' && $user['is_verified'] == 0) {
                    $login_error = "Your account is not verified. Please check your email for a verification code.";
                } else {
                    session_regenerate_id(true);

                    $_SESSION['loggedin'] = true;
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['student_id'] = $user['student_id'];

                    if ($_SESSION['user_role'] === 'admin') {
                        header("Location: dashboard.php");
                        exit;
                    } else {
                        header("Location: student_dashboard.php");
                        exit;
                    }
                } 
            } else {
                $login_error = "Invalid credentials.";
            }
        } catch (PDOException $e) {
            error_log("Login Error: " . $e->getMessage());
            $login_error = "An error occurred. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | Digital Class</title>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f2f2f2;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        margin: 0;
        padding: 20px;
        box-sizing: border-box;
    }
    .login-container {
        background-color: #fff;
        padding: 40px;
        border-radius: 12px; /* Matches registration rounded corners */
        box-shadow: 0 10px 25px rgba(0,0,0,0.1); /* Matches registration shadow */
        width: 100%;
        max-width: 400px;
        text-align: center;
        box-sizing: border-box;
        border-top: 5px solid #d9534f; /* Signature Red Top Border */
        position: relative;
    }
    .login-container img {
        width: 120px;
        height: auto;
        margin-bottom: 15px;
    }
    h2 {
        color: #d9534f;
        margin-top: 0;
        margin-bottom: 25px;
        font-weight: 600;
    }
    .form-group {
         margin-bottom: 20px;
         text-align: left;
         position: relative;
    }
    label {
        display: block;
        margin-bottom: 8px;
        color: #555;
        font-weight: 600;
        font-size: 0.95em;
    }
    input[type="text"], input[type="password"] {
        width: 100%;
        padding: 12px;
        padding-right: 40px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 1em;
        color: #333;
        box-sizing: border-box;
        transition: border-color 0.3s ease;
    }
     input:focus {
         border-color: #d9534f;
         outline: none;
         box-shadow: 0 0 0 3px rgba(217, 83, 79, 0.1);
     }
    .toggle-password {
        position: absolute;
        right: 12px;
        top: 40px; /* Aligned with input text */
        cursor: pointer;
        color: #999;
        user-select: none;
        font-size: 20px;
    }
    .toggle-password:hover {
        color: #555;
    }
    button {
        background-color: #d9534f;
        color: white;
        padding: 12px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 1.1em;
        font-weight: 600;
        width: 100%;
        transition: background-color 0.3s ease, transform 0.1s;
        margin-top: 10px;
        letter-spacing: 0.5px;
    }
    button:hover {
        background-color: #c9302c;
    }
    button:active {
        transform: scale(0.98);
    }
    .error-message {
        color: #d9534f;
        background-color: #fdecea;
        border: 1px solid #fadbd8;
        padding: 10px;
        border-radius: 6px;
        margin-bottom: 20px;
        font-size: 0.9em;
        text-align: center;
    }
    .forgot-wrapper {
        text-align: right;
        margin-top: -10px;
        margin-bottom: 20px;
    }
    .forgot-link {
        font-size: 0.9em;
        color: #666;
        text-decoration: none;
        transition: color 0.2s;
    }
    .forgot-link:hover {
        color: #d9534f;
        text-decoration: underline;
    }
    .register-link {
        margin-top: 25px;
        font-size: 0.95em;
        color: #555;
    }
    .register-link a {
        color: #d9534f;
        text-decoration: none;
        font-weight: 700;
    }
    .register-link a:hover {
        text-decoration: underline;
    }
</style>
</head>
<body>

<div class="login-container">
    <img src="../images/logowmsu.jpg" alt="WMSU Logo">
    <h2>Login</h2>

    <?php if (!empty($login_error)): ?>
        <div class="error-message"><?php echo htmlspecialchars($login_error); ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <div class="form-group">
            <label for="username">Student ID / Email </label>
            <input type="text" id="username" name="username" placeholder="e.g. 2024001 / email@gmail.com" required>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Enter your password" required>
            <span class="material-icons toggle-password" id="togglePassword">visibility_off</span>
        </div>
        
        <div class="forgot-wrapper">
            <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
        </div>

        <button type="submit">Sign In</button>
    </form>

    <div class="register-link">
        Don't have an account? <a href="register_student.php">Register here</a>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const passwordInput = document.getElementById('password');
        const togglePasswordButton = document.getElementById('togglePassword');

        if (togglePasswordButton && passwordInput) {
            togglePasswordButton.addEventListener('click', function () {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.textContent = type === 'password' ? 'visibility_off' : 'visibility';
            });
        }
    });
</script>

</body>
</html>