const logRows = document.querySelector('#log-rows');
const logCount = document.querySelector('#log-count');
const logLive = document.querySelector('#log-live');
const logMessage = document.querySelector('#logs-message');
let plainLogs = '';

function cell(row, value, className = '') {
  const item = document.createElement('td');
  item.textContent = value || '–';
  if (className) item.className = className;
  row.append(item);
}

async function loadLogs() {
  try {
    const response = await fetch('/api/debug-logs');
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'Logs konden niet worden geladen');
    logCount.textContent = result.count;
    logRows.replaceChildren();
    plainLogs = result.logs.map((log) => [
      new Date(log.created_at).toLocaleString('nl-NL'), log.event, log.details || ''
    ].join('\t')).join('\n');
    if (!result.logs.length) {
      logRows.innerHTML = '<tr><td colspan="3" class="empty">Nog geen logs ontvangen.</td></tr>';
      return;
    }
    for (const log of result.logs) {
      const row = document.createElement('tr');
      cell(row, new Date(log.created_at).toLocaleString('nl-NL'));
      cell(row, log.event, 'history-tag');
      cell(row, log.details, 'history-tag');
      logRows.append(row);
    }
    logMessage.textContent = '';
    logLive.textContent = `● Live · ${new Date().toLocaleTimeString('nl-NL')}`;
  } catch (error) {
    logMessage.textContent = error.message;
    logMessage.className = 'error';
  }
}

document.querySelector('#logs-refresh').addEventListener('click', loadLogs);
document.querySelector('#copy-logs').addEventListener('click', async () => {
  await navigator.clipboard.writeText(plainLogs);
  logMessage.textContent = 'Logs gekopieerd.';
});
loadLogs();
setInterval(() => { if (!document.hidden) loadLogs(); }, 1500);
