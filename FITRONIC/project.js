

const ROOM_CONFIG = {
    'Project Room 01': { tables: 6, equipment: 'Soldering Station + DC Bench Supply' },
    'Project Room 02': { tables: 6, equipment: '3D Printer Bay + Oscilloscope' },
    'Study Room 01':   { tables: 6, equipment: '4-Seat Quiet Coding Desk + Power Strip' },
    'Study Room 02':   { tables: 6, equipment: 'Whiteboard + CAD Display Monitor' }
};

function getTodayISO() {
    return new Date().toISOString().split('T')[0];
}

function generateHardwareArt(theme, label) {
    const palettes = {
        robotics: { bg1: '#0b192c', bg2: '#1b385e', accent: '#38bdf8', badge: 'ROBOTICS LAB' },
        iot:      { bg1: '#0f2942', bg2: '#0284c7', accent: '#7dd3fc', badge: 'IoT TELEMETRY' },
        pcb:      { bg1: '#102a43', bg2: '#1e3a8a', accent: '#38bdf8', badge: 'EMBEDDED PCB' },
        energy:   { bg1: '#0b192c', bg2: '#0f766e', accent: '#5eead4', badge: 'POWER SYSTEMS' }
    };
    const p = palettes[theme] || palettes.pcb;
    const svg = `
    <svg xmlns="http://www.w3.org/2000/svg" width="640" height="360" viewBox="0 0 640 360">
        <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="${p.bg1}"/><stop offset="100%" stop-color="${p.bg2}"/></linearGradient></defs>
        <rect width="640" height="360" fill="url(#g)"/>
        <g stroke="rgba(255,255,255,0.07)" stroke-width="1">
            <line x1="0" y1="60" x2="640" y2="60"/><line x1="0" y1="120" x2="640" y2="120"/><line x1="0" y1="180" x2="640" y2="180"/><line x1="0" y1="240" x2="640" y2="240"/>
            <line x1="120" y1="0" x2="120" y2="360"/><line x1="240" y1="0" x2="240" y2="360"/><line x1="360" y1="0" x2="360" y2="360"/><line x1="480" y1="0" x2="480" y2="360"/>
        </g>
        <path d="M90 180 H210 L235 155 H270" fill="none" stroke="${p.accent}" stroke-width="3" opacity="0.7"/><path d="M550 180 H430 L405 205 H370" fill="none" stroke="${p.accent}" stroke-width="3" opacity="0.7"/>
        <circle cx="90" cy="180" r="5" fill="${p.accent}"/><circle cx="550" cy="180" r="5" fill="${p.accent}"/>
        <rect x="260" y="115" width="120" height="110" rx="12" fill="rgba(6,16,31,0.75)" stroke="${p.accent}" stroke-width="3"/>
        <rect x="285" y="140" width="70" height="60" rx="6" fill="none" stroke="#ffffff" stroke-width="2" opacity="0.85"/>
        <circle cx="320" cy="170" r="10" fill="${p.accent}"/>
        <text x="320" y="285" font-family="Segoe UI, sans-serif" font-size="18" font-weight="700" fill="#ffffff" text-anchor="middle">${label}</text>
        <text x="320" y="310" font-family="Segoe UI, sans-serif" font-size="12" font-weight="600" fill="${p.accent}" letter-spacing="2" text-anchor="middle">${p.badge}</text>
    </svg>`;
    return `data:image/svg+xml;utf8,${encodeURIComponent(svg)}`;
}

function escapeHTML(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', () => {
    // 1. SAFELY LOAD MYSQL DATA
    const appData = (typeof window.APP_DATA !== 'undefined') ? window.APP_DATA : {};
    const reservations = Array.isArray(appData.reservations) ? appData.reservations : [];
    const projects = Array.isArray(appData.projects) ? appData.projects : [];

    // 2. STATE VARIABLES
    let currentRoom = 'Project Room 01';
    let currentDate = getTodayISO();
    let currentSlot = '08:00 AM - 10:00 AM';
    let selectedTable = null;
    let activeCategory = 'All';
    let searchQuery = '';
    let activeDetailProjectId = null;

    // 3. DOM ELEMENTS
    const roomTabs = document.querySelectorAll('.room-tab');
    const roomSelect = document.getElementById('room-select');
    const dateSelect = document.getElementById('date-select');
    const slotSelect = document.getElementById('slot-select');
    const tablesGrid = document.getElementById('tables-grid');
    const bookingForm = document.getElementById('booking-form');
    const projectsGrid = document.getElementById('projects-grid');

    // 4. RESERVATIONS LOGIC
    function initializeReservationControls() {
        if (dateSelect) {
            dateSelect.value = currentDate;
            dateSelect.min = getTodayISO();
            dateSelect.addEventListener('change', (e) => {
                currentDate = e.target.value || getTodayISO();
                selectedTable = null;
                renderTablesGrid();
                updateBookingSummary();
            });
        }
        if (roomSelect) {
            roomSelect.value = currentRoom;
            roomSelect.addEventListener('change', (e) => setActiveRoom(e.target.value));
        }
        if (slotSelect) {
            slotSelect.value = currentSlot;
            slotSelect.addEventListener('change', (e) => {
                currentSlot = e.target.value;
                selectedTable = null;
                renderTablesGrid();
                updateBookingSummary();
            });
        }
        roomTabs.forEach(tab => {
            tab.addEventListener('click', () => setActiveRoom(tab.dataset.room));
        });
        if (bookingForm) {
            bookingForm.addEventListener('submit', (e) => {
                if (!selectedTable) {
                    e.preventDefault();
                    const feedback = document.getElementById('booking-feedback');
                    if (feedback) {
                        feedback.textContent = 'Please click an available (green) table on the floor layout first.';
                        feedback.className = 'alert error';
                        feedback.classList.remove('hidden');
                    }
                } else {
                    document.getElementById('form-room').value = currentRoom;
                    document.getElementById('form-table').value = selectedTable;
                    document.getElementById('form-date').value = currentDate;
                    document.getElementById('form-slot').value = currentSlot;
                }
            });
        }
    }

    function setActiveRoom(roomName) {
        currentRoom = roomName;
        if (roomSelect) roomSelect.value = roomName;
        selectedTable = null;
        roomTabs.forEach(tab => tab.classList.toggle('active', tab.dataset.room === roomName));
        renderTablesGrid();
        updateBookingSummary();
    }

    function renderTablesGrid() {
        if (!tablesGrid) return;
        const config = ROOM_CONFIG[currentRoom];
        if (!config) return;
        
        const floorTitle = document.getElementById('floor-room-title');
        if (floorTitle) floorTitle.textContent = currentRoom;
        
        tablesGrid.innerHTML = '';
        let availableCount = 0;

        for (let tableNum = 1; tableNum <= config.tables; tableNum++) {
            const existingBooking = reservations.find(res =>
                res.room === currentRoom && res.date === currentDate && res.timeSlot === currentSlot && res.tableNumber === tableNum
            );

            const isReserved = Boolean(existingBooking);
            const isSelected = !isReserved && selectedTable === tableNum;
            if (!isReserved) availableCount++;

            const card = document.createElement('div');
            let statusClass = isReserved ? 'reserved' : (isSelected ? 'selected' : 'available');
            let statusLabel = isReserved ? 'Reserved' : (isSelected ? 'Selected' : 'Available');

            card.className = `table-card ${statusClass}`;
            card.innerHTML = `
                <div class="table-card-top">
                    <span class="table-number">Table 0${tableNum}</span>
                    <span class="table-badge">${statusLabel}</span>
                </div>
                <div class="table-specs">${config.equipment}</div>
                ${isReserved
                    ? `<div class="table-booked-by">Reserved by ${escapeHTML(existingBooking.groupNo)}</div>`
                    : `<div class="table-specs" style="margin-top:0.35rem; font-weight:600; color:${isSelected ? '#ea580c' : '#16a34a'};">${isSelected ? 'Ready to book' : 'Click to select'}</div>`
                }
            `;

            card.addEventListener('click', () => {
                if (isReserved) return;
                selectedTable = (selectedTable === tableNum) ? null : tableNum;
                const feedback = document.getElementById('booking-feedback');
                if (feedback) feedback.classList.add('hidden');
                renderTablesGrid();
                updateBookingSummary();
            });
            tablesGrid.appendChild(card);
        }
        
        const counter = document.getElementById('availability-counter');
        if (counter) counter.textContent = `${availableCount} / ${config.tables} Available`;
    }

    function updateBookingSummary() {
        const sumRoom = document.getElementById('summary-room');
        const sumDate = document.getElementById('summary-datetime');
        const sumTable = document.getElementById('summary-table');
        if (sumRoom) sumRoom.textContent = currentRoom;
        if (sumDate) sumDate.textContent = `${currentDate} • ${currentSlot}`;
        if (sumTable) sumTable.textContent = selectedTable ? `Table 0${selectedTable}` : 'None Selected';
    }

    function renderReservationsList() {
        const list = document.getElementById('reservations-list');
        const badge = document.getElementById('total-bookings-badge');
        if (badge) badge.textContent = `${reservations.length} Active`;
        if (!list) return;

        if (reservations.length === 0) {
            list.innerHTML = `<p class="muted-text">No active room reservations yet.</p>`;
            return;
        }

        list.innerHTML = '';
        reservations.forEach(res => {
            const item = document.createElement('div');
            item.className = 'reservation-item';
            item.innerHTML = `
                <div class="res-info">
                    <h4>${escapeHTML(res.room)} — Table 0${res.tableNumber}</h4>
                    <p><strong>${escapeHTML(res.groupNo)}</strong> (${escapeHTML(res.studentName)})</p>
                    <p>${escapeHTML(res.date)} • ${escapeHTML(res.timeSlot)}</p>
                    <p><em>${escapeHTML(res.purpose)}</em></p>
                </div>
                <form method="POST" action="projects.php" style="margin:0;">
                    <input type="hidden" name="reservation_id" value="${res.id}">
                    <button type="submit" name="cancel_booking" class="btn-cancel-res">Cancel</button>
                </form>
            `;
            list.appendChild(item);
        });
    }

    // 5. PROJECTS SHOWCASE LOGIC
    function initializeProjectsShowcase() {
        const searchInput = document.getElementById('project-search');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                searchQuery = e.target.value.trim().toLowerCase();
                renderProjectCards();
            });
        }

        document.querySelectorAll('.filter-chip').forEach(btn => {
            btn.addEventListener('click', () => {
                activeCategory = btn.dataset.category;
                document.querySelectorAll('.filter-chip').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                renderProjectCards();
            });
        });

        const addBtn = document.getElementById('open-add-modal-btn');
        const addModal = document.getElementById('add-project-modal');
        if (addBtn && addModal) {
            addBtn.addEventListener('click', () => {
                addModal.classList.remove('hidden');
            });
        }

        const newProg = document.getElementById('new-progress');
        if(newProg) newProg.addEventListener('input', () => document.getElementById('new-progress-val').textContent = newProg.value);

        const editProg = document.getElementById('edit-progress');
        if(editProg) editProg.addEventListener('input', () => document.getElementById('edit-progress-val').textContent = editProg.value);

        const editTrigger = document.getElementById('detail-edit-trigger');
        if (editTrigger) {
            editTrigger.addEventListener('click', () => {
                if (activeDetailProjectId) {
                    document.getElementById('details-modal').classList.add('hidden');
                    openEditModal(activeDetailProjectId);
                }
            });
        }

        document.querySelectorAll('[data-close-modal]').forEach(btn => {
            btn.addEventListener('click', () => {
                const targetModal = document.getElementById(btn.getAttribute('data-close-modal'));
                if (targetModal) targetModal.classList.add('hidden');
            });
        });

        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) overlay.classList.add('hidden');
            });
        });
    }

    function renderProjectCards() {
        if (!projectsGrid) return;
        
        const filtered = projects.filter(proj => {
            const matchesCategory = activeCategory === 'All' || proj.category === activeCategory;
            const matchesQuery = proj.title.toLowerCase().includes(searchQuery) || proj.groupNo.toLowerCase().includes(searchQuery);
            return matchesCategory && matchesQuery;
        });

        projectsGrid.innerHTML = '';
        if (filtered.length === 0) {
            projectsGrid.innerHTML = `<div class="card" style="grid-column: 1 / -1; text-align: center; padding: 2.5rem;"><h3>No matching project groups found</h3></div>`;
            return;
        }

        filtered.forEach(proj => {
            const card = document.createElement('article');
            card.className = 'project-card';
            const isCompleted = proj.progress >= 100;

            card.innerHTML = `
                <div class="card-media">
                    <img src="${generateHardwareArt(proj.imageTheme, proj.groupNo)}" alt="${escapeHTML(proj.title)}">
                    <span class="group-badge">${escapeHTML(proj.groupNo)}</span>
                    <span class="category-tag">${escapeHTML(proj.category)}</span>
                </div>
                <div class="card-content">
                    <h3 class="project-title">${escapeHTML(proj.title)}</h3>
                    <div class="progress-box">
                        <div class="progress-top"><span class="stage-label">${escapeHTML(proj.stage)}</span><span class="percent-label">${proj.progress}% Complete</span></div>
                        <div class="progress-track"><div class="progress-fill ${isCompleted ? 'completed' : ''}" style="width: ${proj.progress}%;"></div></div>
                    </div>
                    <p class="project-desc">${escapeHTML(proj.description)}</p>
                    <div class="card-actions">
                        <button class="btn btn-navy btn-sm view-details-btn">View Details</button>
                        <button class="btn btn-outline-navy btn-sm edit-project-btn">Update</button>
                    </div>
                </div>
            `;

            card.querySelector('.view-details-btn').addEventListener('click', () => openDetailsModal(proj.id));
            card.querySelector('.edit-project-btn').addEventListener('click', () => openEditModal(proj.id));
            projectsGrid.appendChild(card);
        });
    }

    function openDetailsModal(projectId) {
        const proj = projects.find(p => p.id == projectId);
        if (!proj) return;
        activeDetailProjectId = proj.id;

        document.getElementById('detail-group-badge').textContent = proj.groupNo;
        document.getElementById('detail-title').textContent = proj.title;
        document.getElementById('detail-image').src = generateHardwareArt(proj.imageTheme, proj.title);
        document.getElementById('detail-category').textContent = proj.category;
        document.getElementById('detail-stage').textContent = proj.stage;
        document.getElementById('detail-progress-fill').style.width = `${proj.progress}%`;
        document.getElementById('detail-progress-text').textContent = `${proj.progress}%`;
        document.getElementById('detail-members').textContent = proj.members;
        document.getElementById('detail-full-desc').textContent = proj.description;

        const componentsContainer = document.getElementById('detail-components');
        if (componentsContainer) {
            componentsContainer.innerHTML = '';
            const compArray = Array.isArray(proj.components) ? proj.components : (proj.components || '').split(',');
            compArray.forEach(comp => {
                if (!comp.trim()) return;
                const span = document.createElement('span');
                span.className = 'component-tag';
                span.textContent = comp.trim();
                componentsContainer.appendChild(span);
            });
        }
        document.getElementById('details-modal').classList.remove('hidden');
    }

    function openEditModal(projectId) {
        const proj = projects.find(p => p.id == projectId);
        if (!proj) return;
        document.getElementById('edit-project-id').value = proj.id;
        document.getElementById('edit-group-badge').textContent = proj.groupNo;
        document.getElementById('edit-project-heading').textContent = proj.title;
        document.getElementById('edit-stage').value = proj.stage;
        document.getElementById('edit-progress').value = proj.progress;
        document.getElementById('edit-progress-val').textContent = proj.progress;
        document.getElementById('edit-description').value = proj.description;
        document.getElementById('edit-project-modal').classList.remove('hidden');
    }

    // 6. FIRE EVERYTHING SAFELY
    // By isolating these, if one function encounters missing data, the rest still execute
    const bootSequence = [
        initializeReservationControls,
        renderTablesGrid,
        updateBookingSummary,
        renderReservationsList,
        initializeProjectsShowcase,
        renderProjectCards
    ];

    bootSequence.forEach(func => {
        try {
            func();
        } catch (err) {
            console.error(`UI Engine Error in ${func.name}:`, err);
        }
    });
});