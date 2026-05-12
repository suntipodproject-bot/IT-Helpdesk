// --- Assign Modal Logic ---
async function openAssignModal(ticketId) {
    document.getElementById('assignTicketId').value = ticketId;
    document.getElementById('assignModal').classList.remove('hidden');
    document.getElementById('assignLoading').classList.remove('hidden');
    document.getElementById('assignStaffList').innerHTML = '';

    try {
        const res = await fetch('/api/staff_workload.php');
        const json = await res.json();
        document.getElementById('assignLoading').classList.add('hidden');
        
        if (json.success) {
            const listHtml = json.data.map(staff => `
                <div class="flex items-center justify-between p-3 rounded-lg border border-white/10 hover:bg-ocean-800 transition-colors cursor-pointer" onclick="confirmAssignTicket('${staff.id}')">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-ocean-700 flex items-center justify-center text-white font-bold text-xs">
                            ${staff.full_name.substring(0, 2)}
                        </div>
                        <div>
                            <p class="text-sm font-medium text-white">${escHtml(staff.full_name)}</p>
                            <p class="text-xs text-text-muted">${staff.role === 'admin' ? 'หัวหน้า' : 'เจ้าหน้าที่'}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-xs font-medium px-2 py-1 rounded-full ${staff.active_tickets > 3 ? 'bg-red-500/20 text-red-400' : 'bg-ocean-500/20 text-ocean-400'}">
                            งานในมือ: ${staff.active_tickets}
                        </span>
                    </div>
                </div>
            `).join('');
            document.getElementById('assignStaffList').innerHTML = listHtml;
        } else {
            document.getElementById('assignStaffList').innerHTML = `<p class="text-center text-red-400 text-sm py-4">โหลดข้อมูลล้มเหลว</p>`;
        }
    } catch (err) {
        document.getElementById('assignLoading').classList.add('hidden');
        document.getElementById('assignStaffList').innerHTML = `<p class="text-center text-red-400 text-sm py-4">โหลดข้อมูลล้มเหลว</p>`;
    }
}

function closeAssignModal() {
    document.getElementById('assignModal').classList.add('hidden');
}

async function confirmAssignTicket(staffId) {
    const ticketId = document.getElementById('assignTicketId').value;
    if (!ticketId) return;

    // Show loading state if it's a specific button, otherwise just fetch
    const target = event.currentTarget;
    const oldHtml = target.innerHTML;
    if (staffId === 'auto') {
        target.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังสุ่มงาน...';
    }

    try {
        const res = await fetch('/api/tickets.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: ticketId, assigned_to: staffId })
        });
        const json = await res.json();
        
        if (staffId === 'auto') target.innerHTML = oldHtml;

        if (json.success) {
            showToast('✅ จ่ายงานสำเร็จ');
            closeAssignModal();
            
            // Refresh the appropriate view
            const myJobsView = document.getElementById('view-my-jobs');
            if (myJobsView && !myJobsView.classList.contains('hidden')) {
                loadTickets({ assigned_to: 'me' });
            } else {
                loadTickets();
            }
        } else {
            showToast('❌ ' + (json.error || 'เกิดข้อผิดพลาด'), true);
        }
    } catch (err) {
        if (staffId === 'auto') target.innerHTML = oldHtml;
        showToast('❌ เกิดข้อผิดพลาดในการเชื่อมต่อ', true);
    }
}

// Hijack old assignTicket calls to use the new modal
window.assignTicket = function(id, val) {
    openAssignModal(id);
};
