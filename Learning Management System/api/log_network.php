<?php
session_start();
include('../dbcon.php');

header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$class_id = mysqli_real_escape_string($conn, $input['class_id']);
$student_id = mysqli_real_escape_string($conn, $input['student_id']);
$bitrate = floatval($input['bitrate']);
$packet_loss = floatval($input['packet_loss']);

$query = "INSERT INTO network_logs (class_id, student_id, bitrate, packet_loss, logged_at) 
          VALUES ('$class_id', '$student_id', '$bitrate', '$packet_loss', NOW())";

if (mysqli_query($conn, $query)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
}
?>