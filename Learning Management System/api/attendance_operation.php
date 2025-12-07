<?php
session_start();
include('../dbcon.php');

header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// Handle both POST and GET requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
} else {
    $action = $_GET['action'] ?? '';
}

switch ($action) {
    case 'get_attendance':
        getAttendance($input ?? $_GET);
        break;
    case 'log_exit':
        logExit($input ?? $_GET);
        break;
    case 'export_attendance':
        exportAttendance($_GET);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function getAttendance($data) {
    global $conn;
    $class_id = mysqli_real_escape_string($conn, $data['class_id']);
    $query = "SELECT ca.*, s.firstname, s.lastname, s.student_id as enrollment_no,
                     CASE 
                         WHEN ca.leave_time IS NULL THEN 'Online'
                         ELSE 'Left'
                     END as status
              FROM class_attendance ca
              INNER JOIN student s ON ca.student_id = s.student_id
              WHERE ca.class_id = '$class_id'
              ORDER BY ca.join_time DESC";
    $result = mysqli_query($conn, $query);
    if (!$result) {
        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
        return;
    }
    $attendance = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Format duration as H:i:s if available
        $duration = null;
        if ($row['duration']) {
            $seconds = (int)$row['duration'];
            $h = floor($seconds / 3600);
            $m = floor(($seconds % 3600) / 60);
            $s = $seconds % 60;
            $duration = sprintf('%02d:%02d:%02d', $h, $m, $s);
        }
        $attendance[] = [
            'name' => $row['firstname'] . ' ' . $row['lastname'],
            'enrollment_no' => $row['enrollment_no'],
            'join_time' => date('g:i A', strtotime($row['join_time'])),
            'status' => $row['status'],
            'duration' => $duration
        ];
    }
    echo json_encode(['success' => true, 'attendance' => $attendance]);
}

function logExit($data) {
    global $conn;
    
    $class_id = mysqli_real_escape_string($conn, $data['class_id']);
    $user_id = mysqli_real_escape_string($conn, $data['user_id']);
    
    $query = "UPDATE class_attendance 
              SET leave_time = NOW(), 
                  duration = TIMESTAMPDIFF(SECOND, join_time, NOW())
              WHERE class_id = '$class_id' 
              AND student_id = '$user_id' 
              AND leave_time IS NULL";
    
    if (mysqli_query($conn, $query)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    }
}

function exportAttendance($data) {
    global $conn;
    $class_id = mysqli_real_escape_string($conn, $data['class_id']);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance_' . $class_id . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Name', 'Enrollment No', 'Join Time', 'Leave Time', 'Duration (H:i:s)']);
    $query = "SELECT s.firstname, s.lastname, s.student_id as enrollment_no, 
                     ca.join_time, ca.leave_time, ca.duration
              FROM class_attendance ca
              INNER JOIN student s ON ca.student_id = s.student_id
              WHERE ca.class_id = '$class_id'
              ORDER BY ca.join_time";
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $duration = '';
        if ($row['duration']) {
            $seconds = (int)$row['duration'];
            $h = floor($seconds / 3600);
            $m = floor(($seconds % 3600) / 60);
            $s = $seconds % 60;
            $duration = sprintf('%02d:%02d:%02d', $h, $m, $s);
        } else {
            $duration = 'N/A';
        }
        fputcsv($output, [
            $row['firstname'] . ' ' . $row['lastname'],
            $row['enrollment_no'],
            $row['join_time'],
            $row['leave_time'] ?? 'Still in class',
            $duration
        ]);
    }
    fclose($output);
    exit();
}
?>