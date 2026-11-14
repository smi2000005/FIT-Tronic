<?php
require_once 'db.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_item'])) {
    requireLogin('Sign in to list an item.');
    $itemName = trim($_POST['item_name']);
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $userId = getCurrentUserId(); 
    
    $imagePath = 'uploads/default.jpg';
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
        $fileName = time() . '_' . basename($_FILES['item_image']['name']);
        $targetPath = 'uploads/' . $fileName;
        if (move_uploaded_file($_FILES['item_image']['tmp_name'], $targetPath)) {
            $imagePath = $targetPath;
        }
    }

    if (!empty($itemName) && !empty($description) && $price > 0) {
        createItem($conn, $userId, $itemName, $category, $description, $price, $imagePath);
        header("Location: marketplace.php?added=1");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_bid'])) {
    $itemId = (int)$_POST['item_id'];
    $bidAmount = (float)$_POST['bid_amount'];
    $email = trim($_POST['bidder_email']);

    if (!empty($email) && $bidAmount > 0) {
        $success = placeBid($conn, $itemId, $bidAmount, $email);
        if ($success) {
            header("Location: marketplace.php?bid_success=1");
        } else {
            header("Location: marketplace.php?bid_error=too_low");
        }
        exit();
    }
}

$items = getAllItems($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hardware Marketplace | FITRONIC</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans+Flex:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700&family=Space+Grotesk:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="marketplace.css?v=<?php echo time(); ?>">
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
        <h1 class="hero-title">MA<b>R</b>KETPLACE</h1>
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
            <span class="mono-label">CAMPUS HARDWARE EXCHANGE // ZERO FEES</span>
            <p>Got leftover ESP32s, sensors, or stepper drivers gathering dust on your desk? Turn unused lab gear into cash or snag bargain components from fellow makers.</p>
            <div class="hero-actions">
                <button type="button" class="btn btn-light-blue" onclick="document.getElementById('openItemModalBtn').click()">+ List an Extra Part</button>
                <a href="#marketplace-section" class="btn btn-outline-light">Browse Deals</a>
            </div>
        </div>
    </section>

    <main class="main-container">
        <section id="marketplace-section" class="content-section">
            
            <div class="section-header">
                <div>
                    <h2>HARDWARE <span>MARKETPLACE</span></h2>
                    <p class="section-desc">Bid on surplus microcontrollers, sensors, and lab gear listed by fellow students—or auction your own spare parts.</p>
                </div>
                <button id="openItemModalBtn" class="btn btn-light-blue">+ List an Extra Part</button>
            </div>

            <?php if(isset($_GET['bid_error'])): ?>
                <div class="alert error">Your bid was too low! You must bid higher than the current highest bid or base price.</div>
            <?php elseif(isset($_GET['bid_success'])): ?>
                <div class="alert success">Bid placed successfully!</div>
            <?php elseif(isset($_GET['added'])): ?>
                <div class="alert success">Your hardware item has been listed for auction!</div>
            <?php endif; ?>

            <div class="showcase-toolbar">
                <div class="search-box">
                    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" id="marketplaceSearch" placeholder="Search components (e.g., Arduino, Sensor)...">
                </div>

                <div class="category-filters" id="marketplaceFilters">
                    <button class="filter-chip active" data-category="All">All Items</button>
                    <button class="filter-chip" data-category="Microcontrollers">Microcontrollers</button>
                    <button class="filter-chip" data-category="Sensors & Modules">Sensors & Modules</button>
                    <button class="filter-chip" data-category="Motors & Actuators">Motors & Actuators</button>
                    <button class="filter-chip" data-category="Power & Wiring">Power & Wiring</button>
                    <button class="filter-chip" data-category="Other">Other</button>
                </div>
            </div>

            <div class="projects-grid" id="marketplaceGrid">
                <?php if (empty($items)): ?>
                    <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 3rem;">
                        <h3>No items listed yet</h3>
                        <p class="muted-text" style="margin-top: 0.4rem;">Be the first to sell your extra parts!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <?php 
                            $currentHighest = max($item['price'], $item['highest_bid']);
                            $hasBids = $item['highest_bid'] > 0;
                            $itemCategory = !empty($item['category']) ? $item['category'] : 'Other';
                        ?>
                        
                        <article class="project-card marketplace-item" data-category="<?php echo htmlspecialchars($itemCategory); ?>">
                            <div class="card-media">
                                <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>">
                                <span class="group-badge"><?php echo htmlspecialchars($item['full_name']); ?></span>
                                <span class="category-tag">Rs. <?php echo number_format($item['price'], 2); ?></span>
                            </div>
                            
                            <div class="card-content">
                                <div class="item-header-row">
                                    <h3 class="project-title"><?php echo htmlspecialchars($item['item_name']); ?></h3>
                                    <span class="item-category-badge">
                                        <?php echo htmlspecialchars($itemCategory); ?>
                                    </span>
                                </div>
                                
                                <div class="progress-box">
                                    <div class="progress-top">
                                        <span class="stage-label">Current Highest Bid</span>
                                        <span class="percent-label">Rs. <?php echo number_format($currentHighest, 2); ?></span>
                                    </div>
                                    <div class="bid-leader-text">
                                        <?php if($hasBids): ?>
                                            Leading: <span><?php echo htmlspecialchars($item['highest_bidder_email']); ?></span>
                                        <?php else: ?>
                                            No bids placed yet.
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <p class="project-desc"><?php echo htmlspecialchars($item['description']); ?></p>

                                <div class="card-actions">
                                    <button class="btn btn-navy btn-sm" onclick="openBidModal(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars(addslashes($item['item_name'])); ?>', <?php echo $currentHighest; ?>)">Place Bid</button>
                                </div>
                            </div>
                        </article>
                        
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <!-- Bid Modal -->
    <div id="bidModal" class="modal-overlay hidden" role="dialog">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3>Place Your Bid</h3>
                <button class="modal-close" onclick="closeBidModal()">×</button>
            </div>
            <form action="marketplace.php" method="POST" class="modal-body">
                <input type="hidden" id="bid_item_id" name="item_id" value="">
                
                <div class="form-group bid-summary-box">
                    <p>Bidding on: <strong id="bid_item_name" style="color: var(--bg-main);"></strong></p>
                    <p>Minimum Bid: Rs. <span id="bid_min_price"></span></p>
                </div>

                <div class="form-group">
                    <label for="bidder_email">Your Email Address *</label>
                    <input type="email" id="bidder_email" name="bidder_email" placeholder="e.g., student@uom.lk" required>
                </div>

                <div class="form-group">
                    <label for="bid_amount">Your Bid Amount (Rs.) *</label>
                    <input type="number" id="bid_amount" name="bid_amount" step="0.01" required>
                </div>

                <div class="modal-footer no-padding">
                    <button type="button" class="btn btn-outline-navy" onclick="closeBidModal()">Cancel</button>
                    <button type="submit" name="submit_bid" class="btn btn-navy">Submit Bid</button>
                </div>
            </form>
        </div>
    </div>

    <!-- List Extra Part Modal -->
    <div id="itemModal" class="modal-overlay hidden" role="dialog">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3>List an Extra Part</h3>
                <button class="modal-close" id="closeItemModalBtn">×</button>
            </div>
            <form action="marketplace.php" method="POST" enctype="multipart/form-data" class="modal-body">
                <div class="form-group">
                    <label for="item_name">Component Name *</label>
                    <input type="text" id="item_name" name="item_name" placeholder="e.g., Arduino Mega 2560" required>
                </div>

                <div class="form-group">
                    <label for="category">Component Category *</label>
                    <select id="category" name="category" required>
                        <option value="Microcontrollers">Microcontrollers</option>
                        <option value="Sensors & Modules">Sensors & Modules</option>
                        <option value="Motors & Actuators">Motors & Actuators</option>
                        <option value="Power & Wiring">Power & Wiring</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="item_image">Upload Hardware Photo *</label>
                    <input type="file" id="item_image" name="item_image" accept="image/*" required>
                </div>

                <div class="form-group">
                    <label for="price">Starting Price (Rs.) *</label>
                    <input type="number" id="price" name="price" step="0.01" placeholder="e.g., 4500" required>
                </div>

                <div class="form-group">
                    <label for="description">Condition & Description *</label>
                    <textarea id="description" name="description" rows="3" placeholder="Mention if it's new, used, or has bent pins..." required></textarea>
                </div>

                <div class="modal-footer no-padding">
                    <button type="button" class="btn btn-outline-navy" id="cancelItemModalBtn">Cancel</button>
                    <button type="submit" name="submit_item" class="btn btn-navy">Post Item</button>
                </div>
            </form>
        </div>
    </div>

    <script src="marketplace.js?v=<?php echo time(); ?>"></script>

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