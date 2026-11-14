<?php
require_once 'db.php';

$questionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$question = getQuestionById($conn, $questionId);

if (!$question) {
    header("Location: forum.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_answer'])) {
    requireLogin('Sign in to post an answer.');
    $answerText = trim($_POST['answer_text']);
    $userId = getCurrentUserId();

    if (!empty($answerText)) {
        addAnswer($conn, $questionId, $userId, $answerText);
        header("Location: question.php?id=" . $questionId);
        exit();
    }
}

$answers = getAnswersByQuestion($conn, $questionId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($question['title']); ?> - Forum</title>
    <link rel="stylesheet" href="forum.css">
</head>
<body>

<nav class="navbar">
    <div class="nav-brand">HardwareLab Hub</div>
    <div class="nav-links">
        <a href="forum.php">← Back to Discussion Board</a>
    </div>
</nav>

<main class="container">

    <article class="question-detail-box">
        <div class="card-meta">
            <span class="category-pill"><?php echo htmlspecialchars($question['category']); ?></span>
            <span>Asked by <strong><?php echo htmlspecialchars($question['full_name']); ?></strong> on <?php echo date('M d, Y h:i A', strtotime($question['created_at'])); ?></span>
        </div>
        <h1><?php echo htmlspecialchars($question['title']); ?></h1><br>
        <p class="full-description"><?php echo nl2br(htmlspecialchars($question['description'])); ?></p>
    </article>

    <section class="answers-section">
        <h3><?php echo count($answers); ?> Answers</h3>

        <?php if (empty($answers)): ?>
            <p class="empty-state">No answers yet. Help your batchmate by posting a solution below!</p>
        <?php else: ?>
            <?php foreach ($answers as $ans): ?>
                <div class="answer-card <?php echo $ans['role'] === 'instructor' ? 'instructor-highlight' : ''; ?>">
                    <div class="answer-header">
                        <strong><?php echo htmlspecialchars($ans['full_name']); ?></strong>
                        <?php if ($ans['role'] === 'instructor'): ?>
                            <span class="badge-instructor">✓ Verified Instructor</span>
                        <?php else: ?>
                            <span class="badge-student">Student</span>
                        <?php endif; ?>
                        <span class="timestamp"><?php echo date('M d, Y h:i A', strtotime($ans['created_at'])); ?></span>
                    </div>
                    <p class="answer-body"><?php echo nl2br(htmlspecialchars($ans['answer_text'])); ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="reply-form-box">
        <h3>Your Answer</h3>
        <form action="question.php?id=<?php echo $questionId; ?>" method="POST" id="answerForm">
            <textarea name="answer_text" id="answer_text" rows="4" placeholder="Write your troubleshooting steps or solution..." required></textarea>
            <button type="submit" name="submit_answer" class="btn-primary">Post Answer</button>
        </form>
    </section>
</main>

<script src="forum.js"></script>
</body>
</html>