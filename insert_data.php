<?php
// ตั้งรหัสผ่านลับ (API Key)
$api_key_value = "SmartPond_Secret_1234!"; 

// เช็คว่า ESP32 ส่ง API Key มาตรงไหม?
if ($_POST["api_key"] != $api_key_value) {
    die("Error: Invalid API Key. Access Denied!"); // ถ้ากุญแจไม่ตรง ให้เด้งออกเลย
}
// --- 🛡️ ส่วนจัดการ CORS (ด่านตม.) แบบสมบูรณ์ ---
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: *");

// ถ้า Browser ส่งมาถามเฉยๆ (OPTIONS) ให้ตอบ OK แล้วจบการทำงานทันที
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}
// ----------------------------------------------------

// ตั้งค่า Database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "smart_pond_project";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// รับค่า (ตรวจสอบว่ามีข้อมูลส่งมาจริงไหม)
if (isset($_POST['temperature']) && isset($_POST['light_level'])) {
    
    $temp = $_POST['temperature'];
    $light = $_POST['light_level'];
    $fan = isset($_POST['fan_status']) ? $_POST['fan_status'] : "OFF";
    $lamp = isset($_POST['light_status']) ? $_POST['light_status'] : "OFF";

    $sql = "INSERT INTO sensor_log (temperature, light_level, fan_status, light_status)
            VALUES ('$temp', '$light', '$fan', '$lamp')";

    if ($conn->query($sql) === TRUE) {
        echo "New record created successfully";
    } else {
        // ส่ง Error กลับไปให้ ESP32 รู้
        http_response_code(500);
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
} else {
    echo "No data received (Normal for direct browser visit)";
}

$conn->close();
?>