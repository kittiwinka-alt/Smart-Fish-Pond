<?php
// ตั้งค่าการเชื่อมต่อ Database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "smart_pond_project"; 
$tablename = "sensor_log";      

// เชื่อมต่อ
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// 1. ดึงข้อมูลล่าสุด 1 แถว (เอามาโชว์สถานะปัจจุบัน)
$sql_latest = "SELECT * FROM $tablename ORDER BY id DESC LIMIT 1";
$result_latest = $conn->query($sql_latest);
$latest = $result_latest->fetch_assoc();

// 2. ดึงข้อมูลสถานะปุ่มกด (จากตาราง control_status ที่เราเพิ่งสร้าง)
$sql_control = "SELECT * FROM control_status WHERE id=1";
$result_control = $conn->query($sql_control);
$control = $result_control->fetch_assoc();
$is_auto = ($control['system_mode'] == 'AUTO');

// 3. ดึงข้อมูลย้อนหลัง 20 แถว (เอามาวาดกราฟ)
$sql_chart = "SELECT * FROM $tablename ORDER BY id DESC LIMIT 20";
$result_chart = $conn->query($sql_chart);

// เตรียมตัวแปรสำหรับกราฟ JS
$times = [];
$temps = [];
while($row = $result_chart->fetch_assoc()) {
    array_unshift($times, date("H:i:s", strtotime($row['created_at'])));
    array_unshift($temps, $row['temperature']);
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="10"> <title>Smart Fish Pond Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f0f2f5; font-family: 'Sarabun', sans-serif; }
        .card { border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .status-box { padding: 20px; text-align: center; color: white; border-radius: 10px; }
        .bg-temp { background: linear-gradient(45deg, #ff6b6b, #ff8e8e); }
        .bg-fan { background: linear-gradient(45deg, #4facfe, #00f2fe); }
        .bg-light { background: linear-gradient(45deg, #fa709a, #fee140); }
        .big-text { font-size: 2.5em; font-weight: bold; }
        .control-panel { background-color: #fff; padding: 20px; border-radius: 15px; text-align: center;}
    </style>
</head>
<body>

<div class="container py-4">
    <h1 class="text-center mb-4">🐟 แดชบอร์ดบ่อปลาอัจฉริยะ (Smart Pond)</h1>
    
    <div class="row">
        <div class="col-md-4">
            <div class="card status-box bg-temp">
                <h3>อุณหภูมิน้ำ</h3>
                <div class="big-text"><?php echo number_format($latest['temperature'] ?? 0, 2); ?> °C</div>
                <p>ล่าสุด: <?php echo $latest['created_at'] ?? '-'; ?></p>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card status-box bg-fan">
                <h3>พัดลมระบายความร้อน</h3>
                <div class="big-text"><?php echo $latest['fan_status'] ?? '-'; ?></div>
                <p>สถานะการทำงาน</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card status-box bg-light">
                <h3>ไฟส่องสว่าง</h3>
                <div class="big-text"><?php echo $latest['light_status'] ?? '-'; ?></div>
                <p>ความเข้มแสง: <?php echo $latest['light_level'] ?? 0; ?></p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card control-panel border-primary">
                <h4 class="mb-3">🎛️ แผงควบคุมระบบ (Control Panel)</h4>
                <div class="d-flex justify-content-around flex-wrap">
                    
                    <div class="mb-3">
                        <h5 class="text-muted">โหมดการทำงาน</h5>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn <?php echo $is_auto ? 'btn-primary' : 'btn-outline-primary'; ?>" 
                                    onclick="sendCommand('mode', 'AUTO')">ระบบอัตโนมัติ (AUTO)</button>
                            <button type="button" class="btn <?php echo !$is_auto ? 'btn-danger' : 'btn-outline-danger'; ?>" 
                                    onclick="sendCommand('mode', 'MANUAL')">ควบคุมเอง (MANUAL)</button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <h5 class="text-muted">พัดลมระบายความร้อน</h5>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn <?php echo ($control['fan_cmd'] == 'ON' && !$is_auto) ? 'btn-success' : 'btn-outline-success'; ?>" 
                                    onclick="sendCommand('fan', 'ON')" <?php if($is_auto) echo 'disabled'; ?>>เปิด (ON)</button>
                            <button type="button" class="btn <?php echo ($control['fan_cmd'] == 'OFF' && !$is_auto) ? 'btn-secondary' : 'btn-outline-secondary'; ?>" 
                                    onclick="sendCommand('fan', 'OFF')" <?php if($is_auto) echo 'disabled'; ?>>ปิด (OFF)</button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <h5 class="text-muted">ไฟส่องสว่างบ่อปลา</h5>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn <?php echo ($control['light_cmd'] == 'ON' && !$is_auto) ? 'btn-warning' : 'btn-outline-warning'; ?>" 
                                    onclick="sendCommand('light', 'ON')" <?php if($is_auto) echo 'disabled'; ?>>เปิด (ON)</button>
                            <button type="button" class="btn <?php echo ($control['light_cmd'] == 'OFF' && !$is_auto) ? 'btn-secondary' : 'btn-outline-secondary'; ?>" 
                                    onclick="sendCommand('light', 'OFF')" <?php if($is_auto) echo 'disabled'; ?>>ปิด (OFF)</button>
                        </div>
                    </div>

                </div>
                <?php if($is_auto): ?>
                    <small class="text-danger mt-2 d-block">* ขณะนี้อยู่ในโหมด AUTO ระบบจะล็อคปุ่มกดไว้ เพื่อให้เซนเซอร์ควบคุมเอง</small>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row mt-2">
        <div class="col-12">
            <div class="card p-4">
                <h4>📈 กราฟอุณหภูมิย้อนหลัง</h4>
                <canvas id="tempChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
    // ฟังก์ชันส่งคำสั่งเวลาเรากดปุ่ม
    function sendCommand(type, value) {
        fetch('update_control.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `type=${type}&value=${value}`
        })
        .then(response => response.text())
        .then(data => {
            if(data.trim() === 'Success') {
                location.reload(); // โหลดหน้าเว็บใหม่เพื่อให้ปุ่มเปลี่ยนสี
            } else {
                alert('เกิดข้อผิดพลาดในการส่งคำสั่ง!');
            }
        });
    }

    // โค้ดสร้างกราฟ (เหมือนเดิม)
    const ctx = document.getElementById('tempChart').getContext('2d');
    const myChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($times); ?>,
            datasets: [{
                label: 'อุณหภูมิ (°C)',
                data: <?php echo json_encode($temps); ?>,
                borderColor: '#ff6b6b',
                backgroundColor: 'rgba(255, 107, 107, 0.2)',
                borderWidth: 3,
                fill: true,
                tension: 0.4 
            }]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: false } }
        }
    });
</script>

</body>
</html>