<?php

declare(strict_types=1);
?><!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>RFID scan-historie</title>
  <link rel="stylesheet" href="/assets/site.css">
  <script src="/assets/history.js" defer></script>
</head>
<body class="history-page">
  <main>
    <div class="top"><span class="tag">RFID CUP API</span><a href="/">Live-overzicht →</a></div>
    <h1>Scan-historie</h1>
    <p class="intro">Alle gelezen RFID-tags en iedere uitgifte- of innameactie. Deze historie blijft bewaard, ook wanneer een beker is ingenomen.</p>

    <div class="metrics" aria-label="Historie-totalen">
      <div class="metric"><span>Gelezen tags</span><strong id="unique-tag-count">–</strong></div>
      <div class="metric"><span>Scanacties</span><strong id="event-count">–</strong></div>
    </div>

    <section>
      <div class="heading">
        <h2>Alle scanacties</h2>
        <div class="heading-actions"><span id="history-live" class="live">● Live</span> <button id="history-refresh" type="button">Ververs</button></div>
      </div>
      <p id="history-message" role="status"></p>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Moment</th><th>Tag</th><th>Actie</th><th>Bron</th></tr></thead>
          <tbody id="history-events"><tr><td colspan="4" class="empty">Historie wordt geladen…</td></tr></tbody>
        </table>
      </div>
    </section>
  </main>
</body>
</html>
