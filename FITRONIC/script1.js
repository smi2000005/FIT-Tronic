
(function() {
    "use strict";
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    (function() {
        var hero = document.getElementById('hero');
        var svg = document.getElementById('stringSvg');
        var path = document.getElementById('stringPath');
        var aL = document.getElementById('anchorL');
        var aR = document.getElementById('anchorR');
        var grip = document.getElementById('grip');

        var field = document.createElement('div');
        field.className = 'field';
        hero.insertBefore(field, hero.firstChild);

        var boxes = [];
        var COUNT = 20;
        for (var bi = 0; bi < COUNT; bi++) {
            var el = document.createElement('div');
            var roll = Math.random();
            el.className = 'box' + (roll < 0.18 ? ' acid' : (roll < 0.36 ? ' warm' : ''));
            var bw = 26 + Math.random() * 96;
            var bh = (Math.random() < 0.80) ? bw : (26 + Math.random() * 96); // mostly squares
            el.style.width = bw.toFixed(0) + 'px';
            el.style.height = bh.toFixed(0) + 'px';
            var fx = Math.random(); // 0..1 across hero
            var fy = 0.08 + Math.random() * 0.84; // keep clear of very top/bottom
            el.style.left = (fx * 100).toFixed(2) + '%';
            el.style.top = (fy * 100).toFixed(2) + '%';
            el.style.marginLeft = (-bw / 2) + 'px';
            el.style.marginTop = (-bh / 2) + 'px';
            field.appendChild(el);
            boxes.push({
                el: el,
                fx: fx,
                fy: fy,
                w: bw,
                h: bh,
                rot: (Math.random() * 2 - 1) * 14,
                oy: 0,
                ox: 0,
                vx: 0,
                vy: 0,
                prevRopeY: 0
            });
        }

        var W = 0,
            H = 0,
            baseY = 0;

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
                return {
                    x: b.fx * W,
                    y: b.fy * H,
                    hw: b.w / 2,
                    hh: b.h / 2
                };
            });
            for (var it = 0; it < 16; it++) {
                for (var a = 0; a < pts.length; a++) {
                    for (var c = a + 1; c < pts.length; c++) {
                        var pa = pts[a],
                            pb = pts[c];
                        var dx = pa.x - pb.x,
                            dy = pa.y - pb.y;
                        var ox = (pa.hw + pb.hw + 8) - Math.abs(dx);
                        var oy = (pa.hh + pb.hh + 8) - Math.abs(dy);
                        if (ox > 0 && oy > 0) {
                            if (ox < oy) {
                                var s = (dx < 0 ? -1 : 1) * ox * 0.5;
                                pa.x += s;
                                pb.x -= s;
                            } else {
                                var s2 = (dy < 0 ? -1 : 1) * oy * 0.5;
                                pa.y += s2;
                                pb.y -= s2;
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
        window.addEventListener('resize', function() {
            size();
            relaxHomes();
        });

        var mouseX = 0,
            mouseY = 0,
            active = false;
        var posX = 0,
            posY = 0,
            velX = 0,
            velY = 0;

        hero.addEventListener('pointermove', function(e) {
            var r = hero.getBoundingClientRect();
            mouseX = e.clientX - r.left;
            mouseY = e.clientY - r.top;
            active = true;
        });
        hero.addEventListener('pointerleave', function() {
            active = false;
        });

        function draw() {
            if (W === 0) size();
            posX = (active ? mouseX : W / 2);

            if (reduceMotion) {
                posY = baseY; // flat, no physics
            } else {
                var targetY = active ? mouseY : baseY;
                var k = 0.055,
                    damp = 0.80; // low damping = visible spring bounce
                velY += (targetY - posY) * k;
                velY *= damp;
                posY += velY;
            }

            var cy = 2 * posY - baseY;
            path.setAttribute('d', 'M 0 ' + baseY + ' Q ' + posX + ' ' + cy + ' ' + W + ' ' + baseY);

            grip.setAttribute('cx', posX);
            grip.setAttribute('cy', posY);
            grip.setAttribute('opacity', active ? 1 : 0);

            var ROPE_HALF = 14; // effective rope thickness for contact
            var FRICTION = 0.85;
            var HOME = 0.010; // weak drift back toward origin
            var k3, b;

            function ropeYAt(x) {
                var A = W - 2 * posX,
                    t;
                if (Math.abs(A) < 0.0001) {
                    t = x / W;
                } else {
                    var disc = posX * posX + A * x;
                    if (disc < 0) disc = 0;
                    t = (-posX + Math.sqrt(disc)) / A;
                }
                if (t < 0) t = 0;
                else if (t > 1) t = 1;
                var omt = 1 - t;
                return omt * omt * baseY + 2 * omt * t * cy + t * t * baseY;
            }

            for (k3 = 0; k3 < boxes.length; k3++) {
                b = boxes[k3];
                var hh = b.h / 2,
                    hw = b.w / 2;
                var px = b.fx * W + b.ox;
                var py = b.fy * H + b.oy;

                if (!reduceMotion) {
                    var nearest = ropeYAt(px);
                    var rl = ropeYAt(px - hw),
                        rr = ropeYAt(px + hw);
                    if (Math.abs(rl - py) < Math.abs(nearest - py)) nearest = rl;
                    if (Math.abs(rr - py) < Math.abs(nearest - py)) nearest = rr;

                    var gap = py - nearest; // signed box->rope
                    var pen = (hh + ROPE_HALF) - Math.abs(gap); // contact depth
                    if (pen > 0) {
                        var dir = gap === 0 ? (b.fy < 0.5 ? -1 : 1) : (gap > 0 ? 1 : -1);
                        b.vy += dir * pen * 0.16; // eject out of the rope
                        b.vy += (nearest - b.prevRopeY) * 0.55; // ride the rope's sweep
                    }
                    b.prevRopeY = nearest;
                }

                b.vx += -b.ox * HOME;
                b.vy += -b.oy * HOME;
                b.vx *= FRICTION;
                b.vy *= FRICTION;
                b.ox += b.vx;
                b.oy += b.vy;
            }

            // 2) separation so boxes shove each other apart, never overlap
            if (!reduceMotion) {
                for (var pass = 0; pass < 3; pass++) {
                    for (var a2 = 0; a2 < boxes.length; a2++) {
                        for (var c2 = a2 + 1; c2 < boxes.length; c2++) {
                            var ba = boxes[a2],
                                bb = boxes[c2];
                            var ddx = (ba.fx * W + ba.ox) - (bb.fx * W + bb.ox);
                            var ddy = (ba.fy * H + ba.oy) - (bb.fy * H + bb.oy);
                            var ox2 = (ba.w / 2 + bb.w / 2 + 6) - Math.abs(ddx);
                            var oy2 = (ba.h / 2 + bb.h / 2 + 6) - Math.abs(ddy);
                            if (ox2 > 0 && oy2 > 0) {
                                if (ox2 < oy2) {
                                    var sh = (ddx < 0 ? -1 : 1) * ox2 * 0.30;
                                    ba.ox += sh;
                                    bb.ox -= sh;
                                    ba.vx *= 0.5;
                                    bb.vx *= 0.5;
                                } else {
                                    var sv = (ddy < 0 ? -1 : 1) * oy2 * 0.30;
                                    ba.oy += sv;
                                    bb.oy -= sv;
                                    ba.vy *= 0.5;
                                    bb.vy *= 0.5;
                                }
                            }
                        }
                    }
                }
            }

            // 3) render, clamped inside the hero
            for (k3 = 0; k3 < boxes.length; k3++) {
                var bo = boxes[k3];
                var bhw = bo.w / 2,
                    bhh = bo.h / 2,
                    hx = bo.fx * W,
                    hy = bo.fy * H;
                var minOx = (bhw + 4) - hx,
                    maxOx = (W - bhw - 4) - hx;
                var minOy = (bhh + 4) - hy,
                    maxOy = (H - bhh - 4) - hy;
                if (bo.ox < minOx) {
                    bo.ox = minOx;
                    if (bo.vx < 0) bo.vx = 0;
                } else if (bo.ox > maxOx) {
                    bo.ox = maxOx;
                    if (bo.vx > 0) bo.vx = 0;
                }
                if (bo.oy < minOy) {
                    bo.oy = minOy;
                    if (bo.vy < 0) bo.vy = 0;
                } else if (bo.oy > maxOy) {
                    bo.oy = maxOy;
                    if (bo.vy > 0) bo.vy = 0;
                }
                bo.el.style.transform =
                    'translate(' + bo.ox.toFixed(1) + 'px,' + bo.oy.toFixed(1) + 'px) rotate(' +
                    (bo.rot + bo.oy * 0.10).toFixed(2) + 'deg)';
            }

            requestAnimationFrame(draw);
        }
        posX = W / 2;
        posY = baseY;
        requestAnimationFrame(draw);
    })();

    (function() {
        var section = document.getElementById('projects');
        var ring = document.getElementById('orbitalRing');
        var panels = ring.querySelectorAll('.orbital-panel');
        var count = panels.length;
        var spacing = 0.92;

        function baseRadius() {
            var raw = getComputedStyle(section).getPropertyValue('--orbital-ring-radius');
            return parseFloat(raw) || 360;
        }

        function effectiveRadius() {
            return baseRadius() * spacing;
        }

        function positionPanels() {
            var radius = effectiveRadius();
            panels.forEach(function(panel, index) {
                var angle = (360 / count) * index;
                var tilt = Math.sin((index / count) * Math.PI * 2) * 8;
                panel.style.setProperty('--ry', angle + 'deg');
                panel.style.setProperty('--tz', radius + 'px');
                panel.style.setProperty('--rz', tilt.toFixed(2) + 'deg');
                panel.style.setProperty('--i', index);
            });
        }
        positionPanels();

        var resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(positionPanels, 200);
        });

        var stage = document.getElementById('orbitalStage');
        var parallax = section.querySelector('.orbital-parallax');
        var rotation = 0;
        var velocity = 0;
        var baseDrift = reduceMotion ? 0 : 0.12;
        var friction = 0.94;
        var maxVelocity = 7;
        var dragging = false;
        var lastX = 0;
        var targetX = 0;
        var targetY = 0;
        var currentX = 0;
        var currentY = 0;
        var rangeY = 28;
        var rangeX = 30;
        var biasX = 10;

        function clamp(value, min, max) {
            return Math.max(min, Math.min(max, value));
        }

        if (!reduceMotion) {
            window.addEventListener('mousemove', function(event) {
                var mouseX = (event.clientX / window.innerWidth) - 0.5;
                var mouseY = (event.clientY / window.innerHeight) - 0.5;
                targetY = mouseX * rangeY;
                targetX = (-mouseY * rangeX) + biasX;
            });
        }

        stage.addEventListener('pointerdown', function(event) {
            dragging = true;
            lastX = event.clientX;
            velocity = 0;
            stage.classList.add('dragging');
        });
        window.addEventListener('pointermove', function(event) {
            if (!dragging) return;
            var step = (event.clientX - lastX) * 0.32;
            lastX = event.clientX;
            rotation += step;
            velocity = clamp(step, -maxVelocity, maxVelocity);
        });
        function endDrag() {
            if (!dragging) return;
            dragging = false;
            stage.classList.remove('dragging');
        }
        window.addEventListener('pointerup', endDrag);
        window.addEventListener('pointercancel', endDrag);
        stage.addEventListener('wheel', function(event) {
            if (Math.abs(event.deltaX) > Math.abs(event.deltaY)) {
                event.preventDefault();
                velocity = clamp(velocity + event.deltaX * 0.05, -maxVelocity, maxVelocity);
            }
        }, { passive: false });

        function frame() {
            if (!dragging) {
                rotation += baseDrift + velocity;
                velocity *= friction;
                if (Math.abs(velocity) < 0.0015) velocity = 0;
            }
            ring.style.transform = 'rotateY(' + rotation.toFixed(3) + 'deg)';

            if (!reduceMotion) {
                currentX += (targetX - currentX) * 0.06;
                currentY += (targetY - currentY) * 0.06;
                parallax.style.transform = 'rotateX(' + currentX.toFixed(2) + 'deg) rotateY(' + currentY.toFixed(2) + 'deg)';
            }
            requestAnimationFrame(frame);
        }
        frame();
    })();

    (function() {
        var host = document.getElementById('scatter');
        var foot = document.getElementById('initiate');
        var signupWrap = document.getElementById('signupWrap');
        var signupForm = document.getElementById('signupForm');
        var signinForm = document.getElementById('signinForm');
        var signupTab = document.getElementById('signupTab');
        var signinTab = document.getElementById('signinTab');
        var signinNav = document.querySelector('.nav-signin');
        var signoutNav = document.querySelector('.nav-signout');
        var signinRole = document.getElementById('signinRole');
        var signinIdentity = document.getElementById('signinIdentity');
        var signinIdentityLabel = document.getElementById('signinIdentityLabel');
        var signinStatus = document.getElementById('signinStatus');
        var signupRole = document.getElementById('signupRole');
        var studentFields = document.getElementById('studentFields');
        var instructorFields = document.getElementById('instructorFields');
        var studentId = document.getElementById('studentId');
        var verifyStudentId = document.getElementById('verifyStudentId');
        var studentValidationStatus = document.getElementById('studentValidationStatus');
        var studentPasswordStep = document.getElementById('studentPasswordStep');
        var studentPassword = document.getElementById('studentPassword');
        var verificationTokenInput = document.getElementById('verificationToken');
        var instructorEmail = document.getElementById('instructorEmail');
        var instructorCode = document.getElementById('instructorCode');
        var instructorPassword = document.getElementById('instructorPassword');
        var signupStatus = document.getElementById('signupStatus');
        var text = host.textContent;
        host.textContent = '';

        var verifiedStudentId = '';
        var verificationToken = '';
        var signedInUser = null;

        function showSignedInUser(user) {
            signedInUser = user;
            signinNav.textContent = user.role === 'student'
                ? user.studentId
                : user.instructorUsername;
            signinNav.href = '#hero';
            if (signoutNav) {
                signoutNav.hidden = false;
            }
            signupWrap.hidden = true;
            signupWrap.inert = true;
        }

        function setAuthView(view) {
            var showingSignIn = view === 'signin';
            signinForm.hidden = !showingSignIn;
            signupForm.hidden = showingSignIn;
            signinTab.setAttribute('aria-selected', String(showingSignIn));
            signupTab.setAttribute('aria-selected', String(!showingSignIn));
        }

        signupTab.addEventListener('click', function() {
            setAuthView('signup');
        });

        signinTab.addEventListener('click', function() {
            setAuthView('signin');
        });

        signinNav.addEventListener('click', function() {
            if (signedInUser) return;
            setAuthView('signin');
        });

        var authParams = new URLSearchParams(window.location.search);
        var authMessage = authParams.get('authMessage');
        if (authMessage || authParams.has('authMode')) {
            var authMode = authParams.get('authMode') === 'signin' ? 'signin' : 'signup';
            setAuthView(authMode);
            if (authMessage) {
                (authMode === 'signin' ? signinStatus : signupStatus).textContent = authMessage;
            }
        }

        async function postJson(path, payload) {
            var response;
            try {
                response = await fetch(path, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
            } catch (error) {
                console.error('Signup service request failed.', error);
                throw new Error('Could not reach the signup service. Try again later.');
            }

            var result;
            try {
                result = await response.json();
            } catch (error) {
                console.error('Signup service returned an invalid response.', error);
                throw new Error('The signup service returned an invalid response.');
            }
            if (!response.ok) {
                var requestError = new Error(result.error || 'The signup request could not be completed.');
                requestError.code = result.code || '';
                throw requestError;
            }
            return result;
        }

        async function restoreSignedInUser() {
            try {
                var response = await fetch('server/api.php?route=session', {
                    headers: { 'Accept': 'application/json' },
                    cache: 'no-store'
                });
                if (response.status === 401) return;
                if (!response.ok) {
                    throw new Error('Could not check the current sign-in session.');
                }
                var result = await response.json();
                if (!result.user ||
                    (result.user.role === 'student' && !result.user.studentId) ||
                    (result.user.role === 'instructor' && !result.user.instructorUsername)) {
                    throw new Error('The sign-in session returned incomplete user details.');
                }
                showSignedInUser(result.user);
            } catch (error) {
                console.error('Could not restore the sign-in session.', error);
            }
        }

        restoreSignedInUser();

        function resetStudentApproval(message) {
            verifiedStudentId = '';
            verificationToken = '';
            verificationTokenInput.value = '';
            studentPasswordStep.hidden = true;
            studentPassword.disabled = true;
            studentPassword.value = '';
            studentValidationStatus.textContent = message;
        }

        signupRole.addEventListener('change', function() {
            var isStudent = signupRole.value === 'student';
            var isInstructor = signupRole.value === 'instructor';
            studentFields.hidden = !isStudent;
            studentFields.disabled = !isStudent;
            instructorFields.hidden = !isInstructor;
            instructorFields.disabled = !isInstructor;
            studentId.value = '';
            instructorEmail.value = '';
            instructorCode.value = '';
            instructorPassword.value = '';
            resetStudentApproval('Verify your student ID to continue.');
            signupStatus.textContent = '';
        });

        signinRole.addEventListener('change', function() {
            var isInstructor = signinRole.value === 'instructor';
            signinIdentityLabel.textContent = isInstructor ? 'Email' : 'Student ID';
            signinIdentity.type = isInstructor ? 'email' : 'text';
            signinIdentity.autocomplete = isInstructor ? 'email' : 'username';
            signinIdentity.maxLength = isInstructor ? 254 : 64;
            signinIdentity.value = '';
            signinStatus.textContent = '';
        });

        studentId.addEventListener('input', function() {
            if (verificationToken && studentId.value.trim() !== verifiedStudentId) {
                resetStudentApproval('Student ID changed. Verify it again to unlock the password field.');
                signupStatus.textContent = '';
            }
        });

        verifyStudentId.addEventListener('click', async function() {
            var studentIdValue = studentId.value.trim();
            if (!studentIdValue) {
                studentValidationStatus.textContent = 'Enter your student ID first.';
                studentId.focus();
                return;
            }
            verifyStudentId.disabled = true;
            studentValidationStatus.textContent = 'Checking your student ID…';
            signupStatus.textContent = '';
            try {
                var result = await postJson('server/api.php?route=students/verify', { studentId: studentIdValue });
                if (signupRole.value !== 'student' || studentId.value.trim() !== studentIdValue) return;
                if (!result.verificationToken) {
                    throw new Error('The signup service did not return a verification token.');
                }
                verifiedStudentId = studentIdValue;
                verificationToken = result.verificationToken;
                verificationTokenInput.value = verificationToken;
                studentPasswordStep.hidden = false;
                studentPassword.disabled = false;
                studentValidationStatus.textContent = result.message || 'Student ID verified. Set a password to continue.';
            } catch (error) {
                console.error('Student ID verification failed.', error);
                studentValidationStatus.textContent = error.message;
            } finally {
                verifyStudentId.disabled = false;
            }
        });

        // Keep account access hidden until the title reaches its fully scattered state.
        function setFormVisibility(c) {
            var visible = c >= 1;
            signupWrap.style.opacity = visible ? '1' : '0';
            signupWrap.style.transform = visible ? 'translateY(0)' : 'translateY(26px)';
            signupWrap.style.pointerEvents = visible ? 'auto' : 'none';
            signupWrap.inert = !visible;
        }

        var glyphs = [];
        for (var i = 0; i < text.length; i++) {
            var ch = text[i];
            var span = document.createElement('span');
            span.className = 'glyph';
            if (ch === ' ') {
                span.classList.add('space');
                span.innerHTML = '&nbsp;';
            } else {
                span.textContent = ch;
                if (ch === '/') span.classList.add('slash');
            }
            host.appendChild(span);
            glyphs.push(span);
        }

        function overlaps(a, b) {
            return a.left < b.right && a.right > b.left &&
                a.top < b.bottom && a.bottom > b.top;
        }

        // (re)roll scatter targets inside the footer and outside the form.
        function rollVectors() {
            var unit = Math.min(window.innerWidth, window.innerHeight) / 100;
            var currentTransforms = glyphs.map(function(g) {
                return g.style.transform;
            });
            glyphs.forEach(function(g) {
                g.style.transform = 'none';
            });
            var origins = glyphs.map(function(g) {
                return g.getBoundingClientRect();
            });
            var formRect = signupWrap.getBoundingClientRect();
            var sectionRect = foot.getBoundingClientRect();
            var topbar = document.querySelector('.topbar');
            var topbarHeight = topbar ? topbar.getBoundingClientRect().height : 0;
            glyphs.forEach(function(g, i) {
                g.style.transform = currentTransforms[i];
            });

            var safeArea = {
                left: formRect.left - 12,
                right: formRect.right + 12,
                top: formRect.top - 12,
                bottom: formRect.bottom + 12
            };
            var bounds = {
                left: sectionRect.left + 12,
                right: sectionRect.right - 12,
                top: sectionRect.top + topbarHeight + 16,
                bottom: sectionRect.bottom - 72
            };
            var placed = [];

            glyphs.forEach(function(g, i) {
                var origin = origins[i];
                var x = origin.left + origin.width / 2;
                var y = origin.top + origin.height / 2;
                var tx = 0;
                var ty = 0;
                var rot = 0;
                var placedGlyph = null;

                for (var attempt = 0; attempt < 500 && !placedGlyph; attempt++) {
                    tx = (Math.random() * 2 - 1) * 60 * unit;
                    ty = (Math.random() * 2 - 1) * 50 * unit;
                    rot = (Math.random() * 2 - 1) * 70;
                    var radians = rot * Math.PI / 180;
                    var rotatedWidth = Math.abs(origin.width * Math.cos(radians)) +
                        Math.abs(origin.height * Math.sin(radians));
                    var rotatedHeight = Math.abs(origin.width * Math.sin(radians)) +
                        Math.abs(origin.height * Math.cos(radians));
                    var candidate = {
                        left: x + tx - rotatedWidth / 2 - 3,
                        right: x + tx + rotatedWidth / 2 + 3,
                        top: y + ty - rotatedHeight / 2 - 3,
                        bottom: y + ty + rotatedHeight / 2 + 3
                    };
                    var withinBounds = candidate.left >= bounds.left &&
                        candidate.right <= bounds.right &&
                        candidate.top >= bounds.top &&
                        candidate.bottom <= bounds.bottom;
                    var clearOfForm = !overlaps(candidate, safeArea);
                    var clearOfLetters = placed.every(function(other) {
                        return !overlaps(candidate, other);
                    });
                    if (withinBounds && clearOfForm && clearOfLetters) {
                        placedGlyph = candidate;
                    }
                }

                if (!placedGlyph) {
                    rot = 0;
                    var stepX = Math.max(48, origin.width + 10);
                    var stepY = Math.max(48, origin.height + 10);
                    for (var gridY = bounds.top + origin.height / 2 + 3;
                         gridY <= bounds.bottom - origin.height / 2 - 3 && !placedGlyph;
                         gridY += stepY) {
                        for (var gridX = bounds.left + origin.width / 2 + 3;
                             gridX <= bounds.right - origin.width / 2 - 3 && !placedGlyph;
                             gridX += stepX) {
                            var gridCandidate = {
                                left: gridX - origin.width / 2 - 3,
                                right: gridX + origin.width / 2 + 3,
                                top: gridY - origin.height / 2 - 3,
                                bottom: gridY + origin.height / 2 + 3
                            };
                            var gridClearOfForm = !overlaps(gridCandidate, safeArea);
                            var gridClearOfLetters = placed.every(function(other) {
                                return !overlaps(gridCandidate, other);
                            });
                            if (gridClearOfForm && gridClearOfLetters) {
                                tx = gridX - x;
                                ty = gridY - y;
                                placedGlyph = gridCandidate;
                            }
                        }
                    }
                }

                if (placedGlyph) {
                    placed.push(placedGlyph);
                } else {
                    tx = 0;
                    ty = bounds.top - y + origin.height / 2;
                    rot = 0;
                }

                g.dataset.tx = tx.toFixed(1);
                g.dataset.ty = ty.toFixed(1);
                g.dataset.rot = rot.toFixed(1);
            });
        }
        var cur = 0,
            manualLock = false,
            exploded = false;

        rollVectors();
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(function() {
                rollVectors();
            });
        }

        function clamp(v, a, b) {
            return v < a ? a : (v > b ? b : v);
        }

        function apply(v) {
            for (var i = 0; i < glyphs.length; i++) {
                var g = glyphs[i];
                g.style.transform =
                    'translate(' + (parseFloat(g.dataset.tx) * v) + 'px,' +
                    (parseFloat(g.dataset.ty) * v) + 'px) rotate(' +
                    (parseFloat(g.dataset.rot) * v) + 'deg)';
            }
        }

        function scrollProgress() {
            var r = foot.getBoundingClientRect();
            var vh = window.innerHeight;
            var p = clamp((vh - r.top) / vh, 0, 1);
            return p * p;
        }

        // cur = animated 0..1. Before any click: scrubs with scroll.
        // After a click: locks and animates between states on each tap.
        host.addEventListener('click', function() {
            if (reduceMotion) return;
            if (!manualLock) {
                exploded = cur > 0.5;
                manualLock = true;
            }
            var willExplode = !exploded;
            if (willExplode) rollVectors(); // fresh burst pattern every time
            exploded = willExplode;
        });

        function loop() {
            if (reduceMotion) {
                apply(0);
                setFormVisibility(1);
                requestAnimationFrame(loop);
                return;
            }
            var target = manualLock ? (exploded ? 1 : 0) : scrollProgress();
            if (manualLock) {
                cur += (target - cur) * 0.12; // smooth spring-like toggle
                if (Math.abs(target - cur) < 0.001) cur = target;
            } else {
                cur = target; // 1:1 scroll scrub
            }
            apply(cur);
            setFormVisibility(cur);
            requestAnimationFrame(loop);
        }
        requestAnimationFrame(loop);

        window.addEventListener('resize', function() {
            if (!exploded) rollVectors(); // refresh only when assembled (no jump)
        });
    })();

    /* =====================================================
       NAV // adapt over the light footer section
       ===================================================== */
    (function() {
        var topbar = document.querySelector('.topbar');
        var light = document.getElementById('initiate');
        if (!topbar || !light) return;
        var navH = 64;

        function sync() {
            var r = light.getBoundingClientRect();
            topbar.classList.toggle('on-light', r.top <= navH && r.bottom >= 0);
        }
        window.addEventListener('scroll', sync, {
            passive: true
        });
        window.addEventListener('resize', sync);
        sync();
    })();

})();
