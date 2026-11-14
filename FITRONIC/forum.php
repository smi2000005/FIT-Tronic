<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_question'])) {
    requireLogin('Sign in to ask a question.');
    $title = trim($_POST['title']);
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);
    $userId = getCurrentUserId(); 

    if (!empty($title) && !empty($description)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO forum_questions (user_id, title, category, description) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "isss", $userId, $title, $category, $description);
            mysqli_stmt_execute($stmt);
        }
        header("Location: forum.php?added=1");
        exit();
    }
}

$sql = "SELECT q.*, u.full_name, 
        (SELECT COUNT(*) FROM forum_answers WHERE question_id = q.id) as answer_count 
        FROM forum_questions q 
        JOIN users u ON q.user_id = u.id 
        ORDER BY q.created_at DESC";
$result = mysqli_query($conn, $sql);
$questions = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $questions[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Q&A Forum | FITRONIC</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans+Flex:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700&family=Space+Grotesk:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="forum.css?v=<?php echo time(); ?>">
</head>
<body>

    <nav class="topbar">
        <a class="brand" href="index1.html">FI<span>T</span>RONIC</a>
        <div class="nav-index">
            <a href="index1.html">01 / Home</a>
            <a href="marketplace.php">02 / MarketPlace</a>
            <a href="forum.php" active>03 / Forum</a>
            <a href="projects.php">04 / Projects</a>
            <?php renderAuthNav(); ?>
        </div>
    </nav>

    <section class="hero" id="hero">
        <h1 class="hero-title">FO<b>R</b>UM</h1>
        <svg class="string-svg"
             id="stringSvg"
             preserveAspectRatio="none"
             aria-hidden="true">
            <path class="string-path" id="stringPath"></path>
            <circle class="string-anchor" id="anchorL" r="5"></circle>
            <circle class="string-anchor" id="anchorR" r="5"></circle>
            <circle class="string-grip"   id="grip"    r="7"></circle>
        </svg>
        <div class="hero-foot">
            <span class="mono-label">DEBUGGING, WIRING & COMMUNITY KNOWLEDGE</span>
            <p>Stuck on a stepper driver, sensor noise, or microcontroller code? Post your schematic or bug and get fast fixes from peers and instructors.</p>
            <div class="hero-actions">
                <button type="button" class="btn btn-light-blue" onclick="document.getElementById('openModalBtn').click()">+ Ask a Question</button>
                <a href="#forum-section" class="btn btn-outline-light">Browse Discussions</a>
            </div>
        </div>
    </section>

    <main class="main-container">
        <section id="forum-section" class="content-section">
            
            <div class="section-header">
                <div>
                    <h2>HARDWARE & CODE <span>DISCUSSIONS</span></h2>
                    <p class="section-desc">Search solved wiring issues, filter by hardware module, or start a new troubleshooting thread.</p>
                </div>
                <button id="openModalBtn" class="btn btn-light-blue">+ Ask a Question</button>
            </div>

            <?php if(isset($_GET['added'])): ?>
                <div class="alert success">Your question has been posted successfully!</div>
            <?php endif; ?>

            <div class="showcase-toolbar card">
                <div class="search-box">
                    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" id="searchInput" placeholder="Search issues (e.g., stepper motor, ESP32, sensor)...">
                </div>

                <div class="category-filters" id="tagFilters">
                    <button class="filter-chip active" data-category="All">All Topics</button>
                    <button class="filter-chip" data-category="Motors & Drivers">Motors & Drivers</button>
                    <button class="filter-chip" data-category="Microcontrollers">Microcontrollers</button>
                    <button class="filter-chip" data-category="Sensors">Sensors</button>
                    <button class="filter-chip" data-category="Power & Wiring">Power & Wiring</button>
                </div>
            </div>

            <div class="questions-list" id="questionList">
                <?php if (empty($questions)): ?>
                    <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 3rem;">
                        <h3>No questions asked yet</h3>
                        <p class="muted-text" style="margin-top: 0.4rem;">Be the first to ask the community for help!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($questions as $q): ?>
                        <article class="question-card" 
                                 data-category="<?php echo htmlspecialchars($q['category']); ?>" 
                                 onclick="window.location.href='question.php?id=<?php echo $q['id']; ?>'">
                            
                            <div>
                                <div class="q-card-top">
                                    <h3 class="q-card-title"><?php echo htmlspecialchars($q['title']); ?></h3>
                                    <span class="q-category-pill">
                                        <?php echo htmlspecialchars($q['category']); ?>
                                    </span>
                                </div>
                                
                                <p class="q-card-excerpt">
                                    <?php echo htmlspecialchars($q['description']); ?>
                                </p>
                            </div>
                            
                            <div class="q-card-footer">
                                <span class="q-author-meta">
                                    Posted by <strong><?php echo htmlspecialchars($q['full_name']); ?></strong> • <?php echo date('M d, Y', strtotime($q['created_at'])); ?>
                                </span>
                                <span class="q-answer-count <?php echo $q['answer_count'] > 0 ? 'has-answers' : ''; ?>">
                                    💬 <?php echo $q['answer_count']; ?> Answer<?php echo $q['answer_count'] != 1 ? 's' : ''; ?> →
                                </span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
        </section>
    </main>

    <div id="questionModal" class="modal-overlay hidden" role="dialog">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3>Ask a Hardware or Code Question</h3>
                <button class="modal-close" id="closeModalBtn">×</button>
            </div>
            <form action="forum.php" method="POST" class="modal-body">
                
                <div class="form-group">
                    <label for="title">Problem Title *</label>
                    <input type="text" id="title" name="title" placeholder="e.g., My motor is vibrating but not spinning..." required>
                </div>

                <div class="form-group">
                    <label for="category">Topic Tag *</label>
                    <select id="category" name="category" required>
                        <option value="Motors & Drivers">Motors & Drivers</option>
                        <option value="Microcontrollers">Microcontrollers</option>
                        <option value="Sensors">Sensors</option>
                        <option value="Power & Wiring">Power & Wiring</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description">Detailed Description *</label>
                    <textarea id="description" name="description" rows="5" placeholder="Describe your wiring setup, code, and the exact error..." required></textarea>
                </div>

                <div class="modal-footer no-padding">
                    <button type="button" id="cancelModalBtn" class="btn btn-outline-navy">Cancel</button>
                    <button type="submit" name="submit_question" class="btn btn-navy">Post Question</button>
                </div>
            </form>
        </div>
    </div>

    <script src="forum.js?v=<?php echo time(); ?>"></script>

    <script>
    (function() {
        "use strict";
        var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var hero = document.getElementById('hero');
        var svg = document.getElementById('stringSvg');
        var path = document.getElementById('stringPath');
        var aL = document.getElementById('anchorL');
        var aR = document.getElementById('anchorR');
        var grip = document.getElementById('grip');
        if (!hero || !svg || !path) return;

        var field = document.createElement('div');
        field.className = 'field';
        hero.insertBefore(field, hero.firstChild);

        var boxes = [];
        var COUNT = 18;
        for (var bi = 0; bi < COUNT; bi++) {
            var el = document.createElement('div');
            var roll = Math.random();
            el.className = 'box' + (roll < 0.22 ? ' acid' : (roll < 0.42 ? ' warm' : ''));
            var bw = 24 + Math.random() * 84;
            var bh = (Math.random() < 0.80) ? bw : (24 + Math.random() * 84);
            el.style.width = bw.toFixed(0) + 'px';
            el.style.height = bh.toFixed(0) + 'px';
            var fx = Math.random();
            var fy = 0.12 + Math.random() * 0.76;
            el.style.left = (fx * 100).toFixed(2) + '%';
            el.style.top = (fy * 100).toFixed(2) + '%';
            el.style.marginLeft = (-bw / 2) + 'px';
            el.style.marginTop = (-bh / 2) + 'px';
            field.appendChild(el);
            boxes.push({
                el: el, fx: fx, fy: fy, w: bw, h: bh,
                rot: (Math.random() * 2 - 1) * 14,
                oy: 0, ox: 0, vx: 0, vy: 0, prevRopeY: 0
            });
        }

        var W = 0, H = 0, baseY = 0;
        function size() {
            var r = hero.getBoundingClientRect();
            W = r.width;
            H = r.height;
            baseY = H / 2;
            svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
            aL.setAttribute('cx', 0);
            aL.setAttribute('cy', baseY);
            aR.setAttribute('cx', W);
            aR.setAttribute('cy', baseY);
        }

        function relaxHomes() {
            if (!W || !H) return;
            var m = 12;
            var pts = boxes.map(function(b) {
                return { x: b.fx * W, y: b.fy * H, hw: b.w / 2, hh: b.h / 2 };
            });
            for (var it = 0; it < 16; it++) {
                for (var a = 0; a < pts.length; a++) {
                    for (var c = a + 1; c < pts.length; c++) {
                        var pa = pts[a], pb = pts[c];
                        var dx = pa.x - pb.x, dy = pa.y - pb.y;
                        var ox = (pa.hw + pb.hw + 8) - Math.abs(dx);
                        var oy = (pa.hh + pb.hh + 8) - Math.abs(dy);
                        if (ox > 0 && oy > 0) {
                            if (ox < oy) {
                                var s = (dx < 0 ? -1 : 1) * ox * 0.5;
                                pa.x += s; pb.x -= s;
                            } else {
                                var s2 = (dy < 0 ? -1 : 1) * oy * 0.5;
                                pa.y += s2; pb.y -= s2;
                            }
                        }
                    }
                }
                for (var q = 0; q < pts.length; q++) {
                    var p = pts[q];
                    if (p.x < p.hw + m) p.x = p.hw + m;
                    else if (p.x > W - p.hw - m) p.x = W - p.hw - m;
                    if (p.y < p.hh + m) p.y = p.hh + m;
                    else if (p.y > H - p.hh - m) p.y = H - p.hh - m;
                }
            }
            boxes.forEach(function(b, idx) {
                b.fx = pts[idx].x / W;
                b.fy = pts[idx].y / H;
                b.el.style.left = (b.fx * 100).toFixed(3) + '%';
                b.el.style.top = (b.fy * 100).toFixed(3) + '%';
            });
        }

        size();
        relaxHomes();
        window.addEventListener('resize', function() { size(); relaxHomes(); });

        var mouseX = 0, mouseY = 0, active = false;
        var posX = W / 2, posY = baseY, velY = 0;

        hero.addEventListener('pointermove', function(e) {
            var r = hero.getBoundingClientRect();
            mouseX = e.clientX - r.left;
            mouseY = e.clientY - r.top;
            active = true;
        });
        hero.addEventListener('pointerleave', function() { active = false; });

        function draw() {
            if (W === 0) size();
            posX = active ? mouseX : W / 2;

            if (reduceMotion) {
                posY = baseY;
            } else {
                var targetY = active ? mouseY : baseY;
                velY += (targetY - posY) * 0.055;
                velY *= 0.80;
                posY += velY;
            }

            var cy = 2 * posY - baseY;
            path.setAttribute('d', 'M 0 ' + baseY + ' Q ' + posX + ' ' + cy + ' ' + W + ' ' + baseY);
            grip.setAttribute('cx', posX);
            grip.setAttribute('cy', posY);
            grip.setAttribute('opacity', active ? 1 : 0);

            function ropeYAt(x) {
                var A = W - 2 * posX, t;
                if (Math.abs(A) < 0.0001) {
                    t = x / W;
                } else {
                    var disc = posX * posX + A * x;
                    if (disc < 0) disc = 0;
                    t = (-posX + Math.sqrt(disc)) / A;
                }
                t = Math.max(0, Math.min(1, t));
                var omt = 1 - t;
                return omt * omt * baseY + 2 * omt * t * cy + t * t * baseY;
            }

            for (var k3 = 0; k3 < boxes.length; k3++) {
                var b = boxes[k3];
                var hh = b.h / 2, hw = b.w / 2;
                var px = b.fx * W + b.ox;
                var py = b.fy * H + b.oy;

                if (!reduceMotion) {
                    var nearest = ropeYAt(px);
                    var rl = ropeYAt(px - hw), rr = ropeYAt(px + hw);
                    if (Math.abs(rl - py) < Math.abs(nearest - py)) nearest = rl;
                    if (Math.abs(rr - py) < Math.abs(nearest - py)) nearest = rr;

                    var gap = py - nearest;
                    var pen = (hh + 14) - Math.abs(gap);
                    if (pen > 0) {
                        var dir = gap === 0 ? (b.fy < 0.5 ? -1 : 1) : (gap > 0 ? 1 : -1);
                        b.vy += dir * pen * 0.16;
                        b.vy += (nearest - b.prevRopeY) * 0.55;
                    }
                    b.prevRopeY = nearest;
                }

                b.vx += -b.ox * 0.010;
                b.vy += -b.oy * 0.010;
                b.vx *= 0.85;
                b.vy *= 0.85;
                b.ox += b.vx;
                b.oy += b.vy;

                var minOx = (hw + 4) - b.fx * W, maxOx = (W - hw - 4) - b.fx * W;
                var minOy = (hh + 4) - b.fy * H, maxOy = (H - hh - 4) - b.fy * H;
                if (b.ox < minOx) { b.ox = minOx; if (b.vx < 0) b.vx = 0; }
                else if (b.ox > maxOx) { b.ox = maxOx; if (b.vx > 0) b.vx = 0; }
                if (b.oy < minOy) { b.oy = minOy; if (b.vy < 0) b.vy = 0; }
                else if (b.oy > maxOy) { b.oy = maxOy; if (b.vy > 0) b.vy = 0; }

                b.el.style.transform =
                    'translate(' + b.ox.toFixed(1) + 'px,' + b.oy.toFixed(1) + 'px) rotate(' +
                    (b.rot + b.oy * 0.10).toFixed(2) + 'deg)';
            }

            requestAnimationFrame(draw);
        }
        requestAnimationFrame(draw);
    })();
    </script>
</body>
</html>