// ======================================================
// app.js — Real API Integration
// ======================================================

// ---- Dashboard ----
let chartsRendered = false;
let deptChart, issueChart;

async function loadDashboard() {
    try {
        const m = document.getElementById('dash-month').value;
        const y = document.getElementById('dash-year').value;
        const res = await fetch(`/api/dashboard.php?month=${m}&year=${y}`);
        const json = await res.json();
        if (!json.success) return;
        const d = json.data;

        // Update Label
        const mText = document.getElementById('dash-month').options[document.getElementById('dash-month').selectedIndex].text;
        const yText = document.getElementById('dash-year').options[document.getElementById('dash-year').selectedIndex].text;
        setText('dashboard-period-label', `ข้อมูลสรุปประจำเดือน ${mText} ${yText}`);

        // Update KPI cards
        setText('stat-total-month', d.total_month);
        setText('stat-pending', d.pending);
        setText('stat-pending-detail', `วิกฤต: ${d.critical_pending} | เร่งด่วน: ${d.urgent_pending}`);
        setText('stat-done-month', d.done_month);

        const successRate = d.total_month > 0 ? Math.round((d.done_month / d.total_month) * 100) : 0;
        setText('stat-done-pct', `อัตราความสำเร็จ ${successRate}%`);

        setText('stat-sla-pct', d.sla_pct + '%');

        // Rebuild charts with real data
        renderCharts(d.chart_dept, d.chart_priority);
    } catch (e) { console.error('Dashboard load error:', e); }
}

function renderCharts(deptData, priorityData) {
    const isDark = document.documentElement.classList.contains('dark');
    Chart.defaults.color = isDark ? '#94a3b8' : '#475569';
    Chart.defaults.font.family = "'Sarabun', sans-serif";
    Chart.defaults.scale.grid.color = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';

    if (deptChart) deptChart.destroy();
    if (issueChart) issueChart.destroy();

    const ctxDept = document.getElementById('deptChart').getContext('2d');
    const gradBlue = ctxDept.createLinearGradient(0, 0, 0, 300);
    gradBlue.addColorStop(0, '#00d2ff');
    gradBlue.addColorStop(1, '#3a7bd5');

    deptChart = new Chart(ctxDept, {
        type: 'bar',
        data: {
            labels: deptData.map(r => r.label),
            datasets: [{
                label: 'จำนวนแจ้งซ่อม',
                data: deptData.map(r => r.value),
                backgroundColor: gradBlue,
                borderRadius: 8,
                barPercentage: 0.5,
                hoverBackgroundColor: '#00d2ff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.9)',
                    titleFont: { size: 13 },
                    bodyFont: { size: 12 },
                    padding: 10,
                    cornerRadius: 8,
                    displayColors: false
                }
            },
            scales: {
                y: { beginAtZero: true, grid: { display: true } },
                x: { grid: { display: false } }
            }
        }
    });

    const ctxIssue = document.getElementById('issueChart').getContext('2d');
    issueChart = new Chart(ctxIssue, {
        type: 'doughnut',
        data: {
            labels: priorityData.map(r => {
                const map = { critical: 'วิกฤต', urgent: 'เร่งด่วน', normal: 'ปกติ' };
                return map[r.label] || r.label;
            }),
            datasets: [{
                data: priorityData.map(r => r.value),
                backgroundColor: [
                    '#ff4d4d', // Critical
                    '#fb923c', // Urgent
                    '#22c55e'  // Normal
                ],
                borderWidth: 0,
                hoverOffset: 15,
                weight: 0.5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '75%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        padding: 20,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.9)',
                    padding: 12,
                    cornerRadius: 8
                }
            }
        }
    });
    chartsRendered = true;
}

// ---- Ticket List ----
const PRIORITY_BADGE = {
    critical: '<span class="badge badge-critical">วิกฤต</span>',
    urgent: '<span class="badge badge-urgent">เร่งด่วน</span>',
    normal: '<span class="badge badge-normal">ปกติ</span>',
};
const PRIORITY_BAR = { critical: 'bg-status-critical', urgent: 'bg-status-urgent', normal: 'bg-status-normal' };
const STATUS_LABEL = {
    pending: '<span class="badge-status-pending px-2 py-1 rounded text-xs font-medium">รอดำเนินการ</span>',
    ongoing: '<span class="badge-status-progress px-2 py-1 rounded text-xs font-medium"><i class="fa-solid fa-spinner fa-spin mr-1"></i>กำลังซ่อม</span>',
    completed: '<span class="badge-status-done px-2 py-1 rounded text-xs font-medium"><i class="fa-solid fa-check mr-1"></i>ซ่อมเสร็จสิ้น</span>',
    cancelled: '<span class="px-2 py-1 rounded text-xs font-medium text-gray-400 bg-gray-800">ยกเลิก</span>',
};

async function loadTickets(filters = {}) {
    const isMyJobs = filters.assigned_to === 'me';
    if (isMyJobs) {
        filters.assigned_to = window.CURRENT_USER.id;
    }

    const containerId = isMyJobs ? 'my-jobs-container' : 'ticket-container';
    const container = document.getElementById(containerId);
    if (!container) return;

    container.innerHTML = '<div class="col-span-3 text-center text-slate-400 py-10"><i class="fa-solid fa-spinner fa-spin text-2xl mb-2"></i><p>กำลังโหลด...</p></div>';

    const params = new URLSearchParams(filters).toString();
    try {
        const res = await fetch('/api/tickets.php?' + params);
        const json = await res.json();
        if (!json.success) { container.innerHTML = '<p class="text-red-400 col-span-3 text-center">โหลดข้อมูลไม่สำเร็จ</p>'; return; }

        const tickets = json.data;
        if (!tickets.length) {
            container.innerHTML = `<p class="text-slate-500 col-span-3 text-center py-10">${isMyJobs ? 'คุณยังไม่มีงานที่ได้รับมอบหมาย' : 'ไม่มีรายการแจ้งซ่อม'}</p>`;
            return;
        }

        container.innerHTML = tickets.map(t => `
        <div class="glass-card p-4 relative overflow-hidden group" id="ticket-${t.id}">
            <div class="absolute top-0 left-0 w-1 h-full ${PRIORITY_BAR[t.priority] || ''}"></div>
            <div class="flex justify-between items-start mb-3">
                <div>
                    <span class="text-xs text-text-muted font-mono">${t.ticket_no}</span>
                    <h3 class="text-white font-semibold mt-1 text-sm">${escHtml(t.description).substring(0, 60)}${t.description.length > 60 ? '...' : ''}</h3>
                </div>
                ${PRIORITY_BADGE[t.priority] || ''}
            </div>
            ${window.IS_ADMIN ? `<button type="button" onclick="deleteTicket(${t.id})" class="absolute top-2 right-2 text-text-muted hover:text-red-400 transition-colors opacity-0 group-hover:opacity-100 p-1" title="ลบรายการ"><i class="fa-solid fa-trash-can"></i></button>` : ''}
            <div class="space-y-1 text-xs text-text-muted mb-3">
                <p><i class="fa-solid fa-user w-4 text-center"></i> ${escHtml(t.reporter_name)}</p>
                <p><i class="fa-solid fa-phone w-4 text-center"></i> ${escHtml(t.reporter_phone || '-')}</p>
                ${t.location_room ? `<p><i class="fa-solid fa-location-dot w-4 text-center"></i> ${escHtml(t.location_room)}</p>` : ''}
                ${t.asset_name ? `<p><i class="fa-solid fa-desktop w-4 text-center"></i> ${escHtml(t.asset_name)} ${t.asset_model ? '(' + escHtml(t.asset_model) + ')' : ''} <span class="text-ocean-400 font-mono ml-1">${escHtml(t.asset_code)}</span></p>` : ''}
                <p><i class="fa-solid fa-clock w-4 text-center"></i> ${timeAgo(t.created_at)}</p>
                
                ${t.image_url ? `
                <div class="mt-2">
                    <a href="${t.image_url}" target="_blank" class="block relative group/img overflow-hidden rounded-lg border border-white/10 h-20 w-full">
                        <img src="${t.image_url}" class="w-full h-full object-cover transition-transform group-hover/img:scale-110">
                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover/img:opacity-100 flex items-center justify-center transition-opacity">
                            <i class="fa-solid fa-magnifying-glass-plus text-white text-lg"></i>
                        </div>
                    </a>
                </div>
                ` : ''}
                
                ${(window.IS_ADMIN || (window.CURRENT_USER && window.CURRENT_USER.role === 'staff')) && !t.asset_name ? `
                <div class="mt-3 p-2 bg-white/5 rounded border border-dashed border-white/10">
                    <p class="text-[10px] mb-1.5 text-ocean-400 font-medium">เชื่อมโยงครุภัณฑ์:</p>
                    <div class="flex gap-1.5">
                        <input type="text" id="link-asset-${t.id}" placeholder="รหัสเครื่อง..." 
                            class="flex-1 bg-ocean-900 border border-white/10 rounded px-2 py-1 text-[10px] focus:outline-none focus:border-ocean-500">
                        <button onclick="linkAsset(${t.id})" 
                            class="bg-ocean-700 hover:bg-ocean-600 text-white px-2 rounded text-[10px] transition-colors">
                            เชื่อม
                        </button>
                    </div>
                </div>
                ` : ''}
            </div>
            <div class="flex items-center justify-between border-t border-white/10 pt-3">
                ${STATUS_LABEL[t.status] || ''}
                ${window.IS_ADMIN ? `
                <select onchange="assignTicket(${t.id}, this.value)"
                    class="bg-ocean-800 text-xs text-white border border-white/10 rounded px-2 py-1 focus:outline-none focus:border-ocean-500">
                    <option value="">จ่ายงาน...</option>
                    ${window.STAFF_LIST ? window.STAFF_LIST.map(s => `<option value="${s.id}" ${t.assigned_to == s.id ? 'selected' : ''}>${s.full_name}</option>`).join('') : ''}
                </select>` : `<span class="text-xs text-text-muted">${t.assigned_name ? '👤 ' + escHtml(t.assigned_name) : 'ยังไม่ได้รับมอบหมาย'}</span>`}
            </div>
            ${(t.status !== 'completed' && (window.IS_ADMIN || t.assigned_to == window.CURRENT_USER.id)) ? `
            <div class="mt-2 flex gap-2">
                ${t.status === 'pending' ? `<button onclick="updateStatus(${t.id},'ongoing')"   class="flex-1 text-xs bg-ocean-700 hover:bg-ocean-600 text-white py-1 rounded transition-colors">รับงาน</button>` : ''}
                ${t.status === 'ongoing' ? `<button onclick="updateStatus(${t.id},'completed')" class="flex-1 text-xs bg-green-700 hover:bg-green-600 text-white py-1 rounded transition-colors">✅ ปิดงาน</button>` : ''}
            </div>` : ''}
        </div>`).join('');
    } catch (e) { container.innerHTML = '<p class="text-red-400 col-span-3 text-center">เกิดข้อผิดพลาด</p>'; }
}

window.refreshCurrentTicketsView = function () {
    const myJobsView = document.getElementById('view-my-jobs');
    if (myJobsView && !myJobsView.classList.contains('hidden')) {
        loadTickets({ assigned_to: 'me' });
    } else {
        const searchInput = document.getElementById('ticketSearchInput');
        if (searchInput && searchInput.value.trim()) {
            loadTickets({ search: searchInput.value.trim() });
        } else {
            loadTickets();
        }
    }
};

async function updateStatus(id, status) {
    await fetch('/api/tickets.php', {
        method: 'PUT', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, status })
    });
    window.refreshCurrentTicketsView();
}

window.linkAsset = async function (id) {
    const code = document.getElementById('link-asset-' + id).value.trim();
    if (!code) return showToast('❌ กรุณาระบุรหัสเครื่อง', true);

    try {
        const res = await fetch('/api/tickets.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, asset_code: code })
        });
        const json = await res.json();
        if (json.success) {
            showToast('✅ เชื่อมโยงครุภัณฑ์เรียบร้อย');
            window.refreshCurrentTicketsView();
        } else {
            showToast('❌ ' + json.error, true);
        }
    } catch (e) {
        showToast('❌ เกิดข้อผิดพลาด', true);
    }
};

async function assignTicket(id, assigned_to) {
    await fetch('/api/tickets.php', {
        method: 'PUT', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, assigned_to: assigned_to || null })
    });
    window.refreshCurrentTicketsView();
}

// ---- Search Feature ----
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('ticketSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', debounce((e) => {
            loadTickets({ search: e.target.value });
        }, 500));
    }
});



// ---- Create Ticket Form ----
let isSubmitting = false;
window.previewFile = function (input) {
    const container = document.getElementById('filePreview');
    container.innerHTML = '';
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.size > 5 * 1024 * 1024) {
            showToast('❌ ไฟล์ขนาดใหญ่เกิน 5MB', true);
            input.value = '';
            return;
        }

        container.classList.remove('hidden');
        const reader = new FileReader();
        reader.onload = function (e) {
            container.innerHTML = `
                <div class="relative group">
                    <img src="${e.target.result}" class="w-full h-24 object-cover rounded-lg border border-white/10">
                    <button type="button" onclick="clearFile()" class="absolute -top-2 -right-2 bg-red-500 text-white w-5 h-5 rounded-full text-[10px] flex items-center justify-center shadow-lg">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            `;
        }
        reader.readAsDataURL(file);
    } else {
        container.classList.add('hidden');
    }
};

window.clearFile = function () {
    const input = document.getElementById('ticketFile');
    input.value = '';
    previewFile(input);
};

window.handleFormSubmit = async function (e) {
    e.preventDefault();
    if (isSubmitting) return;

    const form = e.target;
    const btn = form.querySelector('[type=submit]');
    const loader = document.getElementById('page-loader');

    isSubmitting = true;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> กำลังส่ง...';

    if (loader) {
        loader.querySelector('p').textContent = 'กำลังบันทึกข้อมูลและแจ้งเตือนทีมงาน...';
        loader.classList.remove('hidden');
    }

    const formData = new FormData(form);

    try {
        const res = await fetch('/api/tickets.php', {
            method: 'POST',
            body: formData
        });
        const json = await res.json();

        if (json.success) {
            showToast(`✅ สร้าง Ticket ${json.ticket_no} สำเร็จ!`);
            form.reset();
            const assetInfo = document.getElementById('assetInfo');
            if (assetInfo) assetInfo.classList.add('hidden');

            // Redirect after success
            setTimeout(() => {
                if (loader) loader.classList.add('hidden');
                switchView('ticket-list');
                isSubmitting = false;
            }, 500);
        } else {
            showToast('❌ ' + (json.error || 'เกิดข้อผิดพลาด'), true);
            if (loader) loader.classList.add('hidden');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane mr-2"></i> ส่งเรื่องแจ้งซ่อม';
            isSubmitting = false;
        }
    } catch (err) {
        showToast('❌ ไม่สามารถเชื่อมต่อ Server ได้', true);
        if (loader) loader.classList.add('hidden');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane mr-2"></i> ส่งเรื่องแจ้งซ่อม';
        isSubmitting = false;
    }
}

// ---- QR / Asset Lookup ----
window.searchAssetForTicket = async function (codeToSearch = null) {
    const input = document.getElementById('assetIdInput');
    const info = document.getElementById('assetInfo');
    const code = codeToSearch || input.value.trim();

    if (!code) return;

    if (!codeToSearch) {
        input.value = 'กำลังค้นหา...';
        input.classList.add('animate-pulse');
    }

    try {
        const res = await fetch('/api/assets.php?code=' + encodeURIComponent(code));
        const json = await res.json();

        if (input) input.classList.remove('animate-pulse');

        if (json.success && json.data) {
            const a = json.data;
            if (input) input.value = a.asset_code;
            const hiddenId = document.getElementById('hiddenAssetId');
            if (hiddenId) hiddenId.value = a.id;

            if (info) {
                info.innerHTML = `<i class="fa-solid fa-circle-check mr-1"></i> พบ: ${escHtml(a.asset_name)} ${escHtml(a.brand || '')} ${escHtml(a.model || '')}`;
                info.classList.remove('hidden');
            }

            // Auto-fill department if available
            if (a.department_id) {
                const deptSel = document.getElementById('deptSelect');
                if (deptSel && deptSel.tomselect) {
                    deptSel.tomselect.setValue(a.department_id);
                } else if (deptSel) {
                    deptSel.value = a.department_id;
                }
            }
        } else {
            if (input) input.value = code;
            if (info) {
                info.innerHTML = `<i class="fa-solid fa-circle-xmark mr-1 text-red-400"></i> ไม่พบรหัสนี้ในระบบ`;
                info.classList.remove('hidden');
            }
        }
    } catch (e) {
        if (input) {
            input.classList.remove('animate-pulse');
            input.value = code;
        }
    }
};

window.simulateQRScan = function () {
    searchAssetForTicket();
};

// ---- Navigation (switchView) — Full Definition ----
window.switchView = function (viewId) {
    const loader = document.getElementById('page-loader');
    if (loader) loader.classList.remove('hidden');

    // Update sidebar nav highlight
    document.querySelectorAll('.nav-btn').forEach(btn => {
        btn.classList.remove('text-white', 'bg-ocean-700', 'border-l-4', 'border-ocean-500');
        btn.classList.add('text-text-muted');
        if (btn.dataset.target === viewId) {
            btn.classList.remove('text-text-muted');
            btn.classList.add('text-white', 'bg-ocean-700', 'border-l-4', 'border-ocean-500');
            const titleEl = btn.querySelector('span');
            const mobileTitle = document.getElementById('mobile-page-title');
            if (titleEl && mobileTitle) mobileTitle.innerText = titleEl.innerText;
        }
    });

    setTimeout(() => {
        // Hide all sections, show the target one
        document.querySelectorAll('.view-section').forEach(s => s.classList.add('hidden'));
        const target = document.getElementById('view-' + viewId);
        if (target) target.classList.remove('hidden');
        if (loader) loader.classList.add('hidden');

        // Load real data per view
        if (viewId === 'dashboard') loadDashboard();
        if (viewId === 'ticket-list') loadTickets();
        if (viewId === 'my-jobs') loadTickets({ assigned_to: 'me' });
        if (viewId === 'departments') loadDepartments();
        if (viewId === 'assets') loadAssets();
    }, 300);
};

// ---- Department Management ----
window.loadDepartments = async function () {
    const body = document.getElementById('dept-table-body');
    if (!body) return;

    body.innerHTML = '<tr><td colspan="3" class="px-6 py-10 text-center"><i class="fa-solid fa-spinner fa-spin text-ocean-400"></i></td></tr>';

    try {
        const res = await fetch('/api/departments.php');
        const json = await res.json();
        if (json.success) {
            body.innerHTML = json.data.map(d => `
                <tr class="hover:bg-white/5 transition-colors group">
                    <td class="px-6 py-4 text-sm text-white font-medium">${escHtml(d.dept_name)}</td>
                    <td class="px-6 py-4 text-sm text-text-muted text-center italic">ตรวจสอบในหน้าผู้ใช้งาน</td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onclick='openEditDeptModal(${JSON.stringify(d)})' class="p-2 text-ocean-400 hover:text-white transition-colors" title="แก้ไข">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <button onclick="deleteDept(${d.id})" class="p-2 text-red-400 hover:text-white transition-colors" title="ลบ">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }
    } catch (err) {
        body.innerHTML = '<tr><td colspan="3" class="px-6 py-10 text-center text-red-400">โหลดข้อมูลล้มเหลว</td></tr>';
    }
}

window.openAddDeptModal = function () {
    document.getElementById('deptModalTitle').textContent = 'เพิ่มแผนกใหม่';
    document.getElementById('deptForm').reset();
    document.getElementById('dept_id').value = '';
    document.getElementById('deptModal').classList.remove('hidden');
};

window.openEditDeptModal = function (d) {
    document.getElementById('deptModalTitle').textContent = 'แก้ไขข้อมูลแผนก';
    document.getElementById('deptForm').reset();
    document.getElementById('dept_id').value = d.id;
    document.getElementById('dept_name').value = d.dept_name;
    document.getElementById('deptModal').classList.remove('hidden');
};

window.closeDeptModal = function () {
    document.getElementById('deptModal').classList.add('hidden');
};

window.handleDeptSubmit = async function (e) {
    e.preventDefault();
    const form = e.target;
    const data = {
        id: form.id.value,
        dept_name: form.dept_name.value
    };

    const method = data.id ? 'PUT' : 'POST';

    try {
        const res = await fetch('/api/departments.php', {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.success) {
            showToast('✅ บันทึกข้อมูลสำเร็จ');
            closeDeptModal();
            loadDepartments();
        } else {
            alert('❌ ' + json.error);
        }
    } catch (err) {
        alert('❌ เกิดข้อผิดพลาดในการเชื่อมต่อ');
    }
};

window.deleteDept = async function (id) {
    if (!confirm('⚠️ ยืนยันการลบแผนกนี้?')) return;
    try {
        const res = await fetch('/api/departments.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const json = await res.json();
        if (json.success) {
            showToast('✅ ลบข้อมูลสำเร็จ');
            loadDepartments();
        } else {
            alert('❌ ' + json.error);
        }
    } catch (err) {
        alert('❌ เกิดข้อผิดพลาดในการเชื่อมต่อ');
    }
};

// ---- Asset Management ----
window.loadAssets = async function () {
    const body = document.getElementById('asset-table-body');
    const countEl = document.getElementById('asset-count');
    if (!body) return;

    body.innerHTML = '<tr><td colspan="3" class="px-6 py-10 text-center"><i class="fa-solid fa-spinner fa-spin text-ocean-400"></i></td></tr>';

    try {
        const res = await fetch('/api/assets.php');
        const json = await res.json();
        if (json.success) {
            if (countEl) countEl.textContent = `ทั้งหมด ${json.data.length} รายการ`;
            body.innerHTML = json.data.map(a => `
                <tr class="hover:bg-white/5 transition-colors group cursor-pointer" onclick="viewAssetDetails('${a.asset_code}')">
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 bg-ocean-900/50 text-ocean-300 rounded font-mono text-xs border border-ocean-500/30">${escHtml(a.asset_code)}</span>
                    </td>
                    <td class="px-6 py-4">
                        <p class="text-sm text-white font-medium">${escHtml(a.asset_name)}</p>
                        <p class="text-[10px] text-text-muted">${escHtml(a.brand || '-')} ${escHtml(a.model || '')}</p>
                    </td>
                    <td class="px-6 py-4 text-right" onclick="event.stopPropagation()">
                        <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onclick='openEditAssetModal(${JSON.stringify(a)})' class="p-2 text-ocean-400 hover:text-white transition-colors" title="แก้ไข">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <button onclick="deleteAsset(${a.id})" class="p-2 text-red-400 hover:text-white transition-colors" title="ลบ">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }
    } catch (err) {
        body.innerHTML = '<tr><td colspan="3" class="px-6 py-10 text-center text-red-400">โหลดข้อมูลล้มเหลว</td></tr>';
    }
}

window.openAddAssetModal = function () {
    document.getElementById('assetModalTitle').innerHTML = '<i class="fa-solid fa-server text-ocean-400"></i> เพิ่มครุภัณฑ์ใหม่';
    document.getElementById('assetForm').reset();
    document.getElementById('asset_id_hidden').value = '';
    document.getElementById('assetModal').classList.remove('hidden');
};

window.openEditAssetModal = function (a) {
    document.getElementById('assetModalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square text-ocean-400"></i> แก้ไขข้อมูลครุภัณฑ์';
    document.getElementById('assetForm').reset();
    document.getElementById('asset_id_hidden').value = a.id;
    document.getElementById('asset_code_input').value = a.asset_code;
    document.getElementById('asset_name_input').value = a.asset_name;
    document.getElementById('asset_brand_input').value = a.brand || '';
    document.getElementById('asset_model_input').value = a.model || '';
    document.getElementById('asset_serial_input').value = a.serial_number || '';
    document.getElementById('asset_dept_input').value = a.department_id || '';
    document.getElementById('assetModal').classList.remove('hidden');
};

window.closeAssetModal = function () {
    document.getElementById('assetModal').classList.add('hidden');
};

window.handleAssetSubmit = async function (e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    const method = data.id ? 'PUT' : 'POST';

    try {
        const res = await fetch('/api/assets.php', {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.success) {
            showToast('✅ บันทึกข้อมูลสำเร็จ');
            closeAssetModal();
            loadAssets();
        } else {
            alert('❌ ' + json.error);
        }
    } catch (err) {
        alert('❌ เกิดข้อผิดพลาดในการเชื่อมต่อ');
    }
};

window.deleteAsset = async function (id) {
    if (!confirm('⚠️ ยืนยันการลบครุภัณฑ์นี้? (ต้องไม่มีประวัติการซ่อม)')) return;
    try {
        const res = await fetch('/api/assets.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const json = await res.json();
        if (json.success) {
            showToast('✅ ลบข้อมูลสำเร็จ');
            loadAssets();
        } else {
            alert('❌ ' + json.error);
        }
    } catch (err) {
        alert('❌ เกิดข้อผิดพลาดในการเชื่อมต่อ');
    }
};

window.viewAssetDetails = function (code) {
    document.getElementById('assetSearchInput').value = code;
    searchAssetHistory();
};

window.backToAssetList = function () {
    document.getElementById('assetHistoryContainer').classList.add('hidden');
    document.getElementById('assetHistoryContent').classList.add('hidden');
    document.getElementById('assetListContainer').classList.remove('hidden');
    document.getElementById('assetEmptyState').classList.remove('hidden');
};

// ---- Search filter ----
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.querySelector('#view-ticket-list input[type=text]');
    if (searchInput) {
        let debounce;
        searchInput.addEventListener('input', () => {
            clearTimeout(debounce);
            debounce = setTimeout(() => loadTickets({ search: searchInput.value }), 400);
        });
    }
    // Add ticket container to ticket-list view
    const listSection = document.getElementById('view-ticket-list');
    if (listSection) {
        let grid = listSection.querySelector('.grid');
        if (grid) { grid.id = 'ticket-container'; grid.innerHTML = ''; }
    }
});

// ---- Helpers ----
function setText(id, val) { const el = document.getElementById(id); if (el) el.textContent = val; }
function escHtml(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }

function debounce(func, timeout = 300) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => { func.apply(this, args); }, timeout);
    };
}

window.showToast = function (msg, isError = false) {
    const toast = document.getElementById('toast');
    const msgEl = document.getElementById('toast-message');
    const titleEl = toast.querySelector('h4');
    if (!toast || !msgEl) return;

    if (titleEl) {
        titleEl.textContent = isError ? 'ไม่สำเร็จ!' : 'สำเร็จ!';
        titleEl.className = isError ? 'font-bold text-red-400 text-sm' : 'font-bold text-white text-sm';
    }

    const icon = toast.querySelector('i');
    if (icon) {
        icon.className = isError ? 'fa-solid fa-circle-xmark text-red-500 text-xl mr-3' : 'fa-solid fa-circle-check text-status-normal text-xl mr-3';
    }

    msgEl.textContent = msg;
    toast.style.borderLeftColor = isError ? '#ff4d4d' : '#00b4d8';
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3500);
};

function timeAgo(dateStr) {
    const diff = Math.floor((Date.now() - new Date(dateStr)) / 60000);
    if (diff < 1) return 'เมื่อกี้';
    if (diff < 60) return `${diff} นาทีที่แล้ว`;
    if (diff < 1440) return `${Math.floor(diff / 60)} ชม.ที่แล้ว`;
    return `${Math.floor(diff / 1440)} วันที่แล้ว`;
}
// ---- User Management ----
window.openAddUserModal = function () {
    document.getElementById('userModalTitle').textContent = 'เพิ่มผู้ใช้งานใหม่';
    document.getElementById('userForm').reset();
    document.getElementById('user_id').value = '';
    document.getElementById('user_username').disabled = false;
    document.getElementById('userModal').classList.remove('hidden');
};

window.openEditUserModal = function (u) {
    document.getElementById('userModalTitle').textContent = 'แก้ไขข้อมูลผู้ใช้งาน';
    document.getElementById('userForm').reset();
    document.getElementById('user_id').value = u.id;
    document.getElementById('user_username').value = u.username;
    document.getElementById('user_username').disabled = true;
    document.getElementById('user_full_name').value = u.full_name;
    document.getElementById('user_role').value = u.role;
    document.getElementById('user_position_id').value = u.position_id || '';
    document.getElementById('user_phone').value = u.phone || '';
    document.getElementById('userModal').classList.remove('hidden');
};

window.closeUserModal = function () {
    document.getElementById('userModal').classList.add('hidden');
};

window.handleUserSubmit = async function (e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = {};
    formData.forEach((v, k) => data[k] = v);

    const isEdit = !!data.id;
    const url = '/api/users.php';
    const method = isEdit ? 'PUT' : 'POST';

    try {
        const res = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.success) {
            showToast(isEdit ? '✅ อัปเดตข้อมูลสำเร็จ' : '✅ เพิ่มผู้ใช้งานสำเร็จ (รหัสผ่านเริ่มต้น: welcome123)');
            closeUserModal();
            setTimeout(() => window.location.reload(), 1000);
        } else {
            alert('❌ ' + (json.error || 'เกิดข้อผิดพลาด'));
        }
    } catch (err) {
        alert('❌ เกิดข้อผิดพลาดในการเชื่อมต่อ');
    }
};
// ---- Asset Management Logic ----
window.searchAssetHistory = async function () {
    const code = document.getElementById('assetSearchInput').value.trim();
    if (!code) return showToast('❌ กรุณาระบุรหัสครุภัณฑ์', true);

    const loader = document.getElementById('page-loader');
    if (loader) loader.classList.remove('hidden');

    try {
        const res = await fetch('/api/asset_history.php?code=' + encodeURIComponent(code));
        const json = await res.json();

        if (loader) loader.classList.add('hidden');

        if (json.success) {
            renderAssetHistory(json.data);
            document.getElementById('assetEmptyState').classList.add('hidden');
            document.getElementById('assetHistoryContent').classList.remove('hidden');
        } else {
            showToast('❌ ' + json.error, true);
        }
    } catch (err) {
        if (loader) loader.classList.add('hidden');
        showToast('❌ เกิดข้อผิดพลาดในการเชื่อมต่อ', true);
    }
};

function renderAssetHistory(data) {
    const { asset, history, analysis } = data;

    // 1. Details
    const detailEl = document.getElementById('assetDetailView');
    detailEl.innerHTML = `
        <div class="flex justify-between border-b border-white/5 pb-2">
            <span class="text-text-muted">ชื่ออุปกรณ์:</span>
            <span class="text-white font-medium">${escHtml(asset.asset_name)}</span>
        </div>
        <div class="flex justify-between border-b border-white/5 pb-2">
            <span class="text-text-muted">ยี่ห้อ/รุ่น:</span>
            <span class="text-white">${escHtml(asset.brand)} ${escHtml(asset.model)}</span>
        </div>
        <div class="flex justify-between border-b border-white/5 pb-2">
            <span class="text-text-muted">Serial No:</span>
            <span class="text-white font-mono">${escHtml(asset.serial_no || '-')}</span>
        </div>
        <div class="flex justify-between border-b border-white/5 pb-2">
            <span class="text-text-muted">แผนก:</span>
            <span class="text-white">${escHtml(asset.dept_name || 'ไม่ระบุ')}</span>
        </div>
        <div class="flex justify-between border-b border-white/5 pb-2">
            <span class="text-text-muted">วันหมดประกัน:</span>
            <span class="${analysis.warranty_status === 'Expired' ? 'text-status-critical' : 'text-status-completed'}">
                ${asset.warranty_until ? new Date(asset.warranty_until).toLocaleDateString('th-TH') : 'ไม่ระบุ'}
            </span>
        </div>
    `;

    // 2. Analysis
    const analysisEl = document.getElementById('assetAnalysisView');
    const repairScore = analysis.total_repairs >= 5 ? 'ควรพิจารณาแทงจำหน่าย' : (analysis.total_repairs >= 3 ? 'เฝ้าระวัง' : 'ปกติ');
    const scoreColor = analysis.total_repairs >= 5 ? 'bg-status-critical' : (analysis.total_repairs >= 3 ? 'bg-status-urgent' : 'bg-status-completed');

    analysisEl.innerHTML = `
        <div class="p-3 rounded-lg bg-white/5 flex items-center justify-between">
            <span class="text-xs text-text-muted">จำนวนการซ่อมสะสม:</span>
            <span class="text-lg font-bold text-white">${analysis.total_repairs} ครั้ง</span>
        </div>
        <div class="p-3 rounded-lg ${scoreColor}/20 border border-${scoreColor}/30">
            <p class="text-xs text-text-muted mb-1">สถานะการประเมิน:</p>
            <p class="text-sm font-bold ${scoreColor.replace('bg-', 'text-')}">${repairScore}</p>
        </div>
        <p class="text-[10px] text-text-muted italic">* คำนวณจากความถี่ในการแจ้งซ่อมและอายุการใช้งาน</p>
    `;

    // 3. Timeline
    const timelineEl = document.getElementById('assetTimelineView');
    if (history.length === 0) {
        timelineEl.innerHTML = '<p class="text-text-muted text-center py-10">ไม่เคยมีประวัติการแจ้งซ่อม</p>';
    } else {
        timelineEl.innerHTML = history.map(t => `
            <div class="relative">
                <div class="absolute -left-[25px] top-1.5 w-4 h-4 rounded-full border-2 border-ocean-500 bg-ocean-900 z-10"></div>
                <div>
                    <div class="flex justify-between items-start mb-1">
                        <span class="text-xs font-bold text-ocean-400 font-mono">${t.ticket_no}</span>
                        <span class="text-[10px] text-text-muted">${new Date(t.created_at).toLocaleDateString('th-TH')}</span>
                    </div>
                    <p class="text-sm text-white mb-2">${escHtml(t.problem_description)}</p>
                    <div class="flex items-center gap-3 text-[10px]">
                        <span class="px-2 py-0.5 rounded bg-white/5 text-text-muted">สถานะ: ${t.status}</span>
                        <span class="text-text-muted">ช่าง: ${escHtml(t.technician || 'ไม่ระบุ')}</span>
                    </div>
                </div>
            </div>
        `).join('');
    }
}

window.deleteTicket = async function (id) {
    if (!confirm('คุณยืนยันที่จะลบรายการแจ้งซ่อมนี้ใช่หรือไม่?')) return;

    try {
        const res = await fetch('/api/tickets.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const json = await res.json();
        if (json.success) {
            showToast('✅ ลบรายการแจ้งซ่อมเรียบร้อย');
            if (typeof window.refreshCurrentTicketsView === 'function') {
                window.refreshCurrentTicketsView();
            } else if (typeof loadTickets === 'function') {
                loadTickets();
            }
        } else {
            showToast('❌ ' + (json.error || 'ไม่สามารถลบได้'), true);
        }
    } catch (e) {
        showToast('❌ เกิดข้อผิดพลาดในการลบ', true);
    }
};
