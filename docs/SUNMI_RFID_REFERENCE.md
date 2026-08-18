# Sunmi RFID referentie

Deze app gebruikt de officiële Sunmi UHF-SDK voor de Sunmi L3. Deze notitie is
de vaste technische referentie bij wijzigingen aan de RFID-flow.

## Primaire bronnen

- Voorbeeldapp: https://github.com/SunmiApp/SunmiUHF
- Gecontroleerde commit: `01c61fe252183f5a165f1afe463f7f95b5440fcb`
- Documentatie: https://file.cdn.sunmi.com/SUNMIDOCS/SUNMI-RFID-Developer-Documentation.pdf

De externe repository en PDF worden niet als kopie in deze Git-repository
opgeslagen. De links hierboven zijn de canonieke bron; zo blijft de repository
klein en kunnen we steeds de actuele leverancierdocumentatie raadplegen.

## Gekozen scanflow

De demo in `TakeInventoryFragment.kt` gebruikt voor Gen2 / 6C-tags:

1. `realTimeInventory(1)` om tags direct via `ReaderCall.onTag` te ontvangen.
2. Na `CMD.REAL_TIME_INVENTORY` opnieuw dezelfde realtime-opdracht starten.
3. Bij stoppen `inventory(1)` aanroepen om de inventarisatie te beëindigen.

Onze app volgt dit patroon gedurende precies vier seconden. EPC's worden tijdens
die periode direct getoond. Een `LinkedHashSet` verwijdert dubbele EPC's per
scanactie: een beker verschijnt en uploadt maximaal eenmaal per druk op de
scanknop.

## Vermogen en EPC-prefix

De testschakelaar in de app wisselt alleen op expliciete gebruikersactie tussen
18 dBm (laag) en 26 dBm (sterk). Verander vermogen nooit tijdens een scan.
De instelling kan door de RFID-module worden bewaard; gebruik deze functie dus
alleen voor het afstellen van het leesveld.

De waargenomen hardcup-EPC's delen de eerste 20 hexadecimale tekens:
`33140BEEB034C7800073`. Het resterende deel is de unieke bekeridentiteit.
Dit kan later als hardwarefilter worden gebruikt, maar pas nadat we voldoende
zeker weten dat alle hardcups deze prefix gebruiken.

Gebruik geen bufferinventarisatie (`inventory(...)` gevolgd door
`getAndResetInventoryBuffer()`) voor het live-scherm: die route levert resultaten
pas na de inventarisatie op en is daarom niet geschikt voor zichtbaar bijwerken
tijdens het bewegen van bekers.
