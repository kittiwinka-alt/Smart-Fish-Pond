#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h> 
#include <OneWire.h>
#include <DallasTemperature.h>
#include <WiFiManager.h>      

// ================= ตั้งค่า Server และ Telegram =================
const char* serverName = "http://192.168.2.8/smart_pond_project/insert_data.php"; 
const char* controlName = "http://192.168.2.8/smart_pond_project/get_control.php"; // ลิงก์สำหรับรับคำสั่งจากเว็บ

String botToken = "8272809024:AAHYJep2KAHoabfwcubxBhyZWz7ecGBhLOM"; 
String chatId = "7631447998";   

// ================= กำหนดขาอุปกรณ์ =================
#define LDR_PIN 34
#define ONE_WIRE_BUS 4
#define FAN_PIN 18
#define LIGHT_PIN 19
#define RELAY_ON HIGH
#define RELAY_OFF LOW
#define LED_WIFI 13
#define LED_ALERT 15
#define LED_SEND 5
#define RESET_WIFI_PIN 0 

// ================= ตั้งค่าตัวแปร =================
OneWire oneWire(ONE_WIRE_BUS);
DallasTemperature sensors(&oneWire);

unsigned long lastReconnectAttempt = 0; 

unsigned long lastTime = 0;
unsigned long timerDelay = 10000; // ส่งข้อมูลขึ้นเว็บทุก 10 วิ

unsigned long lastControlTime = 0;
unsigned long controlDelay = 3000; // ไปเช็คคำสั่งจากเว็บทุก 3 วิ (เพื่อให้ปุ่มกดตอบสนองไว)

unsigned long lightTimer = 0;
bool isHotReported = false; 
bool isLightReported = false;

// ตัวแปรสำหรับโหมด Manual/Auto
String systemMode = "AUTO";
String manualFan = "OFF";
String manualLight = "OFF";

// ================= ฟังก์ชันส่ง Telegram =================
void sendTelegram(String message) {
  WiFiClientSecure client;
  client.setInsecure(); 
  HTTPClient http;
  String url = "https://api.telegram.org/bot" + botToken + "/sendMessage?chat_id=" + chatId + "&text=" + message;
  http.begin(client, url);
  int httpResponseCode = http.GET(); 
  if (httpResponseCode > 0) {
    Serial.print("Telegram sent! HTTP Code: "); Serial.println(httpResponseCode);
  } else {
    Serial.println("Error sending Telegram message");
  }
  http.end();
}

void setup() {
  Serial.begin(115200);
  pinMode(RESET_WIFI_PIN, INPUT_PULLUP);
  pinMode(FAN_PIN, OUTPUT);
  pinMode(LIGHT_PIN, OUTPUT);
  pinMode(LED_WIFI, OUTPUT);
  pinMode(LED_ALERT, OUTPUT);
  pinMode(LED_SEND, OUTPUT);
  
  digitalWrite(FAN_PIN, RELAY_OFF);
  digitalWrite(LIGHT_PIN, RELAY_OFF);

  sensors.begin();

  WiFiManager wm;
  Serial.println("Starting WiFiManager...");
  digitalWrite(LED_WIFI, LOW); 

  bool res = wm.autoConnect("SmartPond_Setup", "12345678"); 
  if(!res) {
    Serial.println("Failed to connect");
    ESP.restart(); 
  } else {
    Serial.println("\nWiFi Connected!");
    WiFi.mode(WIFI_STA);
    digitalWrite(LED_WIFI, HIGH); 
    sendTelegram("🐟 บ่อปลาอัจฉริยะ: ระบบพร้อมทำงานและเชื่อมต่อ WiFi สำเร็จแล้ว!");
  }
}

void loop() {
  // ================= 0. เช็คสถานะ WiFi แบบ Real-time =================
 if (WiFi.status() == WL_CONNECTED) {
    digitalWrite(LED_WIFI, HIGH); // เน็ตมา -> ไฟติดค้าง
  } else {
    // โหมดกระพริบไฟเมื่อเน็ตหลุด (ให้รู้ว่ากำลังพยายามต่อใหม่)
    digitalWrite(LED_WIFI, (millis() / 500) % 2); 

    // สั่งให้กระตุ้นการเชื่อมต่อใหม่ ทุกๆ 10 วินาที
    if (millis() - lastReconnectAttempt > 10000) {
      Serial.println("WiFi connection lost. Trying to reconnect...");
      WiFi.disconnect(); // เคลียร์การเชื่อมต่อเก่าที่ค้างเอ๋อๆ ทิ้งก่อน
      WiFi.reconnect();  // บังคับให้พยายามเชื่อมต่อเน็ตบ้านใหม่อีกครั้ง
      lastReconnectAttempt = millis();
    }
  }

  // ================= 1. ไปเช็คคำสั่งจากหน้าเว็บ (ทุก 3 วินาที) =================
  if ((millis() - lastControlTime) > controlDelay) {
    if(WiFi.status() == WL_CONNECTED){
      HTTPClient http;
      http.begin(controlName);
      int httpCode = http.GET();
      if (httpCode > 0) {
        String payload = http.getString(); // ข้อมูลที่ได้จะเป็นก้อน เช่น "AUTO,OFF,ON"
        
        // แยกข้อความออกจากกัน
        int firstComma = payload.indexOf(',');
        int secondComma = payload.indexOf(',', firstComma + 1);
        
        if (firstComma > 0 && secondComma > 0) {
          systemMode = payload.substring(0, firstComma);
          manualFan = payload.substring(firstComma + 1, secondComma);
          manualLight = payload.substring(secondComma + 1);
        }
      }
      http.end();
    }
    lastControlTime = millis();
  }

  // ================= 2. อ่านค่าเซนเซอร์ =================
  sensors.requestTemperatures(); 
  float temperatureC = sensors.getTempCByIndex(0);
  int lightValue = analogRead(LDR_PIN); 
  if (temperatureC == -127.00) temperatureC = 0.0; // กันค่า Error 

  // ================= 3. ระบบควบคุม (แยก AUTO กับ MANUAL) =================
  if (systemMode == "AUTO") {
    // ----------------- โหมดอัตโนมัติ (เซนเซอร์ควบคุม) -----------------
    
    // พัดลม Auto
    if (temperatureC > 32.0) {
      digitalWrite(FAN_PIN, RELAY_ON);   
      digitalWrite(LED_ALERT, HIGH);     
      if (!isHotReported) {
        sendTelegram("⚠️ แจ้งเตือน: อุณหภูมิน้ำสูงเกินกำหนด (" + String(temperatureC) + " °C) ระบบกำลังเปิดพัดลมระบายความร้อน 🌪️");
        isHotReported = true; 
      }
    } 
    else if (temperatureC > 0 && temperatureC < 30.0) {
      digitalWrite(FAN_PIN, RELAY_OFF);  
      digitalWrite(LED_ALERT, LOW); 
      if (isHotReported) {
        sendTelegram("✅ อัปเดต: อุณหภูมิน้ำกลับสู่สภาวะปกติ (" + String(temperatureC) + " °C) ระบบปิดพัดลมแล้ว");
        isHotReported = false; 
      }
    }

    // ไฟ Auto
    if (lightValue > 2500) { 
      if (millis() - lightTimer > 3000) { 
        digitalWrite(LIGHT_PIN, RELAY_ON); 
        if (!isLightReported) {
          sendTelegram("💡 อัปเดต: บรรยากาศมืดลง ระบบกำลังเปิดไฟบ่อปลาครับ");
          isLightReported = true; 
        }
      }
    } 
    else if (lightValue < 1500) {
      digitalWrite(LIGHT_PIN, RELAY_OFF); 
      lightTimer = millis(); 
      if (isLightReported) {
        sendTelegram("☀️ อัปเดต: แสงสว่างเพียงพอ ระบบปิดไฟบ่อปลาแล้วครับ");
        isLightReported = false; 
      }
    } else {
      lightTimer = millis();
    }
    
  } else {
    // ----------------- โหมด MANUAL (หน้าเว็บควบคุม) -----------------
    // ปิดไฟเหลืองแจ้งเตือนทิ้งไปก่อน
    digitalWrite(LED_ALERT, LOW);
    
    // ควบคุมพัดลม
    if (manualFan == "ON") {
      digitalWrite(FAN_PIN, RELAY_ON);
    } else {
      digitalWrite(FAN_PIN, RELAY_OFF);
    }

    // ควบคุมไฟส่องสว่าง
    if (manualLight == "ON") {
      digitalWrite(LIGHT_PIN, RELAY_ON);
    } else {
      digitalWrite(LIGHT_PIN, RELAY_OFF);
    }
    
    // รีเซ็ตการแจ้งเตือน Telegram (เพื่อให้พอกลับไปโหมด Auto มันจะได้เตือนใหม่ได้)
    isHotReported = false;
    isLightReported = false;
  }

  // ================= 4. ส่งข้อมูลเข้า Database =================
  if ((millis() - lastTime) > timerDelay) {
    if(WiFi.status() == WL_CONNECTED){
      HTTPClient http;
      digitalWrite(LED_SEND, HIGH); 
      http.begin(serverName);
      http.addHeader("Content-Type", "application/x-www-form-urlencoded");
      
      String fanStatus = (digitalRead(FAN_PIN) == RELAY_ON) ? "ON" : "OFF";
      String lightStatus = (digitalRead(LIGHT_PIN) == RELAY_ON) ? "ON" : "OFF";
      
      String httpRequestData = "api_key=SmartPond_Secret_1234!&temperature=" + String(temperatureC)
                             + "&light_level=" + String(lightValue)
                             + "&fan_status=" + fanStatus
                             + "&light_status=" + lightStatus;
           
      int httpResponseCode = http.POST(httpRequestData);
      http.end();
      digitalWrite(LED_SEND, LOW); 
    }
    lastTime = millis();
  }

  // ================= 5. เช็คการกดปุ่ม BOOT เพื่อรีเซ็ต WiFi =================
  if (digitalRead(RESET_WIFI_PIN) == LOW) { 
    delay(3000); 
    if (digitalRead(RESET_WIFI_PIN) == LOW) { 
      sendTelegram("🔄 สั่งรีเซ็ต WiFi เรียบร้อยแล้ว! กรุณาตั้งค่าเครือข่ายใหม่"); 
      delay(1000); 
      WiFiManager wm;
      wm.resetSettings();
      ESP.restart(); 
    }
  }
}