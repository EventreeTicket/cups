<?php

declare(strict_types=1);
?><!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>RFID hardcups</title>
  <link rel="stylesheet" href="/assets/site.css">
  <script src="/assets/dashboard.js" defer></script>
</head>
<body class="dashboard-page">
  <main>
    <h1>RFID hardcups</h1>
    <p class="intro">Live-overzicht van uitgegeven hardcups. <a href="/api.php">Bekijk API-specificatie</a>.</p>

    <div class="metrics" aria-label="Actuele statiegeldstand">
      <div class="metric"><span>Uitgegeven bekers</span><strong id="issued-count">–</strong></div>
      <div class="metric"><span>Openstaand statiegeld</span><strong id="deposit-outstanding">–</strong></div>
    </div>

    <section>
      <div class="heading">
        <h2>Uitgegeven bekers</h2>
        <div class="heading-actions"><span id="live-status" class="live">● Live</span> <button id="refresh" type="button">Ververs</button> <button id="clear" type="button">Wis demo-data</button></div>
      </div>
      <p id="message" role="status"></p>
      <ul id="cups"><li class="empty">Status wordt geladen…</li></ul>
    </section>
  </main>
</body>
</html>
