<?php
session_start();

// ข้อมูลการเชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "isanfood";

// สร้างการเชื่อมต่อ
$condb = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($condb->connect_error) {
    die("Connection failed: " . $condb->connect_error);
}
