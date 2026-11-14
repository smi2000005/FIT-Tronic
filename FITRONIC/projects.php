<?php
require_once 'db.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    
    if (isset($_POST['submit_booking'])) {
        $room = $_POST['room'] ?? '';
        $table = (int)($_POST['table_number'] ?? 0);
        $date = $_POST['reserve_date'] ?? '';
        $slot = $_POST['time_slot'] ?? '';
        $student = trim($_POST['student_name'] ?? '');
        $group = trim($_POST['student_group'] ?? '');
        $purpose = trim($_POST['booking_purpose'] ?? '');

        if ($table > 0 && !empty($student) && !empty($group)) {
            createReservation($conn, $room, $table, $date, $slot, $student, $group, $purpose);
            header("Location: projects.php?booked=1");
            exit();
        }
    }
    
    
    if (isset($_POST['cancel_booking'])) {
        $resId = (int)$_POST['reservation_id'];
        cancelReservation($conn, $resId);
        header("Location: projects.php?cancelled=1");
        exit();
    }

   
    if (isset($_POST['submit_project'])) {
        $groupNo = trim($_POST['group_no'] ?? '');
        $category = $_POST['category'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $members = trim($_POST['members'] ?? '');
        $components = trim($_POST['components'] ?? '');
        $stage = $_POST['stage'] ?? '';
        $progress = (int)($_POST['progress'] ?? 30);
        $theme = $_POST['image_theme'] ?? 'pcb';
        $desc = trim($_POST['description'] ?? '');
        
        if (!empty($title) && !empty($groupNo)) {
            createProject($conn, $groupNo, $title, $category, $stage, $progress, $theme, $desc, $members, $components);
            header("Location: projects.php?project_added=1");
            exit();
        }
    }

   
    if (isset($_POST['update_project'])) {
        $id = (int)$_POST['project_id'];
        $stage = $_POST['stage'] ?? '';
        $progress = (int)($_POST['progress'] ?? 0);
        $desc = trim($_POST['description'] ?? '');
        
        updateProject($conn, $id, $stage, $progress, $desc);
        header("Location: projects.php?project_updated=1");
        exit();
    }
}

$dbProjects = getAllProjects($conn);
$dbReservations = getAllReservations($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects & Lab Reservations | FITRONIC</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans+Flex:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700&family=Space+Grotesk:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="project.css?v=<?php echo time(); ?>">
</head>
<body>

   
    <nav class="topbar">
        <a class="brand" href="index1.html">FI<span>T</span>RONIC</a>
        <div class="nav-index">
            <a href="index1.html">01 / Home</a>
            <a href="marketplace.php">02 / MarketPlace</a>
            <a href="forum.php">03 / Forum</a>
            <a href="projects.php">04 / Projects</a>
            <?php renderAuthNav(); ?>
        </div>
    </nav>

   <section class="hero" id="hero">
        <h1 class="hero-title">PRO<b>J</b>ECTS</h1>
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
            <span class="mono-label">WHERE BLUEPRINTS BECOME WORKING HARDWARE</span>
            <p>Reserve workbench tables across our project and study rooms, track team prototype milestones, and publish group progress updates.</p>
            <div class="hero-actions">
                <a href="#reservations-section" class="btn btn-light-blue">Book a Lab Table</a>
                <a href="#groups-section" class="btn btn-outline-light">Explore Project Groups</a>
            </div>
        </div>
    </section>

    <main class="main-container">
        
        <?php if(isset($_GET['booked'])): ?>
            <div class="alert success" style="margin-bottom:1.5rem;">Table reserved successfully!</div>
        <?php elseif(isset($_GET['cancelled'])): ?>
            <div class="alert success" style="margin-bottom:1.5rem;">Reservation cancelled successfully.</div>
        <?php elseif(isset($_GET['project_added'])): ?>
            <div class="alert success" style="margin-bottom:1.5rem;">Project group published to the showcase!</div>
        <?php elseif(isset($_GET['project_updated'])): ?>
            <div class="alert success" style="margin-bottom:1.5rem;">Project milestones updated successfully.</div>
        <?php endif; ?>

    <section id="reservations-section" class="content-section">
            <div class="section-header">
                <div>
                    <h2>PROJECT & STUDY <span>ROOM AVAILABILITY</span></h2>
                    <p class="section-desc">Select a room, date, and time slot to claim a workbench table for your team.</p>
                </div>
            </div>

            <div class="room-tabs" id="room-tabs" role="tablist">
                <button class="room-tab active" data-room="Project Room 01"><span class="room-tab-title">Project Room 01</span></button>
                <button class="room-tab" data-room="Project Room 02"><span class="room-tab-title">Project Room 02</span></button>
                <button class="room-tab" data-room="Study Room 01"><span class="room-tab-title">Study Room 01</span></button>
                <button class="room-tab" data-room="Study Room 02"><span class="room-tab-title">Study Room 02</span></button>
            </div>

            <div class="filter-bar card">
                <div class="filter-item">
                    <label for="room-select">Active Room</label>
                    <select id="room-select">
                        <option value="Project Room 01">Project Room 01</option>
                        <option value="Project Room 02">Project Room 02</option>
                        <option value="Study Room 01">Study Room 01</option>
                        <option value="Study Room 02">Study Room 02</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label for="date-select">Reservation Date</label>
                    <input type="date" id="date-select">
                </div>
                <div class="filter-item">
                    <label for="slot-select">Time Slot</label>
                    <select id="slot-select">
                        <option value="08:00 AM - 10:00 AM">08:00 AM - 10:00 AM</option>
                        <option value="10:00 AM - 12:00 PM">10:00 AM - 12:00 PM</option>
                        <option value="01:00 PM - 03:00 PM">01:00 PM - 03:00 PM</option>
                        <option value="03:00 PM - 05:00 PM">03:00 PM - 05:00 PM</option>
                        <option value="05:00 PM - 07:00 PM">05:00 PM - 07:00 PM</option>
                    </select>
                </div>
                <div class="legend-box">
                    <span class="legend-title">Table Status Key:</span>
                    <div class="legend-items">
                        <span class="legend-pill available"><i class="dot"></i> Available</span>
                        <span class="legend-pill selected"><i class="dot"></i> Selected</span>
                        <span class="legend-pill reserved"><i class="dot"></i> Reserved</span>
                    </div>
                </div>
            </div>

            <div class="booking-workspace">
                <div class="card floor-card">
                    <div class="floor-header">
                        <div>
                            <h3 id="floor-room-title">Project Room 01 — Floor Layout</h3>
                            <p id="floor-slot-subtitle" class="muted-text hidden"></p>
                        </div>
                        <span id="availability-counter" class="availability-badge">4 / 6 Available</span>
                    </div>
                    <div id="tables-grid" class="tables-grid"></div>
                </div>

                <div class="card booking-form-card">
                    <h3>Confirm Table Reservation</h3>
                    <div id="booking-feedback" class="alert hidden" role="alert"></div>

                    <form id="booking-form" action="projects.php" method="POST" style="margin-top: 1rem;">
                        <div class="selected-summary" id="selected-summary">
                            <div class="summary-row"><span>Room:</span><strong id="summary-room">Project Room 01</strong></div>
                            <div class="summary-row"><span>Table:</span><strong id="summary-table" class="text-orange">None Selected</strong></div>
                            <div class="summary-row"><span>Date & Time:</span><strong id="summary-datetime">-</strong></div>
                        </div>

                        <input type="hidden" name="room" id="form-room">
                        <input type="hidden" name="table_number" id="form-table">
                        <input type="hidden" name="reserve_date" id="form-date">
                        <input type="hidden" name="time_slot" id="form-slot">

                        <div class="form-group">
                            <label for="student-name">Student Name *</label>
                            <input type="text" id="student-name" name="student_name" placeholder="e.g., Chamidya J.A.S." required>
                        </div>
                        <div class="form-group">
                            <label for="student-group">Project Group Number *</label>
                            <input type="text" id="student-group" name="student_group" placeholder="e.g., Group No. 01" required>
                        </div>
                        <div class="form-group">
                            <label for="booking-purpose">Activity / Equipment Needed *</label>
                            <input type="text" id="booking-purpose" name="booking_purpose" placeholder="e.g., Motor testing" required>
                        </div>
                        <button type="submit" name="submit_booking" class="btn btn-navy btn-full">Reserve Selected Table</button>
                    </form>
                </div>
            </div>

            <div class="card my-reservations-card">
                <div class="reservations-header">
                    <div><h3>Current Active Reservations</h3></div>
                    <span id="total-bookings-badge" class="count-badge">0 Active</span>
                </div>
                <div id="reservations-list" class="reservations-list"></div>
            </div>
        </section>
        <section id="groups-section" class="content-section">
            <div class="section-header">
                <div>
                   <h2>THE <span>HARDWARE</span> BUILD SHOWCASE</h2>
                    <p class="section-desc">Live engineering milestones, custom PCBs, and robotics prototypes built by FITRONIC student teams.</p>
                </div>
                <button id="open-add-modal-btn" class="btn btn-light-blue">+ Add Project Group</button>
            </div>

            <div class="showcase-toolbar card">
                <div class="search-box">
                    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" id="project-search" placeholder="Search by project title or group number...">
                </div>
                <div class="category-filters" id="category-filters">
                    <button class="filter-chip active" data-category="All">All Projects</button>
                    <button class="filter-chip" data-category="Robotics & Automation">Robotics & Automation</button>
                    <button class="filter-chip" data-category="IoT & Smart Systems">IoT & Smart Systems</button>
                    <button class="filter-chip" data-category="Embedded & PCB">Embedded & PCB</button>
                    <button class="filter-chip" data-category="Energy & Environment">Energy & Environment</button>
                </div>
            </div>

            <div id="projects-grid" class="projects-grid"></div>
        </section>
    </main>

    <!-- Modal 1: View Details -->
    <div id="details-modal" class="modal-overlay hidden" role="dialog">
        <div class="modal-dialog modal-lg">
            <div class="modal-header">
                <div>
                    <span id="detail-group-badge" class="modal-badge">Group No. 01</span>
                    <h3 id="detail-title">Project Title</h3>
                </div>
                <button class="modal-close" data-close-modal="details-modal">×</button>
            </div>
            <div class="modal-body">
                <div class="detail-hero-grid">
                    <img id="detail-image" src="" alt="Hardware Prototype" class="detail-img">
                    <div class="detail-meta-box">
                        <div class="detail-stat"><span class="stat-label">Domain</span><strong id="detail-category">—</strong></div>
                        <div class="detail-stat"><span class="stat-label">Milestone</span><strong id="detail-stage">—</strong></div>
                        <div class="detail-stat">
                            <span class="stat-label">Progress</span>
                            <div class="progress-bar-wrap">
                                <div class="progress-track"><div id="detail-progress-fill" class="progress-fill" style="width: 0%;"></div></div>
                                <strong id="detail-progress-text">0%</strong>
                            </div>
                        </div>
                        <div class="detail-stat"><span class="stat-label">Team</span><p id="detail-members" class="detail-text-sm">—</p></div>
                    </div>
                </div>
                <div class="detail-section"><h4>Technical Overview</h4><p id="detail-full-desc">—</p></div>
                <div class="detail-section"><h4>Components</h4><div id="detail-components" class="component-tags"></div></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-navy" id="detail-edit-trigger">Update Progress & Description</button>
                <button class="btn btn-navy" data-close-modal="details-modal">Close</button>
            </div>
        </div>
    </div>

    <!-- Modal 2: Add Project -->
    <div id="add-project-modal" class="modal-overlay hidden" role="dialog">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3>Register New Hardware Project Group</h3>
                <button class="modal-close" data-close-modal="add-project-modal">×</button>
            </div>
            <form action="projects.php" method="POST" class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Group Number *</label>
                        <input type="text" name="group_no" placeholder="e.g., Group No. 07" required>
                    </div>
                    <div class="form-group">
                        <label>Project Category *</label>
                        <select name="category" required>
                            <option value="Robotics & Automation">Robotics & Automation</option>
                            <option value="IoT & Smart Systems">IoT & Smart Systems</option>
                            <option value="Embedded & PCB">Embedded & PCB</option>
                            <option value="Energy & Environment">Energy & Environment</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Project Title *</label>
                    <input type="text" name="title" placeholder="e.g., Warehouse Bot" required>
                </div>
                <div class="form-group">
                    <label>Team Members *</label>
                    <input type="text" name="members" placeholder="e.g., Kasun P., Nimali S." required>
                </div>
                <div class="form-group">
                    <label>Hardware Components *</label>
                    <input type="text" name="components" placeholder="e.g., ESP32, L298N" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Presentation Milestone *</label>
                        <select name="stage" required>
                            <option value="Proposal & Schematic">Proposal & Schematic</option>
                            <option value="Breadboard Prototype">Breadboard Prototype</option>
                            <option value="PCB & Enclosure Build">PCB & Enclosure Build</option>
                            <option value="Final Testing & Demo">Final Testing & Demo</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Completion: <span id="new-progress-val">30</span>%</label>
                        <input type="range" id="new-progress" name="progress" min="5" max="100" step="5" value="30">
                    </div>
                </div>
                <div class="form-group">
                    <label>Prototype Illustration Theme *</label>
                    <select name="image_theme">
                        <option value="robotics">Robotics & Servo Actuator</option>
                        <option value="iot">IoT Wireless Sensor Node</option>
                        <option value="pcb">Custom Microcontroller & PCB</option>
                        <option value="energy">Solar & Power Electronics</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Project Description *</label>
                    <textarea name="description" rows="3" required></textarea>
                </div>
                <div class="modal-footer no-padding">
                    <button type="button" class="btn btn-outline-navy" data-close-modal="add-project-modal">Cancel</button>
                    <button type="submit" name="submit_project" class="btn btn-navy">Publish Group Card</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 3: Update Project -->
    <div id="edit-project-modal" class="modal-overlay hidden" role="dialog">
        <div class="modal-dialog">
            <div class="modal-header">
                <div>
                    <span id="edit-group-badge" class="modal-badge">Group No. 01</span>
                    <h3 id="edit-project-heading">Update Project Progress</h3>
                </div>
                <button class="modal-close" data-close-modal="edit-project-modal">×</button>
            </div>
            <form action="projects.php" method="POST" class="modal-body">
                <input type="hidden" id="edit-project-id" name="project_id">
                
                <div class="form-group">
                    <label>Presentation Milestone Status *</label>
                    <select id="edit-stage" name="stage" required>
                        <option value="Proposal & Schematic">Proposal & Schematic</option>
                        <option value="Breadboard Prototype">Breadboard Prototype</option>
                        <option value="PCB & Enclosure Build">PCB & Enclosure Build</option>
                        <option value="Final Testing & Demo">Final Testing & Demo</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Completion Percentage: <strong id="edit-progress-val">50</strong>%</label>
                    <input type="range" id="edit-progress" name="progress" min="0" max="100" step="5" value="50">
                </div>
                <div class="form-group">
                    <label>Updated Project Description *</label>
                    <textarea id="edit-description" name="description" rows="4" required></textarea>
                </div>
                <div class="modal-footer no-padding">
                    <button type="button" class="btn btn-outline-navy" data-close-modal="edit-project-modal">Cancel</button>
                    <button type="submit" name="update_project" class="btn btn-navy">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Inject MySQL Data securely into JavaScript -->
    <script>
        window.APP_DATA = {
            reservations: <?php echo json_encode($dbReservations) ?: '[]'; ?>,
            projects: <?php echo json_encode($dbProjects) ?: '[]'; ?>
        };
    </script>
    <script src="project.js?v=<?php echo time(); ?>"></script>

    <!-- Kinetic Elastic String & Floating Boxes Physics (Aligned with index1) -->
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