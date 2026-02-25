<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "smart_pond_project";

$conn = new mysqli($servername, $username, $password, $dbname);

// รับค่าที่ส่งมาจากหน้าเว็บ
if (isset($_POST['type']) && isset($_POST['value'])) {
    $type = $_POST['type'];
    $value = $_POST['value'];

    if ($type == "mode") {
        $sql = "UPDATE control_status SET system_mode='$value' WHERE id=1";
    } else if ($type == "fan") {
        $sql = "UPDATE control_status SET fan_cmd='$value' WHERE id=1";
    } else if ($type == "light") {
        $sql = "UPDATE control_status SET light_cmd='$value' WHERE id=1";
    }

    if ($conn->query($sql) === TRUE) {
        echo "Success";
    } else {
        echo "Error";
    }
}
$conn->close();
?>