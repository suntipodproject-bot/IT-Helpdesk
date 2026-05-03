// ======================================================
// app.js — Real API Integration (overrides mock JS)
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
    pending:     '<span class="badge-status-pending px-2 py-1 rounded text-xs font-medium">รอดำเนินการ</span>',
    in_progress: '<span class="badge-status-progress px-2 py-1 rounded text-xs font-medium"><i class="fa-solid fa-spinner fa-spin mr-1"></i>กำลังซ่อม</span>',
    done:        '<span class="badge-status-done px-2 py-1 rounded text-xs font-medium"><i class="fa-solid fa-check mr-1"></i>ซ่อมเสร็จสิ้น</span>',
};

async function loadTickets(filters = {}) {
    const container = document.getElementById('ticket-container');
    if (!container) return;
    container.innerHTML = '<div class="col-span-3 text-center text-slate-400 py-10"><i class="fa-solid fa-spinner fa-spin text-2xl mb-2"></i><p>กำลังโหลด...</p></div>';

    const params = new URLSearchParams(filters).toString();
    try {
        const res  = await fetch('/api/tickets.php?' + params);
        const json = await res.json();
        if (!json.success) { container.innerHTML = '<p class="text-red-400 col-span-3 text-center">โหลดข้อมูลไม่สำเร็จ</p>'; return; }

        const tickets = json.data;
        if (!tickets.length) {
            container.innerHTML = '<p class="text-slate-500 col-span-3 text-center py-10">ไม่มีรายการแจ้งซ่อม</p>'; return;
        }

        container.innerHTML = tickets.map(t => `
        <div class="glass-card p-4 relative overflow-hidden" id="ticket-${t.id}">
            <div class="absolute top-0 left-0 w-1 h-full ${PRIORITY_BAR[t.priority] || ''}"></div>
            <div class="flex justify-between items-start mb-3">
                <div>
                    <span class="text-xs text-text-muted font-mono">${t.ticket_no}</span>
                    <h3 class="text-white font-semibold mt-1 text-sm">${escHtml(t.description).substring(0,60)}${t.description.length>60?'...':''}</h3>
                </div>
                ${PRIORITY_BADGE[t.priority] || ''}
            </div>
            <div class="space-y-1 text-xs text-text-muted mb-3">
                <p><i class="fa-solid fa-user w-4 text-center"></i> ${escHtml(t.reporter_name)} ${t.reporter_phone?'('+escHtml(t.reporter_phone)+')':''}</p>
                ${t.location_room?`<p><i class="fa-solid fa-location-dot w-4 text-center"></i> ${escHtml(t.location_room)}</p>`:''}
                ${t.asset_name?`<p><i class="fa-solid fa-desktop w-4 text-center"></i> ${escHtml(t.asset_name)} ${t.asset_model?'('+escHtml(t.asset_model)+')':''}</p>`:''}
                <p><i class="fa-solid fa-clock w-4 text-center"></i> ${timeAgo(t.created_at)}</p>
            </div>
            <div class="flex items-center justify-between border-t border-white/10 pt-3">
                ${STATUS_LABEL[t.status] || ''}
                ${IS_ADMIN ? `
                <select onchange="assignTicket(${t.id}, this.value)"
                    class="bg-ocean-800 text-xs text-white border border-white/10 rounded px-2 py-1 focus:outline-none focus:border-ocean-500">
                    <option value="">จ่ายงาน...</option>
                    ${window.STAFF_LIST ? window.STAFF_LIST.map(s=>`<option value="${s.id}" ${t.assigned_to==s.id?'selected':''}>${s.full_name}</option>`).join('') : ''}
                </select>` : `<span class="text-xs text-text-muted">${t.assigned_name ? '👤 '+escHtml(t.assigned_name) : 'ยังไม่ได้รับมอบหมาย'}</span>`}
            </div>
            ${t.status !== 'done' ? `
            <div class="mt-2 flex gap-2">
                ${t.status === 'pending' ? `<button onclick="updateStatus(${t.id},'in_progress')" class="flex-1 text-xs bg-ocean-700 hover:bg-ocean-600 text-white py-1 rounded transition-colors">รับงาน</button>` : ''}
                ${t.status === 'in_progress' ? `<button onclick="updateStatus(${t.id},'done')" class="flex-1 text-xs bg-green-700 hover:bg-green-600 text-white py-1 rounded transition-colors">✅ ปิดงาน</button>` : ''}
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
async function handleFormSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const btn  = form.querySelector('[type=submit]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> กำลังส่ง...';

    const fields = ['reporter_name','reporter_phone','priority','location_building','location_floor','location_room','asset_id','description'];
    const data   = {};
    fields.forEach(f => { const el = form.querySelector(`[name="${f}"]`); if(el) data[f] = el.value; });

    try {
        const res  = await fetch('/api/tickets.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(data) });
        const json = await res.json();
        if (json.success) {
            showToast(`✅ สร้าง Ticket ${json.ticket_no} สำเร็จ! แจ้งเตือนทีมแล้ว`);
            form.reset();
            document.getElementById('assetInfo').classList.add('hidden');
            setTimeout(() => switchView('ticket-list'), 2000);
        } else {
            showToast('❌ เกิดข้อผิดพลาด: ' + (json.error || 'ไม่ทราบสาเหตุ'), true);
        }
    } catch(err) {
        showToast('❌ ไม่สามารถเชื่อมต่อ Server ได้', true);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane mr-2"></i> ส่งเรื่องแจ้งซ่อม';
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
            info.innerHTML = `<i class="fa-solid fa-circle-check mr-1"></i> พบ: ${a.asset_name} ${a.brand} ${a.model} (ประกันถึง ${a.warranty_until || 'ไม่ระบุ'})`;
            info.classList.remove('hidden');
            // Auto-fill location
            const form = document.getElementById('createTicketForm');
            if(form.querySelector('[name=location_building]')) form.querySelector('[name=location_building]').value = a.location_building || '';
            if(form.querySelector('[name=location_room]'))     form.querySelector('[name=location_room]').value     = a.location_room     || '';
        } else {
            input.value = code;
            info.innerHTML = `<i class="fa-solid fa-circle-xmark mr-1 text-red-400"></i> ไม่พบรหัสนี้ในระบบ`;
            info.classList.remove('hidden');
        }
    } catch(e) { input.classList.remove('animate-pulse'); input.value = code; }
}

// ---- Override switchView to load real data ----
const _origSwitchView = switchView;
function switchView(viewId) {
    _origSwitchView(viewId);
    if (viewId === 'dashboard')    setTimeout(loadDashboard, 350);
    if (viewId === 'ticket-list')  setTimeout(() => loadTickets(), 350);
}

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
    // Add form field names to create form
    addFormNames();
});

function addFormNames() {
    const form = document.getElementById('createTicketForm');
    if (!form) return;
    const fields = [
        { sel: 'input[placeholder*="นพ"]',     name: 'reporter_name'      },
        { sel: 'input[placeholder*="โทร"]',    name: 'reporter_phone'     }, // in same input as reporter_name
        { sel: 'select',                         name: null, multi: true   },
        { sel: 'textarea',                       name: 'description'       },
        { sel: '#assetIdInput',                  name: 'asset_id'          },
    ];
    // reporter name
    const rname = form.querySelector('input[placeholder*="นพ"]');
    if (rname) rname.name = 'reporter_name';
    // selects: building, floor, room
    const selects = form.querySelectorAll('select');
    const snames  = ['location_building','location_floor','location_room','priority'];
    // priority select comes before location selects in the DOM
    const prioritySel = form.querySelector('select option[value="critical"]')?.closest('select');
    if (prioritySel) prioritySel.name = 'priority';
    const locationSels = Array.from(selects).filter(s => s !== prioritySel);
    ['location_building','location_floor','location_room'].forEach((n,i) => { if(locationSels[i]) locationSels[i].name = n; });
    // textarea
    const ta = form.querySelector('textarea');
    if (ta) ta.name = 'description';
}

// ---- Helpers ----
function setText(id, val) { const el = document.getElementById(id); if(el) el.textContent = val; }
function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function showToast(msg, isError = false) {
    const toast = document.getElementById('toast');
    document.getElementById('toast-message').textContent = msg;
    toast.style.borderLeftColor = isError ? '#ff4d4d' : '#00b4d8';
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3500);
}
function timeAgo(dateStr) {
    const diff = Math.floor((Date.now() - new Date(dateStr)) / 60000);
    if (diff < 1)   return 'เมื่อกี้';
    if (diff < 60)  return `${diff} นาทีที่แล้ว`;
    if (diff < 1440) return `${Math.floor(diff/60)} ชม.ที่แล้ว`;
    return `${Math.floor(diff/1440)} วันที่แล้ว`;
}
// Preload staff list for admin assignment dropdown
if (typeof IS_ADMIN !== 'undefined' && IS_ADMIN) {
    window.STAFF_LIST = []; // Will be populated from DB in future enhancement
}
