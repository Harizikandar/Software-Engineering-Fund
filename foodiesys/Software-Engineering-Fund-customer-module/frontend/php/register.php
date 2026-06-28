<?php

require "config.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    // Check if email already exists
    $check = $conn->prepare("SELECT * FROM customers WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {

        echo "<script>
                alert('Email already exists!');
                window.location='../html/register.html';
              </script>";
        exit();

    }

    // Encrypt password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert customer
    $stmt = $conn->prepare("INSERT INTO customers(name, email, password) VALUES(?,?,?)");
    $stmt->bind_param("sss", $name, $email, $hashedPassword);

    if ($stmt->execute()) {

        echo "<script>
                alert('Registration Successful!');
                window.location='../html/login.html';
              </script>";

    } else {

        echo "<script>
                alert('Registration Failed!');
                window.location='../html/register.html';
              </script>";

    }

    $stmt->close();
    $check->close();

}

$conn->close();

?>