package nl.hardcups.rfid;

/**
 * Wifi-adres van de computer waarop de lokale PHP-API draait. De Sunmi en deze
 * computer moeten op hetzelfde netwerk zitten. Pas dit adres aan wanneer het
 * netwerk een ander adres aan de computer geeft.
 */
final class ApiConfig {
    static final String BASE_URL = "http://192.168.1.21:8080";
    static final String SOURCE = "sunmi-l3";

    private ApiConfig() { }
}
