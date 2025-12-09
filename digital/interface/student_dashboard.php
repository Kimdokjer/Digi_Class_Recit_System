<?php
session_start();

require_once "../classes/studentmanager.php"; // Goes up to DIGITAL, then to classes/

// --- START: NEW NOTIFICATION PHP ---
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$manager = new studentmanager();
$current_student_id = $_SESSION['student_id']; // Use the student_id from the session

try {
    $notif_conn = $manager->connect();
    
    // --- Check if a "mark all read" request is being made ---
    // We also check for 'HTTP_X_REQUESTED_WITH' to ensure it's our JS fetch request
    if (isset($_GET['mark_read']) && $_GET['mark_read'] === 'all' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        $stmt_mark_all = $notif_conn->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE student_id = :student_id AND is_read = 0
        ");
        $stmt_mark_all->execute([':student_id' => $current_student_id]);
        // Send a success response and stop the script
        echo json_encode(['success' => true]);
        exit;
    }
    
    // --- Check if a single notification is being marked as read (from clicking the link) ---
    if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
        $notif_id_to_mark = $_GET['mark_read'];
        $stmt_mark_one = $notif_conn->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE notification_id = :notif_id AND student_id = :student_id
        ");
        $stmt_mark_one->execute([':notif_id' => $notif_id_to_mark, ':student_id' => $current_student_id]);
        
        // --- Redirect to the link's final destination WITHOUT the mark_read parameter ---
        // First, find the original link for this notification
        $stmt_get_link = $notif_conn->prepare("SELECT link FROM notifications WHERE notification_id = ?");
        $stmt_get_link->execute([$notif_id_to_mark]);
        $notif_link = $stmt_get_link->fetchColumn();

        if ($notif_link) {
             header("Location: " . $notif_link); // Go to the subject page
        } else {
             header("Location: student_dashboard.php"); // Fallback
        }
        exit;
    }
    
    // --- Fetch all notifications for the bell ---
    $stmt_notif = $notif_conn->prepare("
        SELECT * FROM notifications 
        WHERE student_id = :studentId 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt_notif->execute([':studentId' => $current_student_id]);
    $notifications = $stmt_notif->fetchAll();

    $unread_count = 0;
    foreach ($notifications as $notif) {
        if ($notif['is_read'] == 0) {
            $unread_count++;
        }
    }
} catch (PDOException $e) {
    error_log("Notification Error: " . $e->getMessage());
    $notifications = [];
    $unread_count = 0;
}
// --- END: NEW NOTIFICATION PHP ---


// --- Original Page Logic Starts Here ---
$studentId = $_SESSION['username']; // This is used for the rest of the page logic
$studentData = $manager->fetchStudent($studentId);

$enrolledSubjects = $manager->getStudentEnrolledSubjects($studentId); 

$view = $_GET['view'] ?? 'dashboard';
$subjectCodeToShow = null;
$recitations = [];
$averageScore = 0;
$subjectName = '';
$maxScore = 10;
$totalPossibleRecitations = 10;

if ($view === 'subject' && isset($_GET['code'])) {
    $subjectCodeToShow = $_GET['code'];
    
    $isEnrolled = false;
    foreach ($enrolledSubjects as $subject) {
        if ($subject['subject_code'] === $subjectCodeToShow) {
            $isEnrolled = true;
            $subjectName = $subject['subject_name'];
            break;
        }
    }

    if ($isEnrolled) {
        $recitations = $manager->getStudentRecitations($studentId, $subjectCodeToShow);
        $averageScore = $manager->getStudentAverageScore($studentId, $subjectCodeToShow);
    } else {
        // Subject code not found or not enrolled, redirect to dashboard
        header("Location: student_dashboard.php");
        exit;
    }
}

header('X-Frame-Options: SAMEORIGIN');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - <?= htmlspecialchars($studentData['firstname'] ?? 'Student') ?></title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; background-color: #f4f7f6; display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background-color: #D32F2F; color: white; padding: 20px 0; display: flex; flex-direction: column; flex-shrink: 0; height: 100vh; position: sticky; top: 0; box-shadow: 2px 0 8px rgba(0,0,0,0.2); }
        .sidebar-header { text-align: center; padding: 10px 15px; margin-bottom: 30px; }
        .sidebar-header img { width: 90px; height: 90px; border-radius: 50%; background-color: white; padding: 5px; object-fit: cover; border: 3px solid #FFCDD2; margin-bottom: 10px; }
        .sidebar-header h2 { color: #FFEBEE; margin: 0; font-size: 1.4em; font-weight: 600; }
        .sidebar-nav ul { list-style: none; padding: 0; margin: 0; }
        .sidebar-nav ul li a { display: flex; align-items: center; padding: 12px 20px; color: white; text-decoration: none; font-size: 1.05em; transition: background-color 0.3s, border-left 0.3s; }
        .sidebar-nav ul li a .material-icons { margin-right: 15px; font-size: 24px; color: white; }
        .sidebar-nav ul li a:hover { background-color: #B71C1C; border-left: 5px solid #FFEBEE; padding-left: 15px; }
        .sidebar-nav ul li a.active { background-color: #B71C1C; border-left: 5px solid white; padding-left: 15px; font-weight: bold; }
        .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; }
        .top-navbar { background-color: #ffffff; padding: 15px 25px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; }
        .top-navbar h1 { margin: 0; color: #333; font-size: 1.9em; font-weight: 600; }
        .user-menu { display: flex; align-items: center; }
        .user-menu .material-icons { font-size: 28px; margin-right: 10px; color: #757575; }
        .user-menu .user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            line-height: 1.2;
        }
        .user-menu .user-info span {
            font-weight: 600; color: #555; font-size: 1.1em;
        }
        .user-menu .user-info small {
            font-size: 0.8em; color: #757575;
        }
        .logout-btn { background-color: #D32F2F; color: white; padding: 8px 15px; border: none; border-radius: 5px; cursor: pointer; font-size: 0.9em; font-weight: bold; margin-left: 15px; text-decoration: none; transition: background-color 0.3s ease; }
        .logout-btn:hover { background-color: #C62828; }
        .main-content { padding: 30px; flex-grow: 1; background-color: #f8f8f8; }
        .content-header { margin-bottom: 25px; border-bottom: 1px solid #dee2e6; padding-bottom: 15px; }
        .content-header h2 { margin: 0; font-size: 1.8em; color: #333; }
        .course-overview h3 { font-size: 1.3em; color: #495057; margin-bottom: 20px; }
        .course-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; }
        .course-card { background-color: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); overflow: hidden; display: flex; flex-direction: column; text-decoration: none; color: inherit; transition: transform 0.2s ease, box-shadow 0.2s ease; border-top: 5px solid #D32F2F; }
        .course-card:hover { transform: translateY(-5px); box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15); }
        .card-image { height: 120px; background-color: #FFEBEE; display: flex; align-items: center; justify-content: center; color: #D32F2F; font-size: 1.8em; font-weight: bold; padding: 10px; text-align: center; }
        .card-content { padding: 20px; flex-grow: 1; background-color: #fff; }
        .card-content h4 { margin-top: 0; margin-bottom: 10px; font-size: 1.15em; color: #333; }
        .card-content p { font-size: 0.9em; color: #666; margin-bottom: 15px; }
        .progress-bar-container { background-color: #e9ecef; border-radius: 5px; height: 8px; overflow: hidden; margin-bottom: 5px; }
        .progress-bar { background-color: #D32F2F; height: 100%; width: 0%; border-radius: 5px; transition: width 0.5s ease; }
        .completion-text { font-size: 0.8em; color: #6c757d; text-align: right; }
        .recitation-details h3 { font-size: 1.5em; color: #333; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 25px; border-radius: 8px; overflow: hidden; box-shadow: 0 3px 10px rgba(0,0,0,0.1); }
        th, td { border: 1px solid #e0e0e0; padding: 12px 15px; text-align: left; font-size: 0.95em; }
        th { background-color: #D32F2F; color: white; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
        tr:nth-child(even) { background-color: #fcfcfc; }
        td { color: #333; }
        .no-records { text-align: center; margin-top: 20px; color: #757575; font-style: italic; padding: 20px; background-color: #fff; border-radius: 8px; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #D32F2F; text-decoration: none; font-weight: 500; }
        .back-link:hover { text-decoration: underline; }
        .back-link .material-icons { vertical-align: middle; font-size: 18px; margin-right: 5px; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .info-box { text-align: center; border: 1px solid #FFCDD2; padding: 15px; border-radius: 8px; background-color: #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .info-box p { font-size: 1.1em; margin: 0 0 5px 0; color: #555; }
        .info-box span { font-size: 2em; color: #D32F2F; font-weight: bold;}
        
        /* --- START: NOTIFICATION STYLES --- */
        .notification-bell {
            position: relative;
            cursor: pointer;
            margin-right: 20px;
        }
        .notification-bell .material-icons {
            font-size: 28px;
            color: #757575;
            transition: color 0.2s ease;
        }
        .notification-bell:hover .material-icons {
            color: #333;
        }
        .notification-bell .badge {
            position: absolute;
            top: -5px;
            right: -8px;
            background-color: #D32F2F;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 11px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: bold;
            border: 2px solid #ffffff;
        }
        .notification-dropdown {
            display: none;
            position: absolute;
            top: 50px; /* Adjust as needed based on your navbar height */
            right: 0;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            width: 340px;
            max-height: 400px;
            overflow-y: auto;
            z-index: 1001;
            border: 1px solid #eee;
        }
        .notification-dropdown-header {
            padding: 12px 15px;
            font-weight: bold;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background-color: #fff;
        }
        .notification-dropdown-header .mark-all-read {
            font-size: 0.8em;
            font-weight: normal;
            color: #D32F2F;
            text-decoration: none;
            cursor: pointer;
        }
        .notification-dropdown-header .mark-all-read:hover {
            text-decoration: underline;
        }
        .notification-item {
            display: block;
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            text-decoration: none;
            color: #333;
        }
        .notification-item:last-child { border-bottom: none; }
        .notification-item:hover { background-color: #f9f9f9; }
        .notification-item.unread { background-color: #FFEBEE; }
        .notification-item.unread:hover { background-color: #FFCDD2; }
        .notification-item p {
            margin: 0;
            font-size: 0.9em;
            line-height: 1.4;
        }
        .notification-item small {
            font-size: 0.75em;
            color: #777;
            margin-top: 4px;
            display: block;
        }
        .no-notifications {
            text-align: center;
            padding: 20px;
            color: #888;
            font-size: 0.9em;
        }
        /* --- END: NOTIFICATION STYLES --- */
        
        @media (max-width: 768px) {
             body { flex-direction: column; }
             .sidebar { width: 100%; height: auto; position: static; flex-direction: row; justify-content: space-around; padding: 5px 0; box-shadow: 0 2px 5px rgba(0,0,0,0.1); order: 2; }
             .sidebar-header { display: none; }
             .sidebar-nav { width: 100%; }
             .sidebar-nav ul { display: flex; justify-content: space-around; flex-wrap: wrap; }
             .sidebar-nav ul li { margin-bottom: 0; flex-basis: auto; text-align: center; }
             .sidebar-nav ul li a { padding: 8px 5px; font-size: 0.8em; flex-direction: column; align-items: center; border-left: none !important; }
              .sidebar-nav ul li a:hover, .sidebar-nav ul li a.active { background-color: #B71C1C; padding-left: 5px; }
             .sidebar-nav ul li a .material-icons { margin-right: 0; margin-bottom: 3px; font-size: 20px; }
             .top-navbar { padding: 10px 15px; order: 1; }
             .top-navbar h1 { font-size: 1.3em; }
             .main-content { padding: 20px; }
             .content-header h2 { font-size: 1.5em; }
             .info-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
             <img src="../images/logowmsu.jpg" alt="WMSU Logo">
             <h2>Digital Class Recitation</h2>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="student_dashboard.php" class="<?= ($view === 'dashboard' ? 'active' : '') ?>"><span class="material-icons">dashboard</span>Dashboard</a></li>
                <li><a href="student_dashboard.php" class="<?= ($view !== 'dashboard' ? 'active' : '') ?>"><span class="material-icons">school</span>My Courses</a></li>
            </ul>
        </nav>
    </div>

    <div class="main-wrapper">
        <nav class="top-navbar">
            <h1>Student Dashboard</h1>
            <div class="user-menu">
            
                <div class="notification-bell" id="notificationBell">
                    <span class="material-icons">
                        <?= ($unread_count > 0) ? 'notifications_active' : 'notifications' ?>
                    </span>
                    <?php if ($unread_count > 0): ?>
                        <span class="badge" id="notificationBadge"><?= $unread_count ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-dropdown-header">
                        Notifications
                        <?php if ($unread_count > 0): ?>
                            <a href="?mark_read=all" class="mark-all-read" id="markAllReadLink">Mark all as read</a>
                        <?php endif; ?>
                    </div>
                    <div id="notificationList">
                        <?php if (empty($notifications)): ?>
                            <p class="no-notifications">No new notifications.</p>
                        <?php else: ?>
                            <?php foreach ($notifications as $notif): ?>
                                <a href="<?= htmlspecialchars($notif['link']) ?>&mark_read=<?= $notif['notification_id'] ?>" 
                                   class="notification-item <?= $notif['is_read'] == 0 ? 'unread' : '' ?>"
                                   data-id="<?= $notif['notification_id'] ?>">
                                    <p><?= $notif['message'] ?></p>
                                    <small><?= htmlspecialchars(date('M d, Y h:i A', strtotime($notif['created_at']))) ?></small>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <span class="material-icons">account_circle</span>
                <div class="user-info">
                    <span><?= htmlspecialchars($studentData['firstname'] ?? 'Student') ?></span>
                    <small><?= htmlspecialchars($studentData['email'] ?? 'No Email') ?></small>
                </div>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </nav>

        <main class="main-content">
            <?php if ($view === 'subject'): ?>
                <div class="recitation-details">
                    <a href="student_dashboard.php" class="back-link"><span class="material-icons">arrow_back</span>Back to My Courses</a>
                    <div class="content-header">
                        <h2>Recitation Records: <?= htmlspecialchars($subjectName) ?></h2>
                    </div>
                     <div class="info-grid">
                        <div class="info-box">
                            <p>Total Recitations</p>
                            <span><?= count($recitations) ?></span>
                        </div>
                        <div class="info-box">
                            <p>Average Score</p>
                            <span><?= number_format($averageScore, 2) ?></span>
                        </div>
                     </div>
                    <?php if (!empty($recitations)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Score</th>
                                    <th>Mode</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recitations as $rec): ?>
                                    <tr>
                                        <td><?= htmlspecialchars(date('M d, Y h:i A', strtotime($rec['date']))) ?></td>
                                        <td><?= htmlspecialchars($rec['score']) ?> / <?= $maxScore ?></td>
                                        <td><?= htmlspecialchars(ucfirst($rec['mode'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="no-records">You do not have any recorded recitations for this subject yet.</p>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <div class="content-header">
                     <h2>Dashboard</h2>
                </div>
                 <div class="course-overview">
                     <h3>My Courses</h3>
                     <?php if (!empty($enrolledSubjects)): ?>
                         <div class="course-grid">
                            <?php foreach ($enrolledSubjects as $subject): 
                                $subjectRecitations = $manager->getStudentRecitations($studentId, $subject['subject_code']);
                                $subjectAverage = $manager->getStudentAverageScore($studentId, $subject['subject_code']);
                                $recitCount = count($subjectRecitations);
                                $completionPercentage = 0;
                                if ($totalPossibleRecitations > 0) {
                                    $completionPercentage = min(100, round(($recitCount / $totalPossibleRecitations) * 100));
                                }
                            ?>
                             <a href="student_dashboard.php?view=subject&code=<?= htmlspecialchars($subject['subject_code']) ?>" class="course-card">
                                <div class="card-image">
                                     <?= htmlspecialchars($subject['subject_code']) ?>
                                 </div>
                                 <div class="card-content">
                                     <h4><?= htmlspecialchars($subject['subject_name']) ?></h4>
                                     <p>Section: <?= htmlspecialchars($subject['class_name']) ?></p>
                                     <div class="progress-bar-container">
                                        <div class="progress-bar" style="width: <?= $completionPercentage ?>%;"></div>
                                     </div>
                                     <div class="completion-text"><?= $completionPercentage ?>% complete</div>
                                      <p style="margin-top: 10px; font-size: 0.9em; color: #495057;">Recitations: <?= $recitCount ?></p>
                                      <p style="margin-top: 5px; font-size: 0.9em; color: #495057;">Average Score: <?= number_format($subjectAverage, 2) ?></p>
                                 </div>
                             </a>
                            <?php endforeach; ?>
                         </div>
                     <?php else: ?>
                         <p class.no-records">You are not currently enrolled in any subject. Please contact your administrator if you believe this is an error.</p>
                     <?php endif; ?>
                 </div>
            <?php endif; ?>
        </main>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const bell = document.getElementById('notificationBell');
    const dropdown = document.getElementById('notificationDropdown');
    const markAllRead = document.getElementById('markAllReadLink');

    if (bell) {
        bell.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevents the document click from firing
            const isHidden = dropdown.style.display === 'none' || dropdown.style.display === '';
            dropdown.style.display = isHidden ? 'block' : 'none';
        });
    }

    // Close dropdown if clicking outside
    document.addEventListener('click', function(e) {
        if (dropdown && dropdown.style.display === 'block') {
            if (!bell.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        }
    });

    // Handle the "Mark all as read" click
    if (markAllRead) {
        markAllRead.addEventListener('click', function(e) {
            e.preventDefault(); // Stop the link from navigating immediately
            
            // Send a quick background request to mark all as read
            fetch('?mark_read=all', {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' } // Lets PHP know it's an AJAX request
            })
            .then(response => response.json()) // We expect a JSON response
            .then(data => {
                if (data.success) {
                    // Once the server confirms, update the UI
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                    });
                    const badge = document.getElementById('notificationBadge');
                    if (badge) {
                        badge.style.display = 'none';
                    }
                    const icon = bell.querySelector('.material-icons');
                    if (icon) {
                        icon.textContent = 'notifications';
                    }
                    markAllRead.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error marking all as read:', error);
                // If it fails, just reload the page to be safe
                window.location.href = '?mark_read=all';
            });
        });
    }
});
</script>
</body>
</html>

