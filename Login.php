<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login System</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; }
        .login-container { width: 300px; margin: 100px auto; padding: 20px; background: #fff; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        input[type="text"], input[type="password"] { width: 100%; padding: 10px; margin: 8px 0; display: inline-block; border: 1px solid #ccc; box-sizing: border-box; }
        button { background-color: #4CAF50; color: white; padding: 14px 20px; margin: 8px 0; border: none; cursor: pointer; width: 100%; }
        button:hover { opacity: 0.8; }
        .error { color: red; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Login</h2>
        <form action="login.php" method="post">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required>

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Login</button>
        </form>
        <?php if (isset($_GET['error'])) { ?>
            <p class="error"><?php echo htmlspecialchars($_GET['error']); ?></p>
        <?php } ?>
    </div>
</body>
</html>
    $role = $_GET['role'] ?? null;

    try {
        $user_id = authenticate($role);
        
        $stmt = $conn->prepare("SELECT s.id, s.name, s.email, GROUP_CONCAT(DISTINCT sub.name SEPARATOR ', ') AS subjects 
                                FROM students s 
                                LEFT JOIN student_subjects ss ON s.id = ss.student_id 
                                LEFT JOIN subjects sub ON ss.subject_id = sub.id 
                                WHERE s.id = ? 
                                GROUP BY s.id");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            http_response_code(404);
            die(json_encode(["error" => "Student not found"]));
        }
        
        $student = $result->fetch_assoc();
        echo json_encode($student);
    } catch (Exception $e) {
        http_response_code(500);
        die(json_encode(["error" => "Server error: " . $e->getMessage()]));
    }
}


<?php
$servername = "localhost";
$username = "username";
$password = "password";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
echo "Connected successfully";
?><?php
echo "PHP is working!";