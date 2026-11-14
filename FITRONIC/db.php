<?php
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);

$config = require __DIR__ . '/config.php';

try {
    $conn = mysqli_connect($config['host'], $config['user'], $config['pass'], $config['name'], (int)$config['port']);
} catch (mysqli_sql_exception $e) {
    die("Database connection failed: " . $e->getMessage());
}
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}


function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
}

function getCurrentUserName() {
    return isset($_SESSION['display_name']) ? (string)$_SESSION['display_name'] : '';
}

function requireLogin($message = 'Please sign in first.') {
    if (getCurrentUserId() === 0) {
        header('Location: index1.html?authMode=signin&authMessage=' . rawurlencode($message) . '#initiate');
        exit();
    }
}

function renderAuthNav() {
    if (getCurrentUserId() === 0) {
        echo '<a class="nav-signin" href="index1.html?authMode=signin#initiate">SIGN IN</a>';
        return;
    }
    echo '<a class="nav-signin" href="index1.html#hero">' . htmlspecialchars(getCurrentUserName()) . '</a>';
    echo '<a class="nav-signout" href="logout.php">SIGN OUT</a>';
}

function getAllQuestions($conn) {
    $sql = "SELECT q.*, u.full_name, u.role, 
            (SELECT COUNT(*) FROM forum_answers a WHERE a.question_id = q.id) AS reply_count
            FROM forum_questions q
            JOIN users u ON q.user_id = u.id
            ORDER BY q.created_at DESC";
    $result = mysqli_query($conn, $sql);
    $questions = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $questions[] = $row;
    }
    return $questions;
}

function createQuestion($conn, $userId, $title, $category, $description) {
    $stmt = mysqli_prepare($conn, "INSERT INTO forum_questions (user_id, title, category, description) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "isss", $userId, $title, $category, $description);
    return mysqli_stmt_execute($stmt);
}

function getQuestionById($conn, $questionId) {
    $stmt = mysqli_prepare($conn, "SELECT q.*, u.full_name, u.role FROM forum_questions q JOIN users u ON q.user_id = u.id WHERE q.id = ?");
    mysqli_stmt_bind_param($stmt, "i", $questionId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}

function getAnswersByQuestion($conn, $questionId) {
    $stmt = mysqli_prepare($conn, "SELECT a.*, u.full_name, u.role FROM forum_answers a JOIN users u ON a.user_id = u.id WHERE a.question_id = ? ORDER BY (u.role = 'instructor') DESC, a.created_at ASC");
    mysqli_stmt_bind_param($stmt, "i", $questionId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $answers = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $answers[] = $row;
    }
    return $answers;
}

function addAnswer($conn, $questionId, $userId, $answerText) {
    $stmt = mysqli_prepare($conn, "INSERT INTO forum_answers (question_id, user_id, answer_text) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "iis", $questionId, $userId, $answerText);
    return mysqli_stmt_execute($stmt);
}

 
function getAllItems($conn) {
    $sql = "SELECT m.*, u.full_name 
            FROM marketplace_items m
            JOIN users u ON m.user_id = u.id
            ORDER BY m.created_at DESC";
    $result = mysqli_query($conn, $sql);
    $items = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
    return $items;
}


function placeBid($conn, $itemId, $bidAmount, $email) {
    
    $stmt = mysqli_prepare($conn, "SELECT price, highest_bid FROM marketplace_items WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $itemId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $item = mysqli_fetch_assoc($result);

    $currentMax = max($item['price'], $item['highest_bid']);

    if ($bidAmount > $currentMax) {
        $updateStmt = mysqli_prepare($conn, "UPDATE marketplace_items SET highest_bid = ?, highest_bidder_email = ? WHERE id = ?");
        mysqli_stmt_bind_param($updateStmt, "dsi", $bidAmount, $email, $itemId);
        return mysqli_stmt_execute($updateStmt);
    }
    return false; 
}

function createItem($conn, $userId, $itemName, $category, $description, $price, $imagePath) {
    $stmt = mysqli_prepare($conn, "INSERT INTO marketplace_items (user_id, item_name, category, description, price, image_path) VALUES (?, ?, ?, ?, ?, ?)");
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "isssds", $userId, $itemName, $category, $description, $price, $imagePath);
        return mysqli_stmt_execute($stmt);
    }
    
    return false; 
}

function getAllProjects($conn) {$sql = "SELECT * FROM project_groups ORDER BY created_at DESC";
    $result = mysqli_query($conn, $sql);$projects = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {$projects[] = [
                'id' => $row['id'],
                'groupNo' => $row['group_no'],
                'title' => $row['title'],
                'category' => $row['category'],
                'stage' => $row['stage'],
                'progress' => (int)$row['progress'],
                'imageTheme' => $row['image_theme'],
                'description' => $row['description'],
                'members' => $row['members'],
                'components' => array_map('trim', explode(',', $row['components']))
            ];
        }
    }
    return $projects;
}

function createProject($conn,$groupNo, $title,$category, $stage,$progress, $imageTheme,$description, $members,$components) {
    $stmt = mysqli_prepare($conn, "INSERT INTO project_groups (group_no, title, category, stage, progress, image_theme, description, members, components) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssssissss", $groupNo, $title,$category, $stage,$progress, $imageTheme,$description, $members,$components);
        return mysqli_stmt_execute($stmt);
    }
    return false;
}

function updateProject($conn, $id,$stage, $progress,$description) {
    $stmt = mysqli_prepare($conn, "UPDATE project_groups SET stage=?, progress=?, description=? WHERE id=?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sisi", $stage,$progress, $description,$id);
        return mysqli_stmt_execute($stmt);
    }
    return false;
}

function getAllReservations($conn) {$sql = "SELECT * FROM lab_reservations ORDER BY reserve_date DESC, time_slot ASC";
    $result = mysqli_query($conn, $sql);$reservations = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {$reservations[] = [
                'id' => $row['id'],
                'room' => $row['room'],
                'tableNumber' => (int)$row['table_number'],
                'date' => $row['reserve_date'],
                'timeSlot' => $row['time_slot'],
                'studentName' => $row['student_name'],
                'groupNo' => $row['group_no'],
                'purpose' => $row['purpose']
            ];
        }
    }
    return $reservations;
}

function createReservation($conn,$room, $tableNumber,$date, $timeSlot,$studentName, $groupNo,$purpose) {
    $stmt = mysqli_prepare($conn, "INSERT INTO lab_reservations (room, table_number, reserve_date, time_slot, student_name, group_no, purpose) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sisssss", $room, $tableNumber,$date, $timeSlot,$studentName, $groupNo,$purpose);
        return mysqli_stmt_execute($stmt);
    }
    return false;
}

function cancelReservation($conn,$id) {
    $stmt = mysqli_prepare($conn, "DELETE FROM lab_reservations WHERE id=?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        return mysqli_stmt_execute($stmt);
    }
    return false;
}