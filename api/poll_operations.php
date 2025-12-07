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
    case 'create':
        createPoll($input);
        break;
    case 'get_active':
        getActivePoll($input);
        break;
    case 'vote':
        votePoll($input);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function createPoll($data) {
    global $conn;
    
    $class_id = mysqli_real_escape_string($conn, $data['class_id']);
    $teacher_id = mysqli_real_escape_string($conn, $data['teacher_id']);
    $question = mysqli_real_escape_string($conn, $data['question']);
    $options = $data['options'];
    
    // Close any existing active polls
    $close_query = "UPDATE polls SET status = 'closed' WHERE class_id = '$class_id' AND status = 'active'";
    mysqli_query($conn, $close_query);
    
    // Insert new poll
    $query = "INSERT INTO polls (class_id, teacher_id, question, status, created_at) 
              VALUES ('$class_id', '$teacher_id', '$question', 'active', NOW())";
    
    if (mysqli_query($conn, $query)) {
        $poll_id = mysqli_insert_id($conn);
        
        // Insert options
        foreach ($options as $index => $option) {
            $option_text = mysqli_real_escape_string($conn, $option);
            $option_query = "INSERT INTO poll_options (poll_id, option_index, option_text) 
                            VALUES ('$poll_id', '$index', '$option_text')";
            mysqli_query($conn, $option_query);
        }
        
        echo json_encode(['success' => true, 'poll_id' => $poll_id]);
    } else {
        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    }
}

function getActivePoll($data) {
    global $conn;
    
    $class_id = mysqli_real_escape_string($conn, $data['class_id']);
    $student_id = isset($data['student_id']) ? mysqli_real_escape_string($conn, $data['student_id']) : null;
    
    $query = "SELECT * FROM polls 
              WHERE class_id = '$class_id' 
              AND status = 'active' 
              ORDER BY created_at DESC 
              LIMIT 1";
    
    $result = mysqli_query($conn, $query);
    
    if ($row = mysqli_fetch_assoc($result)) {
        $poll_id = $row['poll_id'];
        
        // Get options
        $options_query = "SELECT * FROM poll_options WHERE poll_id = '$poll_id' ORDER BY option_index";
        $options_result = mysqli_query($conn, $options_query);
        
        $options = [];
        while ($option = mysqli_fetch_assoc($options_result)) {
            $options[] = $option['option_text'];
        }
        
        // Check if student has voted
        $voted = false;
        $results = null;
        
        if ($student_id) {
            $vote_check = "SELECT * FROM poll_votes WHERE poll_id = '$poll_id' AND student_id = '$student_id'";
            $vote_result = mysqli_query($conn, $vote_check);
            $voted = mysqli_num_rows($vote_result) > 0;
            
            // Get poll results if voted
            if ($voted) {
                $results_query = "SELECT option_index, COUNT(*) as count FROM poll_votes 
                                 WHERE poll_id = '$poll_id' GROUP BY option_index";
                $results_result = mysqli_query($conn, $results_query);
                
                $results = array_fill(0, count($options), 0);
                while ($result_row = mysqli_fetch_assoc($results_result)) {
                    $results[$result_row['option_index']] = $result_row['count'];
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'poll' => [
                'poll_id' => $poll_id,
                'question' => $row['question'],
                'options' => $options,
                'voted' => $voted,
                'results' => $results
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No active poll']);
    }
}

function votePoll($data) {
    global $conn;
    
    $poll_id = mysqli_real_escape_string($conn, $data['poll_id']);
    $student_id = mysqli_real_escape_string($conn, $data['student_id']);
    $option_index = mysqli_real_escape_string($conn, $data['option_index']);
    
    // Check if already voted
    $check_query = "SELECT * FROM poll_votes 
                    WHERE poll_id = '$poll_id' AND student_id = '$student_id'";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        echo json_encode(['success' => false, 'error' => 'Already voted']);
        return;
    }
    
    $query = "INSERT INTO poll_votes (poll_id, student_id, option_index, voted_at) 
              VALUES ('$poll_id', '$student_id', '$option_index', NOW())";
    
    if (mysqli_query($conn, $query)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    }
}
?>