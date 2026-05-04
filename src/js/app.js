// ======================================================
// app.js — Real API Integration
// ======================================================

// ---- Dashboard ----
let chartsRendered = false;
let deptChart, issueChart;

async function loadDashboard() {
    try {
        const res  = await fetch('/api/dashboard.php');
        const json = await res.json();
        if (!json.success) return;
        const d = json.data;

        // Update KPI cards
        setText('kpi-total',   d.total_month);
        setText('kpi-pending', d.pending);
        setText('kpi-done',    d.done_month);
        setText('kpi-sla',     d.sla_pct + '%');
        setText('kpi-critical',`วิกฤต: ${d.critical_pending} | เร่งด่วน: ${d.urgent_pending}`);
        setText('kpi-sla-label', d.sla_pct >= 90 ? 'ซ่อมทันเวลาตามเกณฑ์ ✅' : 'ต่ำกว่าเกณฑ์ ⚠️');

        // Rebuild charts with real data
        renderCharts(d.chart_dept, d.chart_priority);
    } catch(e) { console.error('Dashboard load error:', e); }
}

function renderCharts(deptData, priorityData) {
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.font.family = "'Sarabun', sans-serif";
    Chart.defaults.scale.grid.color = 'rgba(255,255,255,0.05)';

    if (deptChart) deptChart.destroy();
    if (issueChart) issueChart.destroy();

    const ctxDept = document.getElementById('deptChart').getContext('2d');
    deptChart = new Chart(ctxDept, {
        type: 'bar',
        data: {
            labels: deptData.map(r => r.label),
            datasets: [{ label: 'จำนวนแจ้งซ่อม', data: deptData.map(r => r.value),
                backgroundColor: '#00b4d8', borderRadius: 4, barPercentage: 0.6 }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });

    const PRIORITY_LABEL = { critical: '🔴 วิกฤต', urgent: '🟠 เร่งด่วน', normal: '🟢 ปกติ' };
    const ctxIssue = document.getElementById('issueChart').getContext('2d');
    issueChart = new Chart(ctxIssue, {
        type: 'doughnut',
        data: {
            labels: priorityData.map(r => PRIORITY_LABEL[r.label] || r.label),
            datasets: [{ data: priorityData.map(r => r.value),
                backgroundColor: ['#ff4d4d','#ff9f43','#2ed573'], borderWidth: 0, hoverOffset: 4 }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: '70%',
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } } }
    });
    chartsRendered = true;
}

// ---- Ticket List ----
const PRIORITY_BADGE = {
    critical: '<span class="badge badge-critical">วิกฤต</span>',
    urgent:   '<span class="badge badge-urgent">เร่งด่วน</span>',
    normal:   '<span class="badge badge-normal">ปกติ</span>',
};
const PRIORITY_BAR = { critical: 'bg-status-critical', urgent: 'bg-status-urgent', normal: 'bg-status-normal' };
const STATUS_LABEL = {
    pending:   '<span class="badge-status-pending px-2 py-1 rounded text-xs font-medium">รอดำเนินการ</span>',
    ongoing:   '<span class="badge-status-progress px-2 py-1 rounded text-xs font-medium"><i class="fa-solid fa-spinner fa-spin mr-1"></i>กำลังซ่อม</span>',
    completed: '<span class="badge-status-done px-2 py-1 rounded text-xs font-medium"><i class="fa-solid fa-check mr-1"></i>ซ่อมเสร็จสิ้น</span>',
    cancelled: '<span class="px-2 py-1 rounded text-xs font-medium text-gray-400 bg-gray-800">ยกเลิก</span>',
};

async function loadTickets(filters = {}) {
    const isMyJobs = filters.assigned_to === 'me';
    if (isMyJobs) {
        filters.assigned_to = window.CURRENT_USER.id;
    }

    const containerId = isMyJobs ? 'my-jobs-container' : 'ticket-container';
    const container   = document.getElementById(containerId);
    if (!container) return;
    
    container.innerHTML = '<div class="col-span-3 text-center text-slate-400 py-10"><i class="fa-solid fa-spinner fa-spin text-2xl mb-2"></i><p>กำลังโหลด...</p></div>';

    const params = new URLSearchParams(filters).toString();
    try {
        const res  = await fetch('/api/tickets.php?' + params);
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
                    <h3 class="text-white font-semibold mt-1 text-sm">${escHtml(t.description).substring(0,60)}${t.description.length>60?'...':''}</h3>
                </div>
                ${PRIORITY_BADGE[t.priority] || ''}
            </div>
            ${window.IS_ADMIN ? `<button type="button" onclick="deleteTicket(${t.id})" class="absolute top-2 right-2 text-text-muted hover:text-red-400 transition-colors opacity-0 group-hover:opacity-100 p-1" title="ลบรายการ"><i class="fa-solid fa-trash-can"></i></button>` : ''}
            <div class="space-y-1 text-xs text-text-muted mb-3">
                <p><i class="fa-solid fa-user w-4 text-center"></i> ${escHtml(t.reporter_name)} ${t.reporter_phone?'('+escHtml(t.reporter_phone)+')':''}</p>
                ${t.location_room?`<p><i class="fa-solid fa-location-dot w-4 text-center"></i> ${escHtml(t.location_room)}</p>`:''}
                ${t.asset_name?`<p><i class="fa-solid fa-desktop w-4 text-center"></i> ${escHtml(t.asset_name)} ${t.asset_model?'('+escHtml(t.asset_model)+')':''}</p>`:''}
                <p><i class="fa-solid fa-clock w-4 text-center"></i> ${timeAgo(t.created_at)}</p>
            </div>
            <div class="flex items-center justify-between border-t border-white/10 pt-3">
                ${STATUS_LABEL[t.status] || ''}
                ${window.IS_ADMIN ? `
                <select onchange="assignTicket(${t.id}, this.value)"
                    class="bg-ocean-800 text-xs text-white border border-white/10 rounded px-2 py-1 focus:outline-none focus:border-ocean-500">
                    <option value="">จ่ายงาน...</option>
                    ${window.STAFF_LIST ? window.STAFF_LIST.map(s=>`<option value="${s.id}" ${t.assigned_to==s.id?'selected':''}>${s.full_name}</option>`).join('') : ''}
                </select>` : `<span class="text-xs text-text-muted">${t.assigned_name ? '👤 '+escHtml(t.assigned_name) : 'ยังไม่ได้รับมอบหมาย'}</span>`}
            </div>
            ${(t.status !== 'completed' && (window.IS_ADMIN || t.assigned_to == window.CURRENT_USER.id)) ? `
            <div class="mt-2 flex gap-2">
                ${t.status === 'pending'  ? `<button onclick="updateStatus(${t.id},'ongoing')"   class="flex-1 text-xs bg-ocean-700 hover:bg-ocean-600 text-white py-1 rounded transition-colors">รับงาน</button>` : ''}
                ${t.status === 'ongoing'  ? `<button onclick="updateStatus(${t.id},'completed')" class="flex-1 text-xs bg-green-700 hover:bg-green-600 text-white py-1 rounded transition-colors">✅ ปิดงาน</button>` : ''}
            </div>` : ''}
        </div>`).join('');
    } catch(e) { container.innerHTML = '<p class="text-red-400 col-span-3 text-center">เกิดข้อผิดพลาด</p>'; }
}

async function updateStatus(id, status) {
    await fetch('/api/tickets.php', { method:'PUT', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ id, status }) });
    loadTickets();
}

async function assignTicket(id, assigned_to) {
    await fetch('/api/tickets.php', { method:'PUT', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ id, assigned_to: assigned_to || null }) });
    loadTickets();
}



// ---- Create Ticket Form ----
let isSubmitting = false;
window.handleFormSubmit = async function(e) {
    e.preventDefault();
    if (isSubmitting) return;
    
    const form = e.target;
    const btn  = form.querySelector('[type=submit]');
    const loader = document.getElementById('page-loader');
    
    isSubmitting = true;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> กำลังส่ง...';
    
    if (loader) {
        loader.querySelector('p').textContent = 'กำลังบันทึกข้อมูลและแจ้งเตือนทีมงาน...';
        loader.classList.remove('hidden');
    }

    const formData = new FormData(form);
    const data = {};
    formData.forEach((value, key) => { data[key] = value; });

    try {
        const res  = await fetch('/api/tickets.php', { 
            method: 'POST', 
            headers: { 'Content-Type': 'application/json' }, 
            body: JSON.stringify(data) 
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
    } catch(err) {
        showToast('❌ ไม่สามารถเชื่อมต่อ Server ได้', true);
        if (loader) loader.classList.add('hidden');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane mr-2"></i> ส่งเรื่องแจ้งซ่อม';
        isSubmitting = false;
    }
}

// ---- QR / Asset Lookup ----
async function simulateQRScan() {
    const input = document.getElementById('assetIdInput');
    const info  = document.getElementById('assetInfo');
    const code  = input.value.trim() || 'PC-OPD-001';
    input.value = 'กำลังค้นหา...';
    input.classList.add('animate-pulse');
    try {
        const res  = await fetch('/api/assets.php?code=' + encodeURIComponent(code));
        const json = await res.json();
        input.classList.remove('animate-pulse');
        if (json.success) {
            const a = json.data;
            input.value = a.asset_code;
            const hiddenId = document.getElementById('hiddenAssetId');
            if (hiddenId) hiddenId.value = a.id;
            
            info.innerHTML = `<i class="fa-solid fa-circle-check mr-1"></i> พบ: ${a.asset_name} ${a.brand} ${a.model}`;
            info.classList.remove('hidden');
            
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
            input.value = code;
            info.innerHTML = `<i class="fa-solid fa-circle-xmark mr-1 text-red-400"></i> ไม่พบรหัสนี้ในระบบ`;
            info.classList.remove('hidden');
        }
    } catch(e) { input.classList.remove('animate-pulse'); input.value = code; }
}

// ---- Navigation (switchView) — Full Definition ----
window.switchView = function(viewId) {
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
        if (viewId === 'dashboard')   loadDashboard();
        if (viewId === 'ticket-list') loadTickets();
        if (viewId === 'my-jobs')     loadTickets({ assigned_to: 'me' });
    }, 300);
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
function setText(id, val) { const el = document.getElementById(id); if(el) el.textContent = val; }
function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

window.showToast = function(msg, isError = false) {
    const toast = document.getElementById('toast');
    const msgEl = document.getElementById('toast-message');
    if (!toast || !msgEl) return;
    
    msgEl.textContent = msg;
    toast.style.borderLeftColor = isError ? '#ff4d4d' : '#00b4d8';
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3500);
};

function timeAgo(dateStr) {
    const diff = Math.floor((Date.now() - new Date(dateStr)) / 60000);
    if (diff < 1)   return 'เมื่อกี้';
    if (diff < 60)  return `${diff} นาทีที่แล้ว`;
    if (diff < 1440) return `${Math.floor(diff/60)} ชม.ที่แล้ว`;
    return `${Math.floor(diff/1440)} วันที่แล้ว`;
}
// ---- User Management ----
window.openAddUserModal = function() {
    document.getElementById('userModalTitle').textContent = 'เพิ่มผู้ใช้งานใหม่';
    document.getElementById('userForm').reset();
    document.getElementById('user_id').value = '';
    document.getElementById('user_username').disabled = false;
    document.getElementById('userModal').classList.remove('hidden');
};

window.openEditUserModal = function(u) {
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

window.closeUserModal = function() {
    document.getElementById('userModal').classList.add('hidden');
};

window.handleUserSubmit = async function(e) {
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
