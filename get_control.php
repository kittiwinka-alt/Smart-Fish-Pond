<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "smart_pond_project";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT system_mode, fan_cmd, light_cmd FROM control_status WHERE id=1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    // พิมพ์ค่าออกมาเป็นข้อความติดกัน (เช่น AUTO,OFF,OFF) เพื่อให้ ESP32 อ่านง่ายๆ
    echo $row['system_mode'] . "," . $row['fan_cmd'] . "," . $row['light_cmd'];
} else {
    echo "NO_DATA";
}

$conn->close();
?>