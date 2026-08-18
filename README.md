# RFID cup API (lokaal)

Een klein PHP-concept voor twee RFID-scanners:

- **inschrijven**: bekers komen binnen, bijvoorbeeld twaalf hardcups tegelijk;
- **uitschrijven**: bekers verlaten de locatie;
- iedere scan blijft als gebeurtenis bewaard en de actuele status per RFID-tag is snel op te vragen.

De data staat in `data/rfid.sqlite`. Dit is SQLite: één lokaal bestand, dus geen MySQL-server of losse installatie nodig.

## Starten

Vereist: PHP met de extensie `pdo_sqlite` (in de meeste PHP-installaties aanwezig).

```bash
php -S localhost:8080 router.php
```

De database en tabellen worden automatisch aangemaakt bij de eerste aanvraag.

Open daarna [http://localhost:8080/](http://localhost:8080/) voor het live-overzicht.
De volledige lokale API-documentatie staat op [http://localhost:8080/api.php](http://localhost:8080/api.php).

## Sunmi L3 Android-app

De Android-app staat in [`android-app`](android-app). Hij gebruikt de ingebouwde UHF-RFID-lezer van een Sunmi L3 en verstuurt één RFID-inventarisatie als API-batch. Zie [de Android-instructies](android-app/README.md) voor bouwen, installeren en de ADB-koppeling met deze lokale API.

## API

### Bekers inschrijven

`POST /api/scans/in`

```json
{
  "request_id": "in-reader-20260818-0001",
  "source": "invoerpoort-1",
  "tags": ["04:A1:B2:C3", "04:A1:B2:C4"]
}
```

### Bekers uitschrijven

`POST /api/scans/out`

```json
{
  "request_id": "out-reader-20260818-0001",
  "source": "uitvoerpoort-1",
  "tags": ["04:A1:B2:C3"]
}
```

`request_id` is optioneel, maar sterk aanbevolen: wanneer een scanner een verzoek opnieuw verstuurt, wordt hetzelfde batchresultaat teruggegeven en worden geen dubbele gebeurtenissen geregistreerd.

### Voorbeelden

```bash
curl -X POST http://localhost:8080/api/scans/in \
  -H 'Content-Type: application/json' \
  -d '{"request_id":"batch-1","source":"invoerpoort-1","tags":["hardcup-001","hardcup-002"]}'

curl http://localhost:8080/api/cups
curl http://localhost:8080/api/cups/hardcup-001
```

Beschikbare routes:

| Methode | Pad | Doel |
| --- | --- | --- |
| `GET` | `/health` | eenvoudige gezondheidscheck |
| `POST` | `/api/scans/in` | één of meer tags inschrijven |
| `POST` | `/api/scans/out` | één of meer tags uitschrijven |
| `POST` | `/api/demo/reset` | verwijder alle demo-scans en -statussen |
| `GET` | `/api/cups` | actuele status van alle bekers |
| `GET` | `/api/cups/{tag}` | actuele status en scanhistorie van één beker |

Een tag die al is ingeschreven kan opnieuw worden ingeschreven; dat is bewust toegestaan en komt als afzonderlijk audit-event in de historie. De actuele status volgt altijd de laatste scan.
