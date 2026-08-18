const message = document.querySelector('#message');
const cups = document.querySelector('#cups');
const liveStatus = document.querySelector('#live-status');
const issuedCount = document.querySelector('#issued-count');
const depositOutstanding = document.querySelector('#deposit-outstanding');

function showMessage(text, type = '') {
  message.textContent = text;
  message.className = type;
}

function showTotals(result) {
  issuedCount.textContent = result.issued_count ?? result.count ?? 0;
  const cents = result.deposit_outstanding_cents ?? 0;
  depositOutstanding.textContent = new Intl.NumberFormat('nl-NL', {
    style: 'currency', currency: 'EUR'
  }).format(cents / 100);
}

async function loadCups() {
  try {
    const response = await fetch('/api/cups');
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'Status kon niet worden geladen');
    showTotals(result);
    cups.replaceChildren();
    if (!result.cups.length) {
      cups.innerHTML = '<li class="empty">Nog geen bekers gescand.</li>';
      return;
    }
    for (const cup of result.cups) {
      const item = document.createElement('li');
      const tag = document.createElement('span');
      tag.className = 'tag';
      tag.textContent = cup.tag;
      const state = document.createElement('span');
      state.className = `state ${cup.status}`;
      state.textContent = 'UITGEGEVEN';
      item.append(tag, state);
      cups.append(item);
    }
    liveStatus.textContent = `● Live · ${new Date().toLocaleTimeString('nl-NL')}`;
  } catch (error) {
    issuedCount.textContent = '–';
    depositOutstanding.textContent = '–';
    cups.innerHTML = `<li class="empty">${error.message}</li>`;
    liveStatus.textContent = '● Verbinding mislukt';
  }
}

async function clearDemoData() {
  if (!confirm('Weet je zeker dat je alle gescande bekers en scan-historie wilt wissen?')) return;
  document.querySelector('#clear').disabled = true;
  showMessage('Demo-data wordt gewist…');
  try {
    const response = await fetch('/api/demo/reset', { method: 'POST' });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'Wissen mislukt');
    showMessage('Demo-data is gewist.', 'ok');
    await loadCups();
  } catch (error) {
    showMessage(`Niet gewist: ${error.message}`, 'error');
  } finally {
    document.querySelector('#clear').disabled = false;
  }
}

document.querySelector('#clear').addEventListener('click', clearDemoData);
document.querySelector('#refresh').addEventListener('click', loadCups);
loadCups();
setInterval(() => {
  if (!document.hidden) loadCups();
}, 2000);
