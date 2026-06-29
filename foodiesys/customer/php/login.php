<?php

require "session.php";
require "config.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    $stmt = $conn->prepare("SELECT * FROM customers WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows == 1) {

        $customer = $result->fetch_assoc();

        if (password_verify($password, $customer["password"])) {

            session_regenerate_id(true);
            $_SESSION["cust_id"] = $customer["cust_id"];
            $_SESSION["name"] = $customer["name"];

            header("Location: ../html/vendors.html");
            exit();

        } else {

            echo "<script>
                    alert('Wrong Password!');
                    window.location='../html/login.html';
                  </script>";

        }

    } else {

        echo "<script>
                alert('Email does not exist!');
                window.location='../html/login.html';
              </script>";

    }

    $stmt->close();
}

$conn->close();

?>