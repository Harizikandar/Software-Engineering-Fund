<?php

session_start();
require "config.php";

header("Content-Type: application/json");

if(!isset($_SESSION["cust_id"])){

    echo json_encode([
        "success"=>false,
        "message"=>"Please login first."
    ]);

    exit();

}

$data=json_decode(file_get_contents("php://input"),true);

if(!$data){

    echo json_encode([
        "success"=>false,
        "message"=>"Invalid request."
    ]);

    exit();

}

$rating=intval($data["rating"]);
$comment=trim($data["comment"]);
$vendor_id=intval($data["vendor_id"]);
$cust_id=$_SESSION["cust_id"];

$stmt=$conn->prepare("

INSERT INTO reviews
(rating,comment,review_status,cust_id,vendor_id)
VALUES
(?,?, 'Pending', ?,?)

");

$stmt->bind_param(

"isii",

$rating,
$comment,
$cust_id,
$vendor_id

);

if($stmt->execute()){

    echo json_encode([
        "success"=>true
    ]);

}else{

    echo json_encode([
        "success"=>false,
        "message"=>$conn->error
    ]);

}

$stmt->close();
$conn->close();

?>