<?php
// Start session and include necessary files at the very beginning
session_start();
include('dbcon.php');
include('session.php');

if (!isset($_GET['class_id'])) {
    // Redirect based on user type
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'teacher') {
        header('Location: my_classes.php');
    } else {
        header('Location: student_online.php');
    }
    exit();
}

$class_id = mysqli_real_escape_string($conn, $_GET['class_id']);
$query = mysqli_query($conn, 
    "SELECT oc.*, t.firstname, t.lastname, s.subject_title, s.subject_code
     FROM online_classes oc
     INNER JOIN teacher t ON oc.teacher_id = t.teacher_id
     INNER JOIN subject s ON oc.subject_code = s.subject_code
     WHERE oc.class_id = '$class_id'");
$class = mysqli_fetch_assoc($query);

if (!$class) {
    // Redirect based on user type
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'teacher') {
        header('Location: my_classes.php?error=Class not found');
    } else {
        header('Location: student_online.php?error=Class not found');
    }
    exit();
}

// Check access for students
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'student') {
    $session_id = $_SESSION['id'];
    $access_query = mysqli_query($conn, 
    "SELECT * FROM teacher_class_student tcs
     INNER JOIN teacher_class tc ON tcs.teacher_class_id = tc.teacher_class_id
     INNER JOIN subject s ON tc.subject_id = s.subject_id
     WHERE tcs.student_id = '$session_id' 
       AND s.subject_code = '{$class['subject_code']}'");
    
    if (mysqli_num_rows($access_query) == 0) {
        header('Location: student_online.php?error=Access denied');
        exit();
    }
    
    // Log attendance (only if not already logged)
    $check_attendance = mysqli_query($conn, 
        "SELECT * FROM class_attendance 
         WHERE class_id = '$class_id' AND student_id = '$session_id'");
    
    if (mysqli_num_rows($check_attendance) == 0) {
        mysqli_query($conn, 
            "INSERT INTO class_attendance (class_id, student_id, join_time) 
             VALUES ('$class_id', '$session_id', NOW())");
    }
}

// Update class status to ongoing if it's the teacher
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'teacher' && isset($_SESSION['id']) && $class['teacher_id'] == $_SESSION['id'] && $class['status'] == 'scheduled') {
    mysqli_query($conn, "UPDATE online_classes SET status = 'ongoing' WHERE class_id = '$class_id'");
}

// Get user display name
if (isset($_SESSION['user_type']) && isset($_SESSION['id'])) {
    $session_id = $_SESSION['id'];
    if ($_SESSION['user_type'] == 'teacher') {
        $user_query = mysqli_query($conn, "SELECT firstname, lastname FROM teacher WHERE teacher_id = '$session_id'");
        $user = mysqli_fetch_assoc($user_query);
        $display_name = $user['firstname'] . ' ' . $user['lastname'] . ' (Teacher)';
    } else {
        $user_query = mysqli_query($conn, "SELECT firstname, lastname FROM student WHERE student_id = '$session_id'");
        $user = mysqli_fetch_assoc($user_query);
        $display_name = $user['firstname'] . ' ' . $user['lastname'] . ' (Student)';
    }
} else {
    $display_name = 'Guest User';
    $session_id = 0;
}

// Include header after all processing
include('header_dashboard.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Class - <?php echo htmlspecialchars($class['class_name']); ?></title>
    <link rel="stylesheet" href="admin/assets/styles.css">
    <script src="https://meet.jit.si/external_api.js"></script>
    <style>
        .classroom-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
            background: rgba(255, 255, 255, 0);
        }
        
        .main-content {
            display: flex;
            flex: 1;
            overflow: hidden;
            gap: 20px;
            padding: 15px;
        }
        
        .video-main-area {
            flex: 2;
            min-width: 600px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .video-container {
            position: relative;
            background: #000;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .tools-area {
            flex: 1;
            min-width: 400px;
            max-width: 480px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        /* Poll Container - Side by Side Layout */
        .poll-section {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .poll-create-box {
            flex: 1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 20px;
            color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .poll-active-box {
            flex: 1;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border-radius: 12px;
            padding: 20px;
            color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .poll-container {
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 15px;
            backdrop-filter: blur(10px);
        }
        
        .chat-panel {
            background: #f9f9f9;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 14px 16px;
        }
        
        .chat-messages {
            height: 180px;
            overflow-y: auto;
            border: 1px solid #ddd;
            background: #fff;
            margin-bottom: 10px;
            border-radius: 6px;
            padding: 8px;
        }
        
        .whiteboard-container {
            display: none;
            position: absolute;
            top: 20px;
            right: 20px;
            background: white;
            border: 2px solid #333;
            border-radius: 8px;
            padding: 15px;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .whiteboard-tools {
            margin-bottom: 15px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        
        #whiteboard {
            border: 1px solid #ccc;
            cursor: crosshair;
            background: white;
        }
        
        .fullscreen-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            z-index: 100;
            background: rgba(0,0,0,0.7);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .fullscreen-btn:hover {
            background: rgba(0,0,0,0.9);
        }
        
        .tool-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        
        .tool-buttons button {
            flex: 1 1 45%;
            padding: 10px;
        }
        
        .recording-controls {
            background: #fff3e0;
            border-radius: 8px;
            border: 1px solid #ffb74d;
            padding: 15px;
        }
        
        .control-panel {
            background: #e3f2fd;
            border-radius: 8px;
            padding: 15px;
        }
        
        .raised-hands-panel {
            background: #fff3cd;
            border-radius: 8px;
            border: 1px solid #ffeaa7;
            padding: 15px;
        }
        
        .attendance-panel {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
        }
        
        .student-tools {
            background: #e8f5e8;
            border-radius: 8px;
            border: 1px solid #c8e6c9;
            padding: 15px;
        }
        
        .raise-hand {
            background: #ff9800;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 25px;
            cursor: pointer;
            margin-bottom: 10px;
            font-weight: bold;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .raise-hand:hover {
            background: #f57c00;
            transform: translateY(-2px);
        }
        
        .raise-hand.raised {
            background: #f44336;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .recorded-video-container {
            display: none;
            width: 100%;
            max-width: 480px;
            text-align: center;
        }
        
        #recordedVideo {
            width: 100%;
            max-width: 480px;
            height: 270px;
            border-radius: 8px;
            border: 2px solid #333;
            background: #000;
        }
        
        .recording-status {
            padding: 12px;
            border-radius: 6px;
            text-align: center;
            margin: 8px 0;
            font-weight: bold;
            border: 1px solid;
        }
        
        .recording-active {
            background: #ffebee;
            color: #c62828;
            border-color: #f44336;
            animation: pulse 1.5s infinite;
        }
        
        .recording-idle {
            background: #e8f5e8;
            color: #2e7d32;
            border-color: #4caf50;
        }
        
        .recordings-list {
            margin-top: 15px;
            border-top: 2px solid #ffb74d;
            padding-top: 15px;
            max-height: 250px;
            overflow-y: auto;
        }
        
        .recording-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border: 1px solid #ddd;
            margin-bottom: 8px;
            border-radius: 6px;
            background: white;
        }
        
        .class-info {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #337ab7;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: bold;
        }
        
        .badge-success {
            background: #28a745;
            color: white;
        }
        
        .badge-warning {
            background: #ffc107;
            color: #212529;
        }

        /* Poll Option Styles */
        .poll-option {
            background: rgba(255,255,255,0.2);
            border-radius: 6px;
            padding: 10px;
            margin: 8px 0;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .poll-option input[type="radio"] {
            margin-right: 8px;
        }
        
        .poll-option label {
            color: white;
            font-weight: 500;
        }
        
        .poll-results {
            background: rgba(255,255,255,0.15);
            border-radius: 6px;
            padding: 12px;
            margin: 8px 0;
        }
        
        .poll-result-bar {
            background: rgba(255,255,255,0.3);
            border-radius: 4px;
            overflow: hidden;
            margin: 5px 0;
            height: 24px;
            position: relative;
        }
        
        .poll-result-fill {
            background: rgba(255,255,255,0.8);
            height: 100%;
            transition: width 0.5s ease;
            display: flex;
            align-items: center;
            padding: 0 10px;
            color: #333;
            font-weight: 600;
            font-size: 12px;
        }

        /* Fullscreen Fix */
        .fullscreen-active,
        .fullscreen-active .main-content,
        .fullscreen-active .video-container {
            padding: 0 !important;
            margin: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            max-width: 100vw !important;
            max-height: 100vh !important;
            background: #000 !important;
            z-index: 9999;
        }
        
        .fullscreen-active .video-container {
            position: fixed !important;
            left: 0; 
            top: 0;
        }
        
        .fullscreen-active #meet {
            width: 100vw !important;
            height: 100vh !important;
            min-width: 100vw !important;
            min-height: 100vh !important;
            background: #000 !important;
        }
        
        .fullscreen-active .tools-area,
        .fullscreen-active .class-info,
        .fullscreen-active .navbar,
        .fullscreen-active .block-header,
        .fullscreen-active .breadcrumb,
        .fullscreen-active .block-content > *:not(.main-content) {
            display: none !important;
        }
        
        .fullscreen-active .fullscreen-btn {
            display: none !important;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                flex-direction: column;
            }
            
            .video-main-area,
            .tools-area {
                min-width: 100%;
                max-width: 100%;
            }
            
            .poll-section {
                flex-direction: column;
            }
        }

        /* Loading States */
        .btn-loading {
            position: relative;
            color: transparent !important;
        }
        
        .btn-loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            top: 50%;
            left: 50%;
            margin: -8px 0 0 -8px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-right-color: transparent;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="<?php echo (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'student') ? 'student-view' : 'teacher-view'; ?>">
    <?php 
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'teacher') {
        include('navbar_teacher.php');
    } else {
        include('navbar_student.php');
    }
    ?>
    
    <div class="container-fluid classroom-container">
        <div class="row-fluid">
            <?php 
            if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'teacher') {
                include('teacher_online_sidebar.php');
            } else {
                include('student_online_sidebar.php');
            }
            ?>
            
            <div class="span9" id="content">
                <div class="row-fluid">
                    <ul class="breadcrumb">
                        <li><a href="#"><b>My Class</b></a><span class="divider">/</span></li>
                        <li><a href="<?php echo (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'teacher') ? 'my_classes.php' : 'student_online.php'; ?>"><b>Online Classes</b></a><span class="divider">/</span></li>
                        <li><a href="#"><b>Join Class: <?php echo htmlspecialchars($class['class_name']); ?></b></a></li>
                    </ul>
                    
                    <div class="class-info">
                        <h4><?php echo htmlspecialchars($class['class_name']); ?></h4>
                        <p>
                            <strong>Subject:</strong> <?php echo htmlspecialchars($class['subject_code'] . ' - ' . $class['subject_title']); ?> | 
                            <strong>Teacher:</strong> <?php echo htmlspecialchars($class['firstname'] . ' ' . $class['lastname']); ?> | 
                            <strong>Started:</strong> <?php echo date('M j, Y g:i A', strtotime($class['start_time'])); ?>
                        </p>
                    </div>
                    
                    <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'student' && $class['allow_recording']): ?>
                    <div id="recording-notice" class="recording-notice">
                        <i class="icon-facetime-video"></i>
                        <span id="recording-message">Recording in progress due to poor network conditions</span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="block">
                        <div class="navbar navbar-inner block-header">
                            <div class="muted pull-left">Live Classroom</div>
                            <button class="btn btn-small btn-info fullscreen-btn" id="fullscreen-btn">
                                <i class="icon-fullscreen"></i> Fullscreen
                            </button>
                        </div>
                        <div class="block-content collapse in">
                            <div class="main-content">
                                <!-- Video Main Area -->
                                <div class="video-main-area">
                                    <div class="video-container">
                                        <div id="meet"></div>
                                        
                                        <!-- Whiteboard -->
                                        <div id="whiteboard-container" class="whiteboard-container">
                                            <div class="whiteboard-tools">
                                                <button class="btn btn-small" id="whiteboard-pen">Pen</button>
                                                <button class="btn btn-small" id="whiteboard-eraser">Eraser</button>
                                                <input type="color" id="whiteboard-color" value="#000000">
                                                <input type="range" id="whiteboard-size" min="1" max="10" value="2">
                                                <button class="btn btn-small btn-danger" id="whiteboard-clear">Clear</button>
                                                <button class="btn btn-small" id="whiteboard-close">Close</button>
                                            </div>
                                            <canvas id="whiteboard" width="800" height="425"></canvas>
                                        </div>
                                    </div>
                                    
                                    <!-- Poll Section - Side by Side -->
                                    <div class="poll-section">
                                        <!-- Poll Creation (Teacher Only) -->
                                        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'teacher'): ?>
                                        <div class="poll-create-box">
                                            <h5 style="font-weight: 600; margin-bottom: 15px; color: white;">Create New Poll</h5>
                                            <form id="poll-create-form">
                                                <div class="form-group" style="margin-bottom: 15px;">
                                                    <label for="poll-question-input" style="display: block; margin-bottom: 5px; font-weight: 500; color: white;">Poll Question</label>
                                                    <input type="text" class="form-control" id="poll-question-input" name="poll-question-input" required style="width: 100%; padding: 10px; border: 1px solid rgba(255,255,255,0.3); border-radius: 6px; background: rgba(255,255,255,0.1); color: white;">
                                                </div>
                                                <div class="form-group" style="margin-bottom: 15px;">
                                                    <label style="display: block; margin-bottom: 5px; font-weight: 500; color: white;">Options</label>
                                                    <div id="poll-options-container">
                                                        <input type="text" class="form-control poll-option-input" name="poll-option[]" placeholder="Option 1" required style="width: 100%; padding: 8px; border: 1px solid rgba(255,255,255,0.3); border-radius: 4px; margin-bottom: 5px; background: rgba(255,255,255,0.1); color: white;">
                                                        <input type="text" class="form-control poll-option-input" name="poll-option[]" placeholder="Option 2" required style="width: 100%; padding: 8px; border: 1px solid rgba(255,255,255,0.3); border-radius: 4px; margin-bottom: 5px; background: rgba(255,255,255,0.1); color: white;">
                                                    </div>
                                                    <button type="button" class="btn btn-link" id="add-poll-option" style="padding: 5px 0; font-size: 13px; color: white;">+ Add Option</button>
                                                </div>
                                                <button type="submit" class="btn btn-warning" style="width: 100%; padding: 12px; font-weight: 600; background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white;">
                                                    <i class="icon-bar-chart"></i> Create Poll
                                                </button>
                                            </form>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <!-- Active Poll Display -->
                                        <div class="poll-active-box">
                                            <h5 style="font-weight: 600; margin-bottom: 15px; color: white;">Active Poll</h5>
                                            <div id="poll-container" class="poll-container">
                                                <h6 id="poll-question" style="font-weight: 600; margin-bottom: 15px; color: white;">No active poll</h6>
                                                <div id="poll-options"></div>
                                                <button class="btn btn-success" id="vote-poll" style="display: none; width: 100%; font-weight: 600; margin-top: 10px; background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white;">
                                                    Vote Now
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Chat Panel -->
                                    <div class="chat-panel">
                                        <h4 style="font-weight: 600; color: #2a3b4c; margin-bottom: 15px;">Class Chat</h4>
                                        <div id="chat-messages" class="chat-messages"></div>
                                        <div class="chat-input-container" style="display: flex; gap: 8px;">
                                            <input type="text" id="chat-input" class="chat-input" placeholder="Type your message..." style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                                            <button class="btn btn-primary" id="send-message" style="padding: 10px 20px; border-radius: 6px;">Send</button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Tools Area -->
                                <div class="tools-area">
                                    <!-- Recording Video Preview -->
                                    <div class="recorded-video-container" id="recordedVideoContainer">
                                        <h5>Recorded Session</h5>
                                        <video id="recordedVideo" controls></video>
                                    </div>
                                    
                                    <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'teacher'): ?>
                                    <!-- Teacher Tools -->
                                    <div class="tool-buttons">
                                        <button class="btn btn-warning" id="share-screen">
                                            <i class="icon-desktop"></i> Share Screen
                                        </button>
                                        <button class="btn btn-success" id="start-recording">
                                            <i class="icon-facetime-video"></i> Start Recording
                                        </button>
                                        <button class="btn btn-danger" id="stop-recording" style="display: none;">
                                            <i class="icon-stop"></i> Stop Recording
                                        </button>
                                        <button class="btn btn-primary" id="whiteboard-toggle">
                                            <i class="icon-pencil"></i> Whiteboard
                                        </button>
                                        <button class="btn btn-danger" id="end-class">
                                            <i class="icon-off"></i> End Class
                                        </button>
                                    </div>
                                    
                                    <!-- Recording Controls -->
                                    <div class="recording-controls">
                                        <h5 style="margin-bottom: 10px;">Screen Recording</h5>
                                        <div id="recording-status" class="recording-status recording-idle">
                                            Ready to record
                                        </div>
                                        <div class="download-section" style="text-align: center; margin: 10px 0;">
                                            <button class="btn btn-small btn-primary" id="downloadButton" disabled>
                                                <i class="icon-download"></i> Download Recording
                                            </button>
                                        </div>
                                        <div class="recordings-list">
                                            <h6 style="margin-bottom: 10px;">Previous Recordings</h6>
                                            <div id="recordingsList">
                                                <p class="text-muted">No recordings yet</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Student Controls -->
                                    <div class="control-panel">
                                        <h5 style="margin-bottom: 10px;">Student Controls</h5>
                                        <div class="control-item" style="margin: 8px 0;">
                                            <label class="checkbox">
                                                <input type="checkbox" id="enable-chat" checked> Enable Chat
                                            </label>
                                        </div>
                                        <div class="control-item" style="margin: 8px 0;">
                                            <label class="checkbox">
                                                <input type="checkbox" id="enable-polls" checked> Enable Polls
                                            </label>
                                        </div>
                                        <div class="control-item" style="margin: 8px 0;">
                                            <label class="checkbox">
                                                <input type="checkbox" id="enable-raise-hand" checked> Enable Raise Hand
                                            </label>
                                        </div>
                                        <div class="control-item" style="margin: 8px 0;">
                                            <label class="checkbox">
                                                <input type="checkbox" id="enable-whiteboard" checked> Enable Whiteboard
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <!-- Raised Hands Panel -->
                                    <div class="raised-hands-panel">
                                        <h5 style="margin-bottom: 10px;">Raised Hands ✋</h5>
                                        <div id="raised-hands-list">
                                            <p>No hands raised</p>
                                        </div>
                                    </div>
                                    
                                    <!-- Attendance Panel -->
                                    <div class="attendance-panel">
                                        <h5 style="margin-bottom: 10px;">Attendance</h5>
                                        <div id="attendance-list" class="attendance-list" style="max-height: 150px; overflow-y: auto; margin-bottom: 10px;">
                                            <p>Loading attendance...</p>
                                        </div>
                                        <button class="btn btn-small btn-success" id="export-attendance">
                                            <i class="icon-download"></i> Export Attendance
                                        </button>
                                    </div>
                                    
                                    <?php else: ?>
                                    <!-- Student Tools -->
                                    <div class="student-tools">
                                        <button class="raise-hand" id="raise-hand">
                                            <i class="icon-hand-up"></i> Raise Hand
                                        </button>
                                        <button class="btn btn-small btn-primary" id="student-whiteboard">
                                            <i class="icon-pencil"></i> Whiteboard
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('footer.php'); ?>
    <?php include('script.php'); ?>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="bootstrap/js/bootstrap.min.js"></script>
    <script>
    // Global variables
    let mediaRecorder = null;
    let recordedChunks = [];
    let isRecording = false;
    let isHandRaised = false;
    let whiteboardActive = false;
    let currentPollId = null;
    let studentControls = {
        chat: true,
        polls: true,
        raiseHand: true,
        whiteboard: true
    };

    // Recording variables
    let currentRecordingBlob = null;

    const classId = "<?php echo $class_id; ?>";
    const userId = "<?php echo $session_id; ?>";
    const userType = "<?php echo isset($_SESSION['user_type']) ? $_SESSION['user_type'] : ''; ?>";
    const displayName = "<?php echo $display_name; ?>";

    // Initialize Jitsi Meet API
    const domain = 'meet.jit.si';
    const options = {
        roomName: "<?php echo $class['room_name']; ?>",
        width: "100%",
        height: 500,
        parentNode: document.querySelector('#meet'),
        configOverwrite: { 
            prejoinPageEnabled: false,
            disableModeratorIndicator: false,
            startAudioOnly: false,
            enableEmailInStats: false,
            enableWelcomePage: false,
            enableClosePage: false,
            disableInviteFunctions: true,
            startWithAudioMuted: false,
            startWithVideoMuted: false,
            enableNoisyMicDetection: false,
            resolution: 720,
            constraints: {
                video: {
                    height: { ideal: 720, max: 1080, min: 240 }
                }
            }
        },
        interfaceConfigOverwrite: {
            TOOLBAR_BUTTONS: [
                'microphone', 'camera', 'closedcaptions', 'desktop', 'fullscreen',
                'fodeviceselection', 'hangup', 'profile', 'chat', 'recording',
                'livestreaming', 'etherpad', 'sharedvideo', 'settings', 'raisehand',
                'videoquality', 'filmstrip', 'invite', 'feedback', 'stats', 'shortcuts',
                'tileview', 'videobackgroundblur', 'download', 'help', 'mute-everyone',
                'security'
            ],
            SETTINGS_SECTIONS: ['devices', 'language', 'moderator', 'profile', 'calendar'],
            SHOW_JITSI_WATERMARK: false,
            SHOW_WATERMARK_FOR_GUESTS: false,
            SHOW_BRAND_WATERMARK: false,
            BRAND_WATERMARK_LINK: '',
            SHOW_POWERED_BY: false,
            DISABLE_VIDEO_BACKGROUND: false,
            DISABLE_FOCUS_INDICATOR: false,
            DISABLE_DOMINANT_SPEAKER_INDICATOR: false
        },
        userInfo: {
            displayName: displayName,
            email: ''
        }
    };

    const api = new JitsiMeetExternalAPI(domain, options);

    // Event Handlers
    api.addEventListener('videoConferenceJoined', (response) => {
        console.log('Conference joined:', response);
        
        // For teachers, set them as moderators
        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'teacher'): ?>
        api.executeCommand('password', 'moderator123');
        <?php endif; ?>
    });

    api.addEventListener('videoConferenceLeft', () => {
        console.log('Conference left');
        logAttendanceExit();
        
        // Lower hand if raised
        if (isHandRaised) {
            lowerHand();
        }
    });

    api.addEventListener('participantJoined', (participant) => {
        console.log('Participant joined:', participant);
        updateAttendanceList();
    });

    api.addEventListener('participantLeft', (participant) => {
        console.log('Participant left:', participant);
        updateAttendanceList();
    });

    api.addEventListener('videoConferenceError', (error) => {
        console.error('Conference error:', error);
        alert('Error joining conference: ' + error);
    });

    // ========== FIX 1: FULLSCREEN FUNCTIONALITY ==========
    const fullscreenBtn = document.getElementById('fullscreen-btn');
    const videoContainer = document.querySelector('.video-container');
    
    fullscreenBtn.addEventListener('click', () => {
        if (!document.fullscreenElement) {
            if (videoContainer.requestFullscreen) {
                videoContainer.requestFullscreen();
            } else if (videoContainer.webkitRequestFullscreen) {
                videoContainer.webkitRequestFullscreen();
            } else if (videoContainer.msRequestFullscreen) {
                videoContainer.msRequestFullscreen();
            }
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            } else if (document.msExitFullscreen) {
                document.msExitFullscreen();
            }
        }
    });

    // Listen for fullscreen change to toggle class
    function onFullscreenChange() {
        if (document.fullscreenElement === videoContainer || 
            document.webkitFullscreenElement === videoContainer || 
            document.msFullscreenElement === videoContainer) {
            document.body.classList.add('fullscreen-active');
        } else {
            document.body.classList.remove('fullscreen-active');
        }
    }
    
    document.addEventListener('fullscreenchange', onFullscreenChange);
    document.addEventListener('webkitfullscreenchange', onFullscreenChange);
    document.addEventListener('msfullscreenchange', onFullscreenChange);

    // ========== FIX 2: POLL FUNCTIONALITY - SIDE BY SIDE ==========
    <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'teacher'): ?>
    // Teacher: Poll creation form
    document.getElementById('add-poll-option').addEventListener('click', function() {
        const optionsContainer = document.getElementById('poll-options-container');
        const optionCount = optionsContainer.querySelectorAll('.poll-option-input').length + 1;
        const newInput = document.createElement('input');
        newInput.type = 'text';
        newInput.className = 'form-control poll-option-input';
        newInput.name = 'poll-option[]';
        newInput.placeholder = 'Option ' + optionCount;
        newInput.required = true;
        newInput.style = 'width: 100%; padding: 8px; border: 1px solid rgba(255,255,255,0.3); border-radius: 4px; margin-bottom: 5px; background: rgba(255,255,255,0.1); color: white;';
        optionsContainer.appendChild(newInput);
    });

    document.getElementById('poll-create-form').addEventListener('submit', function(e) {
        e.preventDefault();
        createPoll();
    });

    function createPoll() {
        const question = document.getElementById('poll-question-input').value.trim();
        const optionInputs = document.querySelectorAll('.poll-option-input');
        const options = Array.from(optionInputs).map(input => input.value.trim()).filter(opt => opt !== '');
        
        if (!question) {
            alert('Please enter a poll question');
            return;
        }
        
        if (options.length < 2) {
            alert('Please provide at least 2 options');
            return;
        }

        // Show loading state
        const submitBtn = document.querySelector('#poll-create-form button[type="submit"]');
        submitBtn.classList.add('btn-loading');

        fetch('api/poll_operations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'create',
                class_id: classId,
                teacher_id: userId,
                question: question,
                options: options
            })
        })
        .then(response => response.json())
        .then(data => {
            submitBtn.classList.remove('btn-loading');
            if (data.success) {
                // Clear form
                document.getElementById('poll-question-input').value = '';
                document.querySelectorAll('.poll-option-input').forEach(input => input.value = '');
                // Reset to 2 options
                const optionsContainer = document.getElementById('poll-options-container');
                while (optionsContainer.children.length > 2) {
                    optionsContainer.removeChild(optionsContainer.lastChild);
                }
                alert('Poll created successfully!');
                // Refresh active poll display
                checkForPolls();
            } else {
                alert('Error creating poll: ' + data.error);
            }
        })
        .catch(error => {
            submitBtn.classList.remove('btn-loading');
            console.error('Error creating poll:', error);
            alert('Network error while creating poll');
        });
    }
    <?php endif; ?>

    // ========== ACTIVE POLL DISPLAY FOR ALL USERS ==========
    let hasVotedPollId = null;
    let pollResultsTimer = null;

    function checkForPolls() {
        if (!studentControls.polls && userType === 'student') return;

        fetch('api/poll_operations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'get_active',
                class_id: classId,
                student_id: userId
            })
        })
        .then(response => response.json())
        .then(data => {
            const pollContainer = document.getElementById('poll-container');
            if (data.success && data.poll) {
                currentPollId = data.poll.poll_id;
                document.getElementById('poll-question').textContent = data.poll.question;
                const optionsContainer = document.getElementById('poll-options');
                optionsContainer.innerHTML = '';

                // If already voted, show results
                if (data.poll.voted) {
                    showPollResults(data.poll);
                    return;
                }

                pollContainer.style.display = 'block';
                document.getElementById('vote-poll').style.display = 'block';

                data.poll.options.forEach((option, index) => {
                    const optionDiv = document.createElement('div');
                    optionDiv.className = 'poll-option';
                    optionDiv.innerHTML = `
                        <input type="radio" name="poll_option" value="${index}" id="option_${index}">
                        <label for="option_${index}">${option}</label>
                    `;
                    optionsContainer.appendChild(optionDiv);
                });
            } else {
                document.getElementById('poll-question').textContent = 'No active poll';
                document.getElementById('poll-options').innerHTML = '';
                document.getElementById('vote-poll').style.display = 'none';
            }
        });
    }

    function showPollResults(poll) {
        const pollContainer = document.getElementById('poll-container');
        pollContainer.style.display = 'block';
        document.getElementById('vote-poll').style.display = 'none';
        const optionsContainer = document.getElementById('poll-options');
        optionsContainer.innerHTML = '';
        
        if (!poll.results) {
            optionsContainer.innerHTML = '<div class="poll-option"><em>Thank you for voting! Waiting for other responses...</em></div>';
            return;
        }
        
        let totalVotes = poll.results.reduce((a, b) => a + b, 0);
        poll.options.forEach((option, idx) => {
            const count = poll.results[idx] || 0;
            const percent = totalVotes > 0 ? Math.round((count / totalVotes) * 100) : 0;
            const bar = `
                <div class="poll-result-bar">
                    <div class="poll-result-fill" style="width: ${percent}%">
                        ${percent}% (${count} votes)
                    </div>
                </div>
            `;
            optionsContainer.innerHTML += `
                <div class="poll-results">
                    <strong>${option}</strong>
                    ${bar}
                </div>
            `;
        });
        
        // Hide poll after 20 seconds for students
        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'student'): ?>
        clearTimeout(pollResultsTimer);
        pollResultsTimer = setTimeout(() => {
            pollContainer.style.display = 'none';
        }, 20000);
        <?php endif; ?>
    }

    document.getElementById('vote-poll').addEventListener('click', () => {
        const selectedOption = document.querySelector('input[name="poll_option"]:checked');
        if (selectedOption) {
            const voteBtn = document.getElementById('vote-poll');
            voteBtn.classList.add('btn-loading');

            fetch('api/poll_operations.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'vote',
                    class_id: classId,
                    poll_id: currentPollId,
                    student_id: userId,
                    option_index: selectedOption.value
                })
            })
            .then(response => response.json())
            .then(data => {
                voteBtn.classList.remove('btn-loading');
                if (data.success) {
                    // After voting, fetch poll again to show results
                    setTimeout(checkForPolls, 500);
                } else {
                    alert('Error submitting vote: ' + data.error);
                }
            })
            .catch(error => {
                voteBtn.classList.remove('btn-loading');
                console.error('Error submitting vote:', error);
                alert('Network error while submitting vote');
            });
        } else {
            alert('Please select an option');
        }
    });

    // Check for polls every 3 seconds
    setInterval(checkForPolls, 3000);

    // ========== FIX 3: SCREEN SHARING - STUDENTS CAN SEE ==========
    <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'teacher'): ?>
    let isScreenSharing = false;
    const shareScreenBtn = document.getElementById('share-screen');

    function setScreenShareIndicator(active) {
        if (active) {
            shareScreenBtn.classList.remove('btn-warning');
            shareScreenBtn.classList.add('btn-success');
            shareScreenBtn.innerHTML = '<i class="icon-desktop"></i> Sharing Screen';
        } else {
            shareScreenBtn.classList.remove('btn-success');
            shareScreenBtn.classList.add('btn-warning');
            shareScreenBtn.innerHTML = '<i class="icon-desktop"></i> Share Screen';
        }
    }

    shareScreenBtn.addEventListener('click', () => {
        try {
            api.executeCommand('toggleShareScreen');
        } catch (err) {
            alert('Screen sharing is not supported or failed to start.');
        }
    });

    // Listen for Jitsi events for screen sharing
    api.addEventListener('screenSharingStatusChanged', (event) => {
        if (event.on) {
            isScreenSharing = true;
            setScreenShareIndicator(true);
        } else {
            isScreenSharing = false;
            setScreenShareIndicator(false);
        }
    });
    <?php endif; ?>

    // ========== FIX 4: ATTENDANCE SYSTEM ==========
    function updateAttendanceList() {
        fetch('api/attendance_operation.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'get_attendance',
                class_id: classId
            })
        })
        .then(response => response.json())
        .then(data => {
            const attendanceList = document.getElementById('attendance-list');
            if (data.success && data.attendance.length > 0) {
                let html = '<ul style="list-style: none; margin: 0; padding: 0;">';
                data.attendance.forEach(attendee => {
                    let duration = attendee.duration ? ` <span class="text-muted">(${attendee.duration})</span>` : '';
                    html += `
                        <li style="padding: 8px 0; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <i class="icon-user"></i> ${attendee.name} 
                                <small class="text-muted">(${attendee.enrollment_no}) - ${attendee.join_time}</small>
                                ${duration}
                            </div>
                            <span class="badge ${attendee.status === 'Online' ? 'badge-success' : 'badge-warning'}">
                                ${attendee.status}
                            </span>
                        </li>
                    `;
                });
                html += '</ul>';
                attendanceList.innerHTML = html;
            } else {
                attendanceList.innerHTML = '<p>No attendees yet</p>';
            }
        })
        .catch(error => {
            console.error('Error fetching attendance:', error);
        });
    }

    // Update attendance every 10 seconds
    setInterval(updateAttendanceList, 10000);

    // ========== FIX 5: CLASS END - INSTANT REDIRECT ==========
    <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'teacher'): ?>
    document.getElementById('end-class').addEventListener('click', () => {
        if (confirm('Are you sure you want to end this class for all students? This action cannot be undone.')) {
            const endBtn = document.getElementById('end-class');
            endBtn.classList.add('btn-loading');

            fetch('api/class_operations.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'end_class',
                    class_id: classId
                })
            })
            .then(response => response.json())
            .then(data => {
                endBtn.classList.remove('btn-loading');
                if (data.success) {
                    // INSTANT REDIRECT - No alert delay
                    window.location.href = 'my_classes.php?ended=1';
                } else {
                    alert('Error ending class: ' + data.error);
                }
            })
            .catch(error => {
                endBtn.classList.remove('btn-loading');
                console.error('Error ending class:', error);
                alert('Network error while ending class');
            });
        }
    });
    <?php else: ?>
    // Student: Listen for class end and INSTANT redirect
    function checkClassStatus() {
        fetch('api/class_operations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'get_status',
                class_id: classId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.status === 'ended') {
                // INSTANT REDIRECT - No alert delay
                window.location.href = 'student_online.php?ended=1';
            }
        })
        .catch(error => {
            console.error('Error checking class status:', error);
        });
    }
    
    // Check class status every 5 seconds for faster response
    setInterval(checkClassStatus, 5000);
    <?php endif; ?>

    // ========== RECORDING FUNCTIONALITY ==========
    <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'teacher'): ?>
    
    document.getElementById('start-recording').addEventListener('click', startRecording);
    document.getElementById('stop-recording').addEventListener('click', stopRecording);
    document.getElementById('downloadButton').addEventListener('click', downloadRecording);
    
    async function startRecording() {
        try {
            const startBtn = document.getElementById('start-recording');
            startBtn.classList.add('btn-loading');
            
            const stream = await navigator.mediaDevices.getDisplayMedia({
                video: {
                    cursor: "always",
                    displaySurface: "window"
                },
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    sampleRate: 44100
                }
            });

            startBtn.classList.remove('btn-loading');

            const options = {
                mimeType: 'video/webm; codecs=vp9,opus',
                videoBitsPerSecond: 2500000
            };

            if (!MediaRecorder.isTypeSupported(options.mimeType)) {
                options.mimeType = 'video/webm; codecs=vp8,opus';
            }

            mediaRecorder = new MediaRecorder(stream, options);
            recordedChunks = [];

            mediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    recordedChunks.push(event.data);
                }
            };

            mediaRecorder.onstop = () => {
                currentRecordingBlob = new Blob(recordedChunks, { type: 'video/webm' });
                
                const recordedVideo = document.getElementById('recordedVideo');
                recordedVideo.src = URL.createObjectURL(currentRecordingBlob);
                document.getElementById('recordedVideoContainer').style.display = 'block';
                
                document.getElementById('downloadButton').disabled = false;
                updateRecordingStatus('Recording completed', 'idle');
                uploadRecording(currentRecordingBlob);
                addToRecordingsList(currentRecordingBlob);
                
                stream.getTracks().forEach(track => track.stop());
            };

            mediaRecorder.onerror = (event) => {
                console.error('MediaRecorder error:', event.error);
                updateRecordingStatus('Recording error: ' + event.error, 'idle');
            };

            mediaRecorder.start(1000);
            
            document.getElementById('start-recording').style.display = 'none';
            document.getElementById('stop-recording').style.display = 'inline-block';
            document.getElementById('downloadButton').disabled = true;
            
            updateRecordingStatus('Recording in progress...', 'active');

            stream.getVideoTracks()[0].onended = () => {
                if (mediaRecorder.state === 'recording') {
                    stopRecording();
                }
            };

        } catch (error) {
            document.getElementById('start-recording').classList.remove('btn-loading');
            console.error('Error starting recording:', error);
            updateRecordingStatus('Error: ' + error.message, 'idle');
        }
    }

    function stopRecording() {
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            const stopBtn = document.getElementById('stop-recording');
            stopBtn.classList.add('btn-loading');
            
            mediaRecorder.stop();
            
            document.getElementById('start-recording').style.display = 'inline-block';
            document.getElementById('stop-recording').style.display = 'none';
            stopBtn.classList.remove('btn-loading');
        }
    }

    function downloadRecording() {
        if (currentRecordingBlob) {
            const downloadBtn = document.getElementById('downloadButton');
            downloadBtn.classList.add('btn-loading');
            
            const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
            const filename = `class_recording_${classId}_${timestamp}.webm`;
            
            const url = URL.createObjectURL(currentRecordingBlob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            setTimeout(() => {
                downloadBtn.classList.remove('btn-loading');
            }, 1000);
        }
    }

    function updateRecordingStatus(message, status) {
        const statusDiv = document.getElementById('recording-status');
        statusDiv.textContent = message;
        statusDiv.className = `recording-status recording-${status}`;
    }

    function addToRecordingsList(blob) {
        const timestamp = new Date().toLocaleString();
        const recordingsList = document.getElementById('recordingsList');
        
        if (recordingsList.querySelector('.text-muted')) {
            recordingsList.innerHTML = '';
        }
        
        const recordingItem = document.createElement('div');
        recordingItem.className = 'recording-item';
        
        recordingItem.innerHTML = `
            <div class="recording-info">
                <strong>Recording from ${timestamp}</strong>
                <br>
                <small>Size: ${formatFileSize(blob.size)}</small>
            </div>
            <div class="recording-actions">
                <button class="btn btn-mini btn-info play-btn">Play</button>
                <button class="btn btn-mini btn-success download-btn">Download</button>
            </div>
        `;
        
        const playBtn = recordingItem.querySelector('.play-btn');
        const downloadBtn = recordingItem.querySelector('.download-btn');
        
        playBtn._blobUrl = URL.createObjectURL(blob);
        downloadBtn._blob = blob;
        downloadBtn._timestamp = timestamp;
        
        playBtn.addEventListener('click', function() {
            const recordedVideo = document.getElementById('recordedVideo');
            recordedVideo.src = this._blobUrl;
            recordedVideo.play();
            document.getElementById('recordedVideoContainer').style.display = 'block';
        });
        
        downloadBtn.addEventListener('click', function() {
            const blob = this._blob;
            const timestamp = this._timestamp.replace(/[/:\\]/g, '-');
            const filename = `recording-${timestamp}.webm`;
            
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        });
        
        recordingsList.insertBefore(recordingItem, recordingsList.firstChild);
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function uploadRecording(blob) {
        const formData = new FormData();
        formData.append("recording", blob, `class_recording_${classId}_${Date.now()}.webm`);
        formData.append("class_id", classId);
        formData.append("teacher_id", userId);

        fetch("save_recording.php", {
            method: "POST",
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            console.log("Recording uploaded:", data);
        })
        .catch(err => {
            console.error("Upload failed:", err);
        });
    }
    <?php endif; ?>

    // ========== RAISE HAND FUNCTIONALITY ==========
    <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'student'): ?>
    document.getElementById('raise-hand').addEventListener('click', toggleRaiseHand);
    
    function toggleRaiseHand() {
        if (!studentControls.raiseHand) {
            alert('Raise hand feature is disabled by teacher');
            return;
        }
        
        if (!isHandRaised) {
            raiseHand();
        } else {
            lowerHand();
        }
    }
    
    function raiseHand() {
        const raiseHandBtn = document.getElementById('raise-hand');
        raiseHandBtn.classList.add('btn-loading');
        
        fetch('api/hand_raise_operations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'raise_hand',
                class_id: classId,
                student_id: userId,
                student_name: displayName
            })
        })
        .then(response => response.json())
        .then(data => {
            raiseHandBtn.classList.remove('btn-loading');
            if (data.success) {
                isHandRaised = true;
                const raiseHandBtn = document.getElementById('raise-hand');
                raiseHandBtn.classList.add('raised');
                raiseHandBtn.innerHTML = '<i class="icon-hand-down"></i> Lower Hand';
                api.executeCommand('raiseHand');
            }
        })
        .catch(error => {
            raiseHandBtn.classList.remove('btn-loading');
            console.error('Error raising hand:', error);
        });
    }
    
    function lowerHand() {
        const raiseHandBtn = document.getElementById('raise-hand');
        raiseHandBtn.classList.add('btn-loading');
        
        fetch('api/hand_raise_operations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'lower_hand',
                class_id: classId,
                student_id: userId
            })
        })
        .then(response => response.json())
        .then(data => {
            raiseHandBtn.classList.remove('btn-loading');
            if (data.success) {
                isHandRaised = false;
                const raiseHandBtn = document.getElementById('raise-hand');
                raiseHandBtn.classList.remove('raised');
                raiseHandBtn.innerHTML = '<i class="icon-hand-up"></i> Raise Hand';
            }
        })
        .catch(error => {
            raiseHandBtn.classList.remove('btn-loading');
            console.error('Error lowering hand:', error);
        });
    }
    <?php else: ?>
    // Teacher: Get raised hands
    function getRaisedHands() {
        fetch('api/hand_raise_operations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'get_raised_hands',
                class_id: classId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateRaisedHandsList(data.raised_hands);
            }
        })
        .catch(error => {
            console.error('Error getting raised hands:', error);
        });
    }
    
    function updateRaisedHandsList(raisedHands) {
        const handsList = document.getElementById('raised-hands-list');
        
        if (raisedHands.length === 0) {
            handsList.innerHTML = '<p>No hands raised</p>';
            return;
        }
        
        handsList.innerHTML = '';
        raisedHands.forEach(hand => {
            const handItem = document.createElement('div');
            handItem.className = 'raised-hand-item';
            handItem.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 8px; border-bottom: 1px solid #ffeaa7; background: white; margin-bottom: 5px; border-radius: 4px;';
            handItem.innerHTML = `
                <span>${hand.student_name}</span>
                <small class="text-muted">${new Date(hand.raised_at).toLocaleTimeString()}</small>
                <button class="btn btn-mini btn-success clear-hand" data-event-id="${hand.event_id}">
                    <i class="icon-ok"></i> Clear
                </button>
            `;
            handsList.appendChild(handItem);
        });
        
        document.querySelectorAll('.clear-hand').forEach(button => {
            button.addEventListener('click', function() {
                const eventId = this.getAttribute('data-event-id');
                clearRaisedHand(eventId);
            });
        });
    }
    
    function clearRaisedHand(eventId) {
        const clearBtn = document.querySelector(`[data-event-id="${eventId}"]`);
        clearBtn.classList.add('btn-loading');
        
        fetch('api/hand_raise_operations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'clear_hand',
                class_id: classId,
                event_id: eventId
            })
        })
        .then(response => response.json())
        .then(data => {
            clearBtn.classList.remove('btn-loading');
            if (data.success) {
                getRaisedHands();
            }
        })
        .catch(error => {
            clearBtn.classList.remove('btn-loading');
            console.error('Error clearing hand:', error);
        });
    }
    
    setInterval(getRaisedHands, 2000);
    <?php endif; ?>

    // ========== STUDENT CONTROLS ==========
    <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'teacher'): ?>
    function setupStudentControls() {
        const controls = ['chat', 'polls', 'raise_hand', 'whiteboard'];
        
        controls.forEach(control => {
            const checkbox = document.getElementById(`enable-${control}`);
            if (checkbox) {
                checkbox.addEventListener('change', function() {
                    updateControl(control, this.checked);
                });
            }
        });
    }

    function updateControl(control, enabled) {
        studentControls[control.replace('_', '')] = enabled;
        
        fetch('api/class_controls.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'update_controls',
                class_id: classId,
                teacher_id: userId,
                [`enable_${control}`]: enabled ? 1 : 0
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log(`Control ${control} updated to: ${enabled}`);
            }
        })
        .catch(error => {
            console.error('Error updating control:', error);
        });
    }
    <?php endif; ?>

    // ========== WHITEBOARD FUNCTIONALITY ==========
    function initWhiteboard() {
        const canvas = document.getElementById('whiteboard');
        const ctx = canvas.getContext('2d');
        let isDrawing = false;
        let lastX = 0;
        let lastY = 0;
        let currentTool = 'pen';
        let currentColor = '#000000';
        let currentSize = 2;

        // Set white background
        ctx.fillStyle = 'white';
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        function startDrawing(e) {
            isDrawing = true;
            [lastX, lastY] = [e.offsetX, e.offsetY];
        }

        function draw(e) {
            if (!isDrawing) return;
            
            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
            ctx.lineTo(e.offsetX, e.offsetY);
            ctx.strokeStyle = currentTool === 'eraser' ? 'white' : currentColor;
            ctx.lineWidth = currentSize * (currentTool === 'eraser' ? 5 : 1);
            ctx.lineCap = 'round';
            ctx.stroke();
            
            [lastX, lastY] = [e.offsetX, e.offsetY];
        }

        function stopDrawing() {
            isDrawing = false;
        }

        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseout', stopDrawing);

        document.getElementById('whiteboard-pen').addEventListener('click', () => {
            currentTool = 'pen';
        });

        document.getElementById('whiteboard-eraser').addEventListener('click', () => {
            currentTool = 'eraser';
        });

        document.getElementById('whiteboard-color').addEventListener('change', (e) => {
            currentColor = e.target.value;
        });

        document.getElementById('whiteboard-size').addEventListener('change', (e) => {
            currentSize = parseInt(e.target.value);
        });

        document.getElementById('whiteboard-clear').addEventListener('click', () => {
            ctx.fillStyle = 'white';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
        });

        document.getElementById('whiteboard-close').addEventListener('click', () => {
            document.getElementById('whiteboard-container').style.display = 'none';
            whiteboardActive = false;
        });
    }

    // Toggle whiteboard
    <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'teacher'): ?>
    document.getElementById('whiteboard-toggle').addEventListener('click', () => {
        const whiteboard = document.getElementById('whiteboard-container');
        whiteboard.style.display = whiteboard.style.display === 'none' ? 'block' : 'none';
        whiteboardActive = !whiteboardActive;
        
        if (whiteboardActive) {
            initWhiteboard();
        }
    });
    <?php else: ?>
    document.getElementById('student-whiteboard').addEventListener('click', () => {
        if (!studentControls.whiteboard) {
            alert('Whiteboard is disabled by teacher');
            return;
        }
        const whiteboard = document.getElementById('whiteboard-container');
        whiteboard.style.display = whiteboard.style.display === 'none' ? 'block' : 'none';
        whiteboardActive = !whiteboardActive;
        
        if (whiteboardActive) {
            initWhiteboard();
        }
    });
    <?php endif; ?>

    // ========== EXPORT ATTENDANCE ==========
    document.getElementById('export-attendance').addEventListener('click', () => {
        const exportBtn = document.getElementById('export-attendance');
        exportBtn.classList.add('btn-loading');
        
        window.open(`api/attendance_operation.php?action=export_attendance&class_id=${classId}`, '_blank');
        
        setTimeout(() => {
            exportBtn.classList.remove('btn-loading');
        }, 2000);
    });

    // ========== ATTENDANCE LOGGING ==========
    function logAttendanceExit() {
        fetch('api/attendance_operation.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'log_exit',
                class_id: classId,
                user_id: userId,
                user_type: userType
            })
        })
        .catch(error => {
            console.error('Error logging exit:', error);
        });
    }

    // ========== INITIALIZATION ==========
    function loadStudentControls() {
        fetch('api/class_controls.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'get_controls',
                class_id: classId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                studentControls = {
                    chat: data.controls.enable_chat == 1,
                    polls: data.controls.enable_polls == 1,
                    raiseHand: data.controls.enable_raise_hand == 1,
                    whiteboard: data.controls.enable_whiteboard == 1
                };
                updateUIForControls();
            }
        })
        .catch(error => {
            console.error('Error loading controls:', error);
        });
    }

    function updateUIForControls() {
        if (userType === 'student') {
            document.getElementById('chat-input').disabled = !studentControls.chat;
            document.getElementById('send-message').disabled = !studentControls.chat;
            
            if (!studentControls.raiseHand) {
                document.getElementById('raise-hand').style.display = 'none';
            }
            if (!studentControls.whiteboard) {
                document.getElementById('student-whiteboard').style.display = 'none';
            }
        }
    }

    // Initialize everything when page loads
    document.addEventListener('DOMContentLoaded', function() {
        loadStudentControls();
        updateAttendanceList();
        checkForPolls(); // Initial poll check for all users
        
        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'teacher'): ?>
        setupStudentControls();
        setInterval(updateAttendanceList, 10000);
        <?php endif; ?>
        
        // Handle page unload
        window.addEventListener('beforeunload', () => {
            logAttendanceExit();
        });
    });

    // ========== NETWORK MONITORING ==========
    <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'student'): ?>
    api.addListener('endpointStatsReceived', (stats) => {
        fetch('api/log_network.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                class_id: classId,
                student_id: userId,
                bitrate: stats.bitrate || 0,
                packet_loss: stats.packetLoss || 0
            })
        });
    });
    <?php endif; ?>
    </script>
</body>
</html>