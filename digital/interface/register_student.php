<?php
require_once '../classes/studentmanager.php';

$manager = new studentmanager();
$student_form_data = [];
$register_errors = [];
$courses = $manager->fetchAllCourses();

// State variables
$showVerifyModal = false;
$verificationSuccess = false;
$verificationError = "";
$emailForVerification = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- HANDLE VERIFICATION ---
    if (isset($_POST['action']) && $_POST['action'] === 'verify') {
        $email = trim($_POST['verify_email']);
        $code = trim($_POST['verify_code']);
        $emailForVerification = $email;

        if (!empty($email) && !empty($code)) {
            // Use the manager function to check time correctly
            $result = $manager->verifyRegistration($email, $code);

            if ($result === true) {
                $verificationSuccess = true;
            } else {
                $verificationError = $result; 
                $showVerifyModal = true;
            }
        } else {
            $verificationError = "Please enter the code.";
            $showVerifyModal = true;
        }
    }
    
    // --- HANDLE REGISTRATION ---
    elseif (isset($_POST['action']) && $_POST['action'] === 'register') {
        $student_form_data = $_POST;
        
        // Validation
        if (empty($student_form_data["studentId"])) $register_errors["studentId"] = "Required";
        if (empty($student_form_data["email"])) $register_errors["email"] = "Required";
        if ($student_form_data["password"] !== $student_form_data["confirmPassword"]) $register_errors["confirmPassword"] = "Passwords mismatch";

        if (empty($register_errors)) {
            $manager->studentId = $student_form_data["studentId"];
            $manager->email = $student_form_data["email"];
            $manager->password = $student_form_data["password"];
            $manager->lastName = $student_form_data["lastName"];
            $manager->firstName = $student_form_data["firstName"];
            $manager->middleName = $student_form_data["middleName"] ?? "";
            $manager->gender = ($student_form_data["gender"] === 'Other') ? $student_form_data["genderSpecify"] : $student_form_data["gender"];
            $manager->birthDate = $student_form_data["birthDate"];
            $manager->courseName = $student_form_data["course"];

            if ($manager->addStudent()) {
                $manager->sendVerificationEmail($manager->studentId, $manager->email, $manager->firstName);
                $showVerifyModal = true;
                $emailForVerification = $manager->email;
                $student_form_data = []; 
            } else {
                $register_errors["general"] = "Registration failed. ID or Email may exist.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f2f2f2; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .container { background-color: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 100%; max-width: 450px; text-align: center; position: relative; overflow: hidden; }
        
        /* Progress Bar */
        .progress-container { display: flex; justify-content: space-between; margin-bottom: 30px; position: relative; }
        .progress-bg { position: absolute; top: 50%; left: 0; width: 100%; height: 2px; background: #eee; z-index: 1; transform: translateY(-50%); }
        .progress-bar { position: absolute; top: 50%; left: 0; width: 0%; height: 2px; background: #d9534f; z-index: 2; transform: translateY(-50%); transition: width 0.3s; }
        .step-dot { width: 10px; height: 10px; background: #ccc; border-radius: 50%; z-index: 3; position: relative; transition: 0.3s; }
        .step-dot.active { background: #d9534f; transform: scale(1.3); }

        h2 { color: #d9534f; margin-top: 0; }
        .logo { width: 80px; margin-bottom: 10px; }

        /* Form Steps */
        .step { display: none; animation: fadeIn 0.4s; }
        .step.active { display: block; }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateX(10px); } to { opacity: 1; transform: translateX(0); } }

        .form-group { text-align: left; margin-bottom: 15px; position: relative; }
        .form-group label { display: block; font-size: 0.9em; color: #555; margin-bottom: 5px; font-weight: 600; }
        .form-group input, .form-group select { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-size: 1em; transition: 0.3s; }
        .form-group input:focus { border-color: #d9534f; outline: none; }
        
        /* Navigation Buttons */
        .nav-buttons { display: flex; justify-content: space-between; margin-top: 25px; align-items: center; }
        .btn-arrow { background: #d9534f; color: white; border: none; width: 50px; height: 50px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(217, 83, 79, 0.3); transition: 0.3s; }
        .btn-arrow:hover { background: #c9302c; transform: scale(1.05); }
        .btn-arrow:disabled { background: #ccc; cursor: not-allowed; box-shadow: none; }
        .btn-back { background: #f0f0f0; color: #555; box-shadow: none; }
        .btn-back:hover { background: #e0e0e0; }

        /* Submit Button */
        .btn-submit { background: #d9534f; color: white; width: 100%; padding: 14px; border: none; border-radius: 6px; font-size: 1.1em; cursor: pointer; margin-top: 10px; }

        .error-msg { color: #d9534f; font-size: 0.8em; display: none; text-align: left; margin-top: 5px; }
        
        /* Modals */
        .modal { display: none; position: fixed; z-index: 999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 30px; border-radius: 8px; width: 90%; max-width: 400px; text-align: center; border-top: 5px solid #d9534f; }
        
        .toggle-pw { position: absolute; right: 12px; top: 40px; color: #888; cursor: pointer; }
    </style>
</head>
<body>

<div class="container">
    <img src="../images/logowmsu.jpg" alt="Logo" class="logo">
    
    <?php if ($verificationSuccess): ?>
        <h2>Success!</h2>
        <p>Your account is verified.</p>
        <a href="login.php" class="btn-submit" style="display:block; text-decoration:none; line-height: 1.5;">Go to Login</a>
    <?php else: ?>

        <div class="progress-container">
            <div class="progress-bg"></div>
            <div class="progress-bar" id="progressBar"></div>
            <div class="step-dot active"></div>
            <div class="step-dot"></div>
            <div class="step-dot"></div>
        </div>

        <form method="POST" id="regForm" onsubmit="return validateForm()">
            <input type="hidden" name="action" value="register">

            <div class="step active" id="step1">
                <h2>Create Account</h2>
                <div class="form-group">
                    <label>Student ID</label>
                    <input type="text" name="studentId" id="studentId" required placeholder="e.g. 2024001">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="email" required placeholder="email@gmail.com">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" id="password" required placeholder="Minimum of 8 chararacters">
                    <i class="material-icons toggle-pw" onclick="togglePw('password', this)">visibility_off</i>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirmPassword" id="confirmPassword" required placeholder="Re-enter Password">
                    <i class="material-icons toggle-pw" onclick="togglePw('confirmPassword', this)">visibility_off</i>
                </div>
                <p class="error-msg" id="error1"></p>
                
                <div class="nav-buttons" style="justify-content: flex-end;">
                    <button type="button" class="btn-arrow" onclick="nextStep(1)"><i class="material-icons">arrow_forward</i></button>
                </div>
                <p style="margin-top:15px; font-size:0.9em;">Already registered? <a href="login.php" style="color:#d9534f;">Login</a></p>
            </div>

            <div class="step" id="step2">
                <h2>Personal Details</h2>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="lastName" id="lastName" required>
                </div>
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="firstName" id="firstName" required>
                </div>
                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender" id="gender" onchange="toggleGender()" required>
                        <option value="">Select</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group" id="genderSpecifyBox" style="display:none;">
                    <input type="text" name="genderSpecify" id="genderSpecify" placeholder="Please specify">
                </div>
                <div class="form-group">
                    <label>Birth Date</label>
                    <input type="date" name="birthDate" id="birthDate" required>
                </div>
                <p class="error-msg" id="error2"></p>

                <div class="nav-buttons">
                    <button type="button" class="btn-arrow btn-back" onclick="prevStep(2)"><i class="material-icons">arrow_back</i></button>
                    <button type="button" class="btn-arrow" onclick="nextStep(2)"><i class="material-icons">arrow_forward</i></button>
                </div>
            </div>

            <div class="step" id="step3">
                <h2>Academic Info</h2>
                <div class="form-group">
                    <label>Course</label>
                    <select name="course" id="course" required>
                        <option value="">Select Course</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?= htmlspecialchars($c['course_name']) ?>"><?= htmlspecialchars($c['course_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="text-align: left; margin: 20px 0;">
                    <input type="checkbox" name="terms" id="terms" required>
                    <label for="terms" style="display:inline; font-weight:normal;">I agree to the <span onclick="document.getElementById('termsModal').style.display='flex'" style="color:#d9534f; cursor:pointer; text-decoration:underline;">Terms & Conditions</span></label>
                </div>
                <p class="error-msg" id="error3"></p>

                <button type="submit" class="btn-submit">Complete Registration</button>
                
                <div class="nav-buttons" style="justify-content: flex-start; margin-top: 10px;">
                    <button type="button" class="btn-arrow btn-back" onclick="prevStep(3)"><i class="material-icons">arrow_back</i></button>
                </div>
            </div>

        </form>
    <?php endif; ?>
</div>

<div id="verifyModal" class="modal" style="display: <?= $showVerifyModal ? 'flex' : 'none' ?>;">
    <div class="modal-content">
        <h3 style="color:#d9534f;">Verify Your Email</h3>
        <p>Enter the 6-digit code sent to <strong><?= htmlspecialchars($emailForVerification) ?></strong></p>
        <?php if($verificationError): ?><p style="color:red; font-size:0.9em;"><?= $verificationError ?></p><?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="action" value="verify">
            <input type="hidden" name="verify_email" value="<?= htmlspecialchars($emailForVerification) ?>">
            <input type="text" name="verify_code" maxlength="6" style="font-size: 2em; letter-spacing: 5px; text-align: center; width: 100%; padding: 10px; border: 1px solid #ddd; margin: 15px 0;" placeholder="000000" required>
            <button type="submit" class="btn-submit">Verify</button>
        </form>
    </div>
</div>

<div id="termsModal" class="modal">
    <div class="modal-content" style="text-align: left;">
        <span onclick="document.getElementById('termsModal').style.display='none'" style="float:right; cursor:pointer; font-size:1.5em;">&times;</span>
        <h3>Terms & Conditions</h3>
        <div style="max-height: 200px; overflow-y: auto;">
            <p>By registering for and using the Digital Class Recitation system ("the System"), you agree to comply with and be bound by these Terms and Conditions. If you do not agree to these terms, please do not use the System.</p>
            <h4>2. Use of the System</h4>
            <p>The System is intended for educational purposes related to class recitation tracking within Western Mindanao State University (WMSU). You agree to use the System only for its intended purpose and in a manner that complies with all applicable laws and WMSU policies.</p>
            <h4>3. Account Responsibility</h4>
            <p>You are responsible for maintaining the confidentiality of your account login information (Student ID and password). You are responsible for all activities that occur under your account. Notify administrators immediately of any unauthorized use.</p>
            <h4>4. Data Privacy</h4>
            <p>Your personal information (name, student ID, course, etc.) and recitation scores will be stored in the System. This data will be used by authorized instructors and administrators for academic purposes only. WMSU is committed to protecting your data in accordance with relevant data privacy laws. Your data will not be shared with third parties without your consent, except as required by law or university policy.</p>
            <h4>5. Prohibited Conduct</h4>
            <p>You agree not to misuse the System. Misuse includes, but is not limited to: attempting unauthorized access, disrupting the system, uploading malicious content, sharing your account, or using the system for non-educational purposes.</p>
            <h4>6. Disclaimer</h4>
            <p>The System is provided "as is". While efforts are made to ensure accuracy and availability, WMSU makes no warranties regarding the system's performance, reliability, or suitability for any specific purpose.</p>
            <h4>7. Changes to Terms</h4>
            <p>WMSU reserves the right to modify these Terms and Conditions at any time. Continued use of the System after changes constitutes acceptance of the new terms.</p>
            <h4>8. Governing Law</h4>
            <p>These terms shall be governed by the laws of the Republic of the Philippines.</p>
            <p><em>By checking the box during registration, you acknowledge that you have read, understood, and agree to these Terms and Conditions.</em></p>
        </div>
    </div>
</div>

<script>
    let currentStep = 1;
    const dots = document.querySelectorAll('.step-dot');
    const bar = document.getElementById('progressBar');

    function updateUI() {
        document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
        document.getElementById('step' + currentStep).classList.add('active');
        dots.forEach((d, index) => { d.classList.toggle('active', index < currentStep); });
        bar.style.width = ((currentStep - 1) * 50) + '%';
    }

    function nextStep(step) {
        const inputs = document.getElementById('step' + step).querySelectorAll('input, select');
        let valid = true;
        
        inputs.forEach(input => {
            if (input.hasAttribute('required') && !input.value) {
                input.style.borderColor = 'red';
                valid = false;
            } else {
                input.style.borderColor = '#ddd';
            }
        });

        if (step === 1) {
            const pw = document.getElementById('password').value;
            const confirm = document.getElementById('confirmPassword').value;
            if (pw.length < 8) { showError(1, "Password must be 8+ chars."); return; }
            if (pw !== confirm) { showError(1, "Passwords do not match."); return; }
        }

        if (valid) {
            document.getElementById('error' + step).style.display = 'none';
            currentStep++;
            updateUI();
        } else {
            showError(step, "Please fill in all fields.");
        }
    }

    function prevStep(step) {
        currentStep--;
        updateUI();
    }
    
    function showError(step, msg) {
        const el = document.getElementById('error' + step);
        el.innerText = msg;
        el.style.display = 'block';
    }

    function toggleGender() {
        const val = document.getElementById('gender').value;
        const box = document.getElementById('genderSpecifyBox');
        const input = document.getElementById('genderSpecify');
        if (val === 'Other') {
            box.style.display = 'block';
            input.setAttribute('required', 'required');
        } else {
            box.style.display = 'none';
            input.removeAttribute('required');
        }
    }
    
    // Updated function to handle icon switching
    function togglePw(id, icon) {
        const el = document.getElementById(id);
        const type = el.type === 'password' ? 'text' : 'password';
        el.type = type;
        // Switches icon text between visibility and visibility_off
        icon.textContent = (type === 'password') ? 'visibility_off' : 'visibility';
    }
</script>

</body>
</html>