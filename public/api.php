<?php

declare(strict_types=1);
?><!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>RFID API-specificatie</title>
  <link rel="stylesheet" href="/assets/site.css">
</head>
<body class="api-page">
  <main>
    <div class="top"><span class="tag">RFID CUP API · v1</span><a href="/">Live-overzicht →</a></div>
    <h1>API-specificatie</h1>
    <p class="lead">Deze API registreert RFID-tags als <strong>uitgegeven</strong> (IN) of <strong>ingenomen</strong> (OUT). Elke scan blijft in de historie; de actuele status van een tag is altijd de laatste scan.</p>

    <section>
      <h2>Basis</h2>
      <p>Het basisadres is <code>https://cups.paytree-network.nl</code>. Verzoeken en antwoorden gebruiken JSON. Voeg bij POST-verzoeken altijd deze header toe:</p>
      <pre>Content-Type: application/json</pre>
      <p class="note">De API heeft in dit concept nog geen authenticatie. Voeg vóór productie een API-key of andere beveiliging toe.</p>
    </section>

    <section>
      <h2>Endpoints</h2>
      <div class="route"><span class="method">POST</span><code>/api/scans/in</code><span>Registreer één of meer bekers als uitgegeven.</span></div>
      <div class="route"><span class="method">POST</span><code>/api/scans/out</code><span>Registreer één of meer bekers als ingenomen.</span></div>
      <div class="route"><span class="method get">GET</span><code>/api/cups</code><span>Haal de actuele status van alle bekende tags op.</span></div>
      <div class="route"><span class="method get">GET</span><code>/api/cups/{tag}</code><span>Haal één beker plus de volledige scanhistorie op.</span></div>
      <div class="route"><span class="method get">GET</span><code>/health</code><span>Eenvoudige beschikbaarheidscheck.</span></div>
    </section>

    <section>
      <h2>Bekers uitgeven of innemen</h2>
      <p>Gebruik exact hetzelfde JSON-formaat voor beide richtingen. Een batch mag bijvoorbeeld twaalf hardcups bevatten.</p>
      <h3>Verzoek</h3>
      <pre>{
  "request_id": "invoerpoort-1-20260818-0001",
  "source": "invoerpoort-1",
  "tags": ["04:A1:B2:C3", "04:A1:B2:C4"]
}</pre>
      <table>
        <thead><tr><th>Veld</th><th>Verplicht</th><th>Betekenis</th></tr></thead>
        <tbody>
          <tr><td><code>tags</code></td><td>Ja</td><td>Niet-lege lijst met RFID-tagwaarden. Dubbele tags binnen één verzoek worden automatisch maar één keer verwerkt.</td></tr>
          <tr><td><code>source</code></td><td>Nee</td><td>Naam of identificatie van de scanner, bijvoorbeeld <code>invoerpoort-1</code>.</td></tr>
          <tr><td><code>request_id</code></td><td>Nee, aanbevolen</td><td>Unieke ID van dit scannerverzoek. Bij opnieuw verzenden geeft de API hetzelfde resultaat terug zonder dubbel te registreren.</td></tr>
        </tbody>
      </table>
      <h3>Uitgeven</h3>
      <pre>POST /api/scans/in</pre>
      <h3>Innemen</h3>
      <pre>POST /api/scans/out</pre>
      <h3>Succesantwoord · HTTP 201</h3>
      <pre>{
  "batch_id": 42,
  "direction": "in",
  "scanned_at": "2026-08-18T08:32:34+00:00",
  "processed_tags": 2,
  "tags": ["04:A1:B2:C3", "04:A1:B2:C4"]
}</pre>
    </section>

    <section>
      <h2>Status opvragen</h2>
      <h3>Alle bekers</h3>
      <pre>GET /api/cups</pre>
      <pre>{
  "count": 2,
  "cups": [{
    "tag": "04:A1:B2:C3",
    "status": "IN",
    "last_scanned_at": "2026-08-18T08:32:34+00:00",
    "last_source": "invoerpoort-1"
  }]
}</pre>
      <h3>Eén beker en zijn historie</h3>
      <pre>GET /api/cups/04%3AA1%3AB2%3AC3</pre>
      <p>Gebruik URL-encoding wanneer een tag speciale tekens bevat, zoals <code>:</code>. De respons bevat het object <code>cup</code> met de actuele status en een lijst <code>events</code> met alle scans, nieuwste eerst.</p>
    </section>

    <section>
      <h2>Fouten en gedrag</h2>
      <table>
        <thead><tr><th>Status</th><th>Wanneer</th></tr></thead>
        <tbody>
          <tr><td><code>200</code></td><td>GET-verzoek gelukt, of een eerder <code>request_id</code> opnieuw ontvangen.</td></tr>
          <tr><td><code>201</code></td><td>Nieuwe scanbatch opgeslagen.</td></tr>
          <tr><td><code>404</code></td><td>Onbekende route of tag niet gevonden.</td></tr>
          <tr><td><code>422</code></td><td>Ongeldige invoer, bijvoorbeeld geen <code>tags</code>-lijst.</td></tr>
          <tr><td><code>500</code></td><td>Onverwachte serverfout.</td></tr>
        </tbody>
      </table>
      <p>Een tag mag meerdere keren IN of OUT gescand worden. Dit wordt bewust bewaard als auditgeschiedenis; de laatste scan bepaalt de actuele status.</p>
    </section>
  </main>
</body>
</html>
