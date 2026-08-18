# Hardcups RFID voor Sunmi L3

Deze minimale Android-app gebruikt de ingebouwde Sunmi UHF-RFID-service. Eén druk op **Scannen** inventariseert alle zichtbare EPC-tags en stuurt ze als één batch naar `POST /api/scans/in` of `POST /api/scans/out`.

## Demo via wifi

1. Verbind de Sunmi en deze computer met hetzelfde wifi-netwerk.

2. Start de PHP-API in de hoofdmap, luisterend op het lokale netwerk:

   ```bash
   php -S 0.0.0.0:8080 router.php
   ```

3. Bouw en installeer:

   ```bash
   ./gradlew installDebug
   ```

De app gebruikt nu `http://192.168.1.21:8080`, het huidige wifi-adres van deze computer. Als dat adres verandert, pas je `ApiConfig.BASE_URL` aan en bouw/installleer je de app opnieuw. Je hebt tijdens de demo geen USB-kabel nodig.

De lokale API is niet automatisch vanaf het internet bereikbaar: alleen apparaten op hetzelfde lokale netwerk kunnen hem benaderen. Voor internettoegang zijn een publiek gehoste server of een beveiligde tunnel nodig.

De Sunmi RFID SDK is opgenomen als `app/libs/SunmiScannerSdk-release-v1.1.12.aar`, afkomstig uit de officiële Sunmi RFID SDK-documentatie.
