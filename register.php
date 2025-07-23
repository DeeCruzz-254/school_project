<?php
// hash the password using Flask API
function hashPasswordWithAPI($password) {
    $url = 'http://127.0.0.1:5000/hash';
    $data = json_encode(['password' => $password]);

    $options = [
        'http' => [
            'header'  => "Content-type: application/json",
            'method'  => 'POST',
            'content' => $data,
        ]
    ];

    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    if ($result === FALSE) {
        die("Error contacting the hash API");
    }

    $response = json_decode($result, true);
    return $response['hashed_password'] ?? null;
}

// handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $hashed = hashPasswordWithAPI($password);

    // Connect to MySQL (change db config as needed)
    $conn = new mysqli("localhost", "root", "", "Users");

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Insert user
    $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $username, $hashed);

    if ($stmt->execute()) {
        echo "User registered successfully!";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
    $conn = new mysqli("localhost", "root", "", "school_app");

if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}
echo "Connected to DB!";

}
?>
