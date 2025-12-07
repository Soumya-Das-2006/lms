<?php
session_start();
include('dbcon.php');

if (!isset($_SESSION['id']) || $_SESSION['user_type'] != 'teacher') {
    die('Access denied');
}

if (isset($_FILES['recording'])) {
    $class_id = $_POST['class_id'];
    $teacher_id = $_POST['teacher_id'];
    
    // Create recordings directory if it doesn't exist
    $upload_dir = 'recordings/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $filename = 'class_' . $class_id . '_' . date('Y-m-d_H-i-s') . '.webm';
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($_FILES['recording']['tmp_name'], $filepath)) {
        // Get file size
        $file_size = filesize($filepath);
        
        // Save to database
        $query = "INSERT INTO class_recordings (class_id, teacher_id, filename, filepath, file_size, created_at) 
                  VALUES ('$class_id', '$teacher_id', '$filename', '$filepath', '$file_size', NOW())";
        
        if (mysqli_query($conn, $query)) {
            echo 'Recording saved successfully';
        } else {
            echo 'Error saving recording to database: ' . mysqli_error($conn);
        }
    } else {
        echo 'Error moving uploaded file';
    }
} else {
    echo 'No recording received';
}
?>