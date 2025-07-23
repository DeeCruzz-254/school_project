<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require 'vendor/autoload.php';
use Firebase\JWT\JWT;

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "zeraki_analytics";
$secret_key = "your_secret_key_here";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

// Authentication Middleware
function authenticate($role = null) {
    global $secret_key, $conn;
    
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        die(json_encode(["error" => "Authorization header missing"]));
    }
    
    $token = str_replace('Bearer ', '', $headers['Authorization']);
    try {
        $decoded = JWT::decode($token, $secret_key, array('HS256'));
        $user_id = $decoded->user_id;
        
        // Verify user exists and has proper role
        $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            http_response_code(401);
            die(json_encode(["error" => "Invalid user"]));
        }
        
        $user = $result->fetch_assoc();
        if ($role && $user['role'] !== $role) {
            http_response_code(403);
            die(json_encode(["error" => "Insufficient permissions"]));
        }
        
        return $user_id;
    } catch (Exception $e) {
        http_response_code(401);
        die(json_encode(["error" => "Invalid token: " . $e->getMessage()]));
    }
}

// Login Endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['action'] === 'login') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $conn->prepare("SELECT id, password_hash, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $data['username']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(401);
        die(json_encode(["error" => "Invalid credentials"]));
    }
    
    $user = $result->fetch_assoc();
    if (!password_verify($data['password'], $user['password_hash'])) {
        http_response_code(401);
        die(json_encode(["error" => "Invalid credentials"]));
    }
    
    $payload = [
        "user_id" => $user['id'],
        "role" => $user['role'],
        "exp" => time() + 3600 // 1 hour expiration
    ];
    
    $jwt = JWT::encode($payload, $secret_key);
    echo json_encode(["token" => $jwt, "role" => $user['role']]);
    exit;
}

// Get student data (now with subject details)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['action'] === 'get_student') {
    $user_id = authenticate();
    
    $admissionNumber = $_GET['admission_number'] ?? null;
    $studentId = $_GET['student_id'] ?? null;
    
    if (!$admissionNumber && !$studentId) {
        // If no ID provided, get current user's student record
        $stmt = $conn->prepare("SELECT s.id FROM students s WHERE s.user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            http_response_code(404);
            die(json_encode(["error" => "Student not found"]));
        }
        
        $student = $result->fetch_assoc();
        $studentId = $student['id'];
    }
    
    $query = "
        SELECT s.*, sp.*, e.name as exam_name, e.term, e.year, 
               t.target_grade, t.target_score, u.username as target_set_by
        FROM students s
        JOIN student_performance sp ON s.id = sp.student_id
        JOIN exams e ON sp.exam_id = e.id
        LEFT JOIN performance_targets t ON s.id = t.student_id AND e.id = t.exam_id
        LEFT JOIN users u ON t.set_by = u.id
        WHERE " . ($admissionNumber ? "s.admission_number = ?" : "s.id = ?");
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($admissionNumber ? "s" : "i", $admissionNumber ?: $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        die(json_encode(["error" => "Student not found"]));
    }
    
    $student = $result->fetch_assoc();
    
    // Get subjects with performance details
    $stmt = $conn->prepare("
        SELECT sub.code, sub.name, sp.score, sp.grade, sp.position, sp.comment
        FROM student_subjects ss
        JOIN subjects sub ON ss.subject_id = sub.id
        LEFT JOIN subject_performance sp ON ss.subject_id = sp.subject_id 
            AND sp.student_performance_id = ?
        WHERE ss.student_id = ?
    ");
    $stmt->bind_param("ii", $student['id'], $student['student_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $subjects = [];
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
    
    $student['subjects'] = $subjects;
    
    // Get performance history for charts
    $stmt = $conn->prepare("
        SELECT e.name, e.year, e.term, sp.total_points, sp.mean_points, sp.mean_grade
        FROM student_performance sp
        JOIN exams e ON sp.exam_id = e.id
        WHERE sp.student_id = ?
        ORDER BY e.year, e.term
    ");
    $stmt->bind_param("i", $student['student_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $performance_history = [];
    while ($row = $result->fetch_assoc()) {
        $performance_history[] = $row;
    }
    
    $student['performance_history'] = $performance_history;
    
    echo json_encode($student);
    exit;
}

// Update student performance (admin/teacher only)
if ($_SERVER['REQUEST_METHOD'] === 'PUT' && $_GET['action'] === 'update_performance') {
    $user_id = authenticate(['admin', 'teacher']);
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    if (empty($data['student_id']) || empty($data['exam_id'])) {
        http_response_code(400);
        die(json_encode(["error" => "Missing required fields"]));
    }
    
    // Update main performance record
    $stmt = $conn->prepare("
        UPDATE student_performance 
        SET total_points = ?, mean_points = ?, overall_position = ?, stream_position = ?, mean_grade = ?
        WHERE student_id = ? AND exam_id = ?
    ");
    $stmt->bind_param(
        "ddiissi", 
        $data['total_points'],
        $data['mean_points'],
        $data['overall_position'],
        $data['stream_position'],
        $data['mean_grade'],
        $data['student_id'],
        $data['exam_id']
    );
    
    if (!$stmt->execute()) {
        http_response_code(500);
        die(json_encode(["error" => "Failed to update performance"]));
    }
    
    // Update subject performances
    foreach ($data['subjects'] as $subject) {
        $stmt = $conn->prepare("
            INSERT INTO subject_performance 
            (student_performance_id, subject_id, score, grade, position, comment)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            score = VALUES(score), grade = VALUES(grade), position = VALUES(position), comment = VALUES(comment)
        ");
        
        $stmt->bind_param(
            "iidsss",
            $data['performance_id'],
            $subject['subject_id'],
            $subject['score'],
            $subject['grade'],
            $subject['position'],
            $subject['comment']
        );
        
        $stmt->execute();
    }
    
    echo json_encode(["success" => true]);
    exit;
}

// Set performance target (admin/teacher only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['action'] === 'set_target') {
    $user_id = authenticate(['admin', 'teacher']);
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $conn->prepare("
        INSERT INTO performance_targets 
        (student_id, exam_id, target_grade, target_score, set_by)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        target_grade = VALUES(target_grade), target_score = VALUES(target_score), set_by = VALUES(set_by)
    ");
    
    $stmt->bind_param(
        "iisdi",
        $data['student_id'],
        $data['exam_id'],
        $data['target_grade'],
        $data['target_score'],
        $user_id
    );
    
    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Failed to set target"]);
    }
    exit;
}

// Other endpoints would go here...

$conn->close();
?>