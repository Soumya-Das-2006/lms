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
    case 'update_controls':
        updateControls($input);
        break;
    case 'get_controls':
        getControls($input);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function updateControls($data) {
    global $conn;
    
    $class_id = mysqli_real_escape_string($conn, $data['class_id']);
    $teacher_id = mysqli_real_escape_string($conn, $data['teacher_id']);
    
    // Check if controls exist
    $check_query = "SELECT * FROM class_controls WHERE class_id = '$class_id'";
    $check_result = mysqli_query($conn, $check_query);
    
    $update_fields = [];
    foreach ($data as $key => $value) {
        if (strpos($key, 'enable_') === 0) {
            $update_fields[$key] = $value;
        }
    }
    
    if (mysqli_num_rows($check_result)) {
        // Update existing
        $set_parts = [];
        foreach ($update_fields as $field => $value) {
            $set_parts[] = "$field = '$value'";
        }
        $set_clause = implode(', ', $set_parts);
        
        $query = "UPDATE class_controls SET $set_clause WHERE class_id = '$class_id'";
    } else {
        // Insert new
        $fields = ['class_id', 'teacher_id'];
        $values = ["'$class_id'", "'$teacher_id'"];
        
        foreach ($update_fields as $field => $value) {
            $fields[] = $field;
            $values[] = "'$value'";
        }
        
        $query = "INSERT INTO class_controls (" . implode(', ', $fields) . ") 
                  VALUES (" . implode(', ', $values) . ")";
    }
    
    if (mysqli_query($conn, $query)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    }
}

function getControls($data) {
    global $conn;
    
    $class_id = mysqli_real_escape_string($conn, $data['class_id']);
    
    $query = "SELECT * FROM class_controls WHERE class_id = '$class_id'";
    $result = mysqli_query($conn, $query);
    
    if ($row = mysqli_fetch_assoc($result)) {
        echo json_encode(['success' => true, 'controls' => $row]);
    } else {
        // Return default values
        echo json_encode([
            'success' => true,
            'controls' => [
                'enable_chat' => 1,
                'enable_polls' => 1,
                'enable_raise_hand' => 1,
                'enable_whiteboard' => 1
            ]
        ]);
    }
}
?>