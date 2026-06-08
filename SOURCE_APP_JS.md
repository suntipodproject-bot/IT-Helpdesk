# Frontend Logic (JavaScript)
ไฟล์หลักที่ควบคุมการแสดงผล Dashboard, Ticket List และการส่งฟอร์ม

```javascript
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
const STATUS_LABEL = {
    pending:   '<span class="badge-status-pending px-2 py-1 rounded text-xs font-medium">รอดำเนินการ</span>',
    ongoing:   '<span class="badge-status-progress px-2 py-1 rounded text-xs font-medium"><i class="fa-solid fa-spinner fa-spin mr-1"></i>กำลังซ่อม</span>',
    completed: '<span class="badge-status-done px-2 py-1 rounded text-xs font-medium"><i class="fa-solid fa-check mr-1"></i>ซ่อมเสร็จสิ้น</span>',
    cancelled: '<span class="px-2 py-1 rounded text-xs font-medium text-gray-400 bg-gray-800">ยกเลิก</span>',
};

async function loadTickets(filters = {}) {
    const container = document.getElementById('ticket-container');
    if (!container) return;
    // ... logic for rendering tickets using fetch /api/tickets.php
}

window.switchView = function(viewId) {
    // ... logic for switching between dashboard, ticket-list, and create-ticket
    if (viewId === 'dashboard')   loadDashboard();
    if (viewId === 'ticket-list') loadTickets();
};
```
