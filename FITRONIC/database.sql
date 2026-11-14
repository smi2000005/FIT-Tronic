CREATE DATABASE IF NOT EXISTS hardware_hub;
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

INSERT INTO users (full_name, email, role) VALUES 
('Kasun Perera', 'kasun@uom.lk', 'student'),
('Dr. Nimal Silva', 'nimal@uom.lk', 'instructor');

INSERT INTO forum_questions (user_id, title, category, description) VALUES 
(1, 'My stepper motor is vibrating but not rotating with DRV8825', 'Motors & Drivers', 'I connected a NEMA 17 stepper motor to an Arduino Mega using a DRV8825 driver and 12V supply, but it only hums and vibrates in place. What can I do?');

INSERT INTO forum_answers (question_id, user_id, answer_text) VALUES 
(1, 2, 'Check your coil wiring pairs using a multimeter—two coil wires are likely swapped. Also adjust the Vref potentiometer on the DRV8825 so the current limit matches your motor.');