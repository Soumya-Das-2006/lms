<?php
session_start();
include('../dbcon.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

switch ($action) {
    case 'end_class':
        endClass($input);
        break;
    case 'get_status':
        getClassStatus($input);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function endClass($data) {
    global $conn;
    
    $class_id = mysqli_real_escape_string($conn, $data['class_id']);
    
    $query = "UPDATE online_classes 
              SET status = 'completed', end_time = NOW() 
              WHERE class_id = '$class_id'";
    
    if (mysqli_query($conn, $query)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    }
}

function getClassStatus($data) {
    global $conn;
    
    $class_id = mysqli_real_escape_string($conn, $data['class_id']);
    
    $query = "SELECT status FROM online_classes WHERE class_id = '$class_id'";
    $result = mysqli_query($conn, $query);
    
    if ($row = mysqli_fetch_assoc($result)) {
        echo json_encode(['success' => true, 'status' => $row['status']]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Class not found']);
    }
}
?>