<?php
session_start();
include('../dbcon.php');

header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

switch ($action) {
    case 'raise_hand':
        raiseHand($input);
        break;
    case 'lower_hand':
        lowerHand($input);
        break;
    case 'get_raised_hands':
        getRaisedHands($input);
        break;
    case 'clear_hand':
        clearHand($input);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function raiseHand($data) {
    global $conn;
    
    $class_id = mysqli_real_escape_string($conn, $data['class_id']);
    $student_id = mysqli_real_escape_string($conn, $data['student_id']);
    $student_name = mysqli_real_escape_string($conn, $data['student_name']);
    
    // Check if already raised
    $check_query = "SELECT * FROM raised_hands 
                    WHERE class_id = '$class_id' 
                    AND student_id = '$student_id' 
                    AND cleared_at IS NULL";

    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        echo json_encode(['success' => false, 'error' => 'Hand already raised']);
        return;
    }
    
    $query = "INSERT INTO raised_hands (class_id, student_id, student_name, raised_at) 
              VALUES ('$class_id', '$student_id', '$student_name', NOW())";
    
    if (mysqli_query($conn, $query)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    }
}

function lowerHand($data) {
    global $conn;
    
    $class_id = mysqli_real_escape_string($conn, $data['class_id']);
    $student_id = mysqli_real_escape_string($conn, $data['student_id']);
    
    $query = "UPDATE raised_hands 
              SET cleared_at = NOW() 
              WHERE class_id = '$class_id' 
              AND student_id = '$student_id' 
              AND cleared_at IS NULL";
    
    if (mysqli_query($conn, $query)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    }
}

function getRaisedHands($data) {
    global $conn;
    
    $class_id = mysqli_real_escape_string($conn, $data['class_id']);
    
    $query = "SELECT * FROM raised_hands 
              WHERE class_id = '$class_id' 
              AND cleared_at IS NULL 
              ORDER BY raised_at";
    
    $result = mysqli_query($conn, $query);
    $raised_hands = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $raised_hands[] = [
            'event_id' => $row['event_id'],
            'student_name' => $row['student_name'],
            'raised_at' => $row['raised_at']
        ];
    }
    
    echo json_encode(['success' => true, 'raised_hands' => $raised_hands]);
}

function clearHand($data) {
    global $conn;
    
    $event_id = mysqli_real_escape_string($conn, $data['event_id']);
    
    $query = "UPDATE raised_hands 
              SET cleared_at = NOW() 
              WHERE event_id = '$event_id'";
    
    if (mysqli_query($conn, $query)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    }
}
?>