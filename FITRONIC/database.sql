DROP DATABASE IF EXISTS hardware_hub;
CREATE DATABASE hardware_hub;
USE hardware_hub;


CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('student', 'instructor') DEFAULT 'student'
);


CREATE TABLE forum_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    category VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE forum_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    user_id INT NOT NULL,
    answer_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES forum_questions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);


CREATE TABLE marketplace_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    category VARCHAR(100) DEFAULT 'Other',
    description TEXT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    highest_bid DECIMAL(10,2) DEFAULT 0.00,
    highest_bidder_email VARCHAR(100) DEFAULT NULL,
    image_path VARCHAR(255) DEFAULT 'uploads/default.jpg',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE project_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_no VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    stage VARCHAR(100) NOT NULL,
    progress INT DEFAULT 0,
    image_theme VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    members TEXT NOT NULL,
    components TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- 5. LAB RESERVATIONS TABLE
-- ==========================================
CREATE TABLE lab_reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room VARCHAR(100) NOT NULL,
    table_number INT NOT NULL,
    reserve_date DATE NOT NULL,
    time_slot VARCHAR(50) NOT NULL,
    student_name VARCHAR(100) NOT NULL,
    group_no VARCHAR(50) NOT NULL,
    purpose TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


INSERT INTO users (full_name, email, role) VALUES 
('Kasun Perera', 'kasun@uom.lk', 'student'),
('Dr. Nimal Silva', 'nimal@uom.lk', 'instructor');


INSERT INTO forum_questions (user_id, title, category, description) VALUES 
(1, 'My stepper motor is vibrating but not rotating with DRV8825', 'Motors & Drivers', 'I connected a NEMA 17 stepper motor to an Arduino Mega using a DRV8825 driver and 12V supply, but it only hums and vibrates in place. What can I do?');

INSERT INTO forum_answers (question_id, user_id, answer_text) VALUES 
(1, 2, 'Check your coil wiring pairs using a multimeter—two coil wires are likely swapped. Also adjust the Vref potentiometer on the DRV8825 so the current limit matches your motor.');


INSERT INTO marketplace_items (user_id, item_name, category, description, price, image_path) VALUES 
(1, 'Arduino Mega 2560', 'Microcontrollers', 'Slightly used, works perfectly for robotics.', 4500.00, 'uploads/default.jpg'),
(2, 'DRV8825 Stepper Driver', 'Motors & Actuators', 'Extra driver from a marble clock project.', 600.00, 'uploads/default.jpg');


INSERT INTO project_groups (group_no, title, category, stage, progress, image_theme, description, members, components) VALUES 
('Group No. 01', 'Autonomous Indoor Smart Home Assistant Robot', 'Robotics & Automation', 'Final Testing & Demo', 85, 'robotics', 'Multi-sensor indoor mobile assistant capable of obstacle avoidance, environmental telemetry, and voice-triggered appliance control.', 'Chamidya J.A.S., De Silva P.S.S., Kalansooriya O.K.', 'ESP32-S3, HC-SR04, L298N'),
('Group No. 02', 'Smart Greenhouse Climate & Drip Irrigation Controller', 'IoT & Smart Systems', 'PCB & Enclosure Build', 70, 'iot', 'Automated soil moisture monitoring and solenoid valve control system with real-time MQTT telemetry.', 'Chamathaka W.V.S., Kulathilaka K.D.A.K.', 'ESP8266 NodeMCU, Soil Sensors, 5V Relay');

INSERT INTO lab_reservations (room, table_number, reserve_date, time_slot, student_name, group_no, purpose) VALUES 
('Project Room 01', 2, CURDATE(), '08:00 AM - 10:00 AM', 'Chamidya J.A.S.', 'Group No. 01', 'Ultrasonic array & motor driver calibration'),
('Study Room 01', 3, CURDATE(), '08:00 AM - 10:00 AM', 'Omesh N.A.N.', 'Group No. 04', 'STM32 C firmware debugging');
