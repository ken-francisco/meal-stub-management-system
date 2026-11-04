const API = 'attendance_api.php';
const canEdit = document.body.dataset.canEdit === '1';
const tbody = document.querySelector('#reportsTable tbody');
const msg = document.getElementById('messages');
const form = document.getElementById('attendanceForm');    
const cancelBtn = document.getElementById('cancelBtn');
const saveBtn = document.getElementById('saveBtn');
const field = id => document.getElementById(id);
const COLS = canEdit ? 8 : 8;

function showMessages(items, type = 'error') {
  msg.innerHTML = '';
  [].concat(items).forEach(text => {
    const p = document.createElement('p');
    p.className = type;
    p.textContent = text;
    msg.appendChild(p);
  });
}

async function request(action, body = null) {
  const res = await fetch(`${API}?action=${action}`, {
    method: body ? 'POST' : 'GET',
    headers: { 'Content-Type': 'application/json' },
    body: body ? JSON.stringify(body) : undefined
  });
  return res.json();
}

function cell(text) {
  const td = document.createElement('td');
  td.textContent = text ?? '';
  return td;
}

function statusCell(status) {
  const td = document.createElement('td');
  const badge = document.createElement('span');
  badge.className = `badge badge-${status}`;  
  badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
  td.appendChild(badge);
  return td;
}

async function loadReports() {
  const out = await request('list');
  const data = out.data || [];
  tbody.innerHTML = '';
  if (!out.ok) { showMessages(out.error || 'Could not load reports.'); return; }
  if (!data.length) {
    tbody.innerHTML = `<tr><td colspan="${COLS}">No reports yet.</td></tr>`;
    return;
  }

  data.forEach(r => {
    const tr = document.createElement('tr');
    tr.append(cell(r.report_date), cell(r.course), cell(r.year_level),
              cell(r.section), cell(r.present_count), statusCell(r.status));

    // PSAS feedback if any, otherwise the secretary's own remarks
    tr.append(cell(r.review_remarks ? `PSAS: ${r.review_remarks}` : r.remarks));

    if (canEdit) {
      const td = document.createElement('td');
      if (r.status === 'approved') {
        td.textContent = 'Locked';
      } else {
        const edit = document.createElement('button');
        edit.textContent = r.status === 'rejected' ? 'Fix & Resubmit' : 'Edit';
        edit.onclick = () => startEdit(r);
        const del = document.createElement('button');
        del.textContent = 'Delete';
        del.onclick = () => removeReport(r.id);
        td.append(edit, del);
      }
      tr.appendChild(td);
    } else {
      tr.appendChild(cell(r.secretary_name));
    }
    tbody.appendChild(tr);
  });
}

if (form) {
  field('report_date').max = new Date().toISOString().split('T')[0];

  window.startEdit = function (r) {
    field('reportId').value = r.id;
    field('course').value = r.course;
    field('year_level').value = r.year_level;
    field('section').value = r.section;
    field('report_date').value = r.report_date;
    field('present_count').value = r.present_count;
    field('remarks').value = r.remarks || '';
    saveBtn.textContent = 'Update Report';
    cancelBtn.hidden = false;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  function resetForm() {
    form.reset();
    field('reportId').value = '';
    saveBtn.textContent = 'Submit Report';
    cancelBtn.hidden = true;
  }

  window.removeReport = async function (id) {
    if (!confirm('Delete this attendance report?')) return;
    const out = await request('delete', { id });
    showMessages(out.ok ? 'Report deleted.' : (out.error || 'Delete failed.'), out.ok ? 'success' : 'error');
    loadReports();
  };

  form.addEventListener('submit', async e => {
    e.preventDefault();
    const id = field('reportId').value;
    const payload = {
      id,
      course: field('course').value,
      year_level: field('year_level').value,
      section: field('section').value,
      report_date: field('report_date').value,
      present_count: field('present_count').value,
      remarks: field('remarks').value
    };
    const out = await request(id ? 'update' : 'create', payload);
    if (out.ok) {
      showMessages(id ? 'Report updated and sent to PSAS.' : 'Report submitted to PSAS.', 'success');
      resetForm();
      loadReports();
    } else {
      showMessages(out.errors || [out.error || 'Something went wrong.']);
    }
  });

  cancelBtn.addEventListener('click', resetForm);
}

loadReports();