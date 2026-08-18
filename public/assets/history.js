const eventRows = document.querySelector('#history-events');
const eventCount = document.querySelector('#event-count');
const uniqueTagCount = document.querySelector('#unique-tag-count');
const historyLive = document.querySelector('#history-live');
const historyMessage = document.querySelector('#history-message');

function addCell(row, text, className = '') {
  const cell = document.createElement('td');
  cell.textContent = text || '–';
  if (className) cell.className = className;
  row.append(cell);
}

async function loadHistory() {
  try {
    const response = await fetch('/api/history');
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'Historie kon niet worden geladen');

    eventCount.textContent = result.event_count;
    uniqueTagCount.textContent = result.unique_tag_count;
    eventRows.replaceChildren();
    if (!result.events.length) {
      eventRows.innerHTML = '<tr><td colspan="4" class="empty">Nog geen bekers gelezen.</td></tr>';
      return;
    }

    for (const event of result.events) {
      const row = document.createElement('tr');
      addCell(row, new Date(event.scanned_at).toLocaleString('nl-NL'));
      addCell(row, event.tag, 'history-tag');
      const action = document.createElement('td');
      const badge = document.createElement('span');
      badge.className = `state ${event.direction}`;
      badge.textContent = event.direction === 'IN' ? 'UITGEGEVEN' : 'INGENOMEN';
      action.append(badge);
      row.append(action);
      addCell(row, event.source);
      eventRows.append(row);
    }
    historyMessage.textContent = '';
    historyLive.textContent = `● Live · ${new Date().toLocaleTimeString('nl-NL')}`;
  } catch (error) {
    historyMessage.textContent = error.message;
    historyMessage.className = 'error';
    historyLive.textContent = '● Verbinding mislukt';
  }
}

document.querySelector('#history-refresh').addEventListener('click', loadHistory);
loadHistory();
setInterval(() => {
  if (!document.hidden) loadHistory();
}, 2000);
