<?php
include('dbcon.php');
include('session.php');

// Function to send notification to students about class
function sendClassNotification($class_id, $message) {
    global $conn;
    
    // Get all students enrolled in this class
    $query = mysqli_query($conn, 
        "SELECT DISTINCT s.student_id, tc.teacher_class_id
         FROM student s
         INNER JOIN teacher_class_student tcs ON s.student_id = tcs.student_id
         INNER JOIN teacher_class tc ON tcs.teacher_class_id = tc.teacher_class_id
         INNER JOIN subject sub ON tc.subject_id = sub.subject_id
         INNER JOIN online_classes oc ON sub.subject_code = oc.subject_code
         WHERE oc.class_id = '$class_id'") or die(mysqli_error($conn));
    
    $notification_count = 0;
    while ($student = mysqli_fetch_assoc($query)) {
        $student_id = $student['student_id'];
        $teacher_class_id = $student['teacher_class_id'];
        
        // Check if notification already exists to avoid duplicates
        $check_query = mysqli_query($conn, 
            "SELECT notification_id FROM notification 
             WHERE teacher_class_id = '$teacher_class_id' 
             AND notification = '$message'
             AND DATE(date_of_notification) = CURDATE()") or die(mysqli_error($conn));
        
        if (mysqli_num_rows($check_query) == 0) {
            // Insert notification for this student
            mysqli_query($conn, 
                "INSERT INTO notification (teacher_class_id, notification, date_of_notification, link) 
                 VALUES ('$teacher_class_id', '$message', NOW(), 'join_class.php?class_id=$class_id')") 
                 or die(mysqli_error($conn));
            $notification_count++;
        }
    }
    
    return $notification_count;
}

// Function to get notifications for a student
function getStudentNotifications($student_id) {
    global $conn;
    
    $query = mysqli_query($conn, 
        "SELECT DISTINCT n.* 
         FROM notification n
         INNER JOIN teacher_class tc ON n.teacher_class_id = tc.teacher_class_id
         INNER JOIN teacher_class_student tcs ON tc.teacher_class_id = tcs.teacher_class_id
         WHERE tcs.student_id = '$student_id'
         ORDER BY n.date_of_notification DESC") or die(mysqli_error($conn));
    
    $notifications = [];
    while ($row = mysqli_fetch_assoc($query)) {
        $notifications[] = $row;
    }
    
    return $notifications;
}

// Function to mark notification as read
function markNotificationAsRead($notification_id, $student_id) {
    global $conn;
    
    mysqli_query($conn, 
        "INSERT INTO notification_read (student_id, student_read, notification_id) 
         VALUES ('$student_id', 'yes', '$notification_id') 
         ON DUPLICATE KEY UPDATE student_read = 'yes'") or die(mysqli_error($conn));
    
    return true;
}
?>