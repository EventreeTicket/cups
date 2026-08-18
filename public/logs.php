<?php

declare(strict_types=1);
?><!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>RFID technische logs</title>
  <link rel="stylesheet" href="/assets/site.css">
  <script src="/assets/logs.js" defer></script>
</head>
<body class="history-page">
  <main>
    <div class="top"><span class="tag">RFID DEBUG</span><a href="/">Live-overzicht →</a></div>
    <h1>Technische logs</h1>
    <p class="intro">Tijdelijke logs van de Sunmi-app: scanstart, RFID-callbacks, tags, vermogen en API-antwoorden.</p>
    <section>
      <div class="heading">
        <h2><span id="log-count">–</span> logregels</h2>
        <div class="heading-actions"><span id="log-live" class="live">● Live</span> <button id="copy-logs" type="button">Kopieer logs</button> <button id="logs-refresh" type="button">Ververs</button></div>
      </div>
      <p id="logs-message" role="status"></p>
      <div class="table-wrap">
        <table><thead><tr><th>Moment</th><th>Event</th><th>Details</th></tr></thead>
          <tbody id="log-rows"><tr><td colspan="3" class="empty">Logs worden geladen…</td></tr></tbody>
        </table>
      </div>
    </section>
  </main>
</body>
</html>
