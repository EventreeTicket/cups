package nl.hardcups.rfid;

import android.app.Activity;
import android.graphics.Color;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.os.RemoteException;
import android.util.Log;
import android.view.Gravity;
import android.view.View;
import android.widget.Button;
import android.widget.LinearLayout;
import android.widget.RadioButton;
import android.widget.RadioGroup;
import android.widget.ScrollView;
import android.widget.TextView;

import com.sunmi.rfid.RFIDHelper;
import com.sunmi.rfid.RFIDManager;
import com.sunmi.rfid.ReaderCall;
import com.sunmi.rfid.constant.CMD;
import com.sunmi.rfid.constant.ParamCts;
import com.sunmi.rfid.entity.DataParameter;
import com.sunmi.sdk.ServiceConnectStatus;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.OutputStream;
import java.io.OutputStreamWriter;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.LinkedHashSet;
import java.util.Set;
import java.util.UUID;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

/** Eénmalige UHF-inventarisatie en verzending als batch naar de lokale API. */
public final class MainActivity extends Activity {
    private static final String LOG_TAG = "HardcupsRFID";
    // Het officiële Sunmi-voorbeeld start een realtime-inventarisatie met
    // herhaling 1 opnieuw na iedere callback. Zo komen tags continu binnen.
    private static final byte REALTIME_REPEAT = 0x01;
    private static final long SCAN_DURATION_MS = 4_000;
    private final ExecutorService network = Executors.newSingleThreadExecutor();
    private final Set<String> tags = new LinkedHashSet<>();
    private final Handler scanTimer = new Handler(Looper.getMainLooper());
    private final Handler presentationTimer = new Handler(Looper.getMainLooper());

    private TextView status;
    private TextView liveTagsEmpty;
    private Button scanButton;
    private RadioGroup direction;
    private LinearLayout liveTags;
    private RFIDHelper rfid;
    private boolean scanRunning;
    private boolean scanActive;
    private boolean inventoryCompleted;
    private boolean readerCompleted;
    private boolean uploadStarted;
    private boolean uploadRunning;

    private final ReaderCall readerCall = new ReaderCall() {
        @Override public void onSuccess(byte command, DataParameter params) {
            Log.d(LOG_TAG, "RFID success command=" + command + " data=" + params);
            if (!scanRunning) return;
            if (command == CMD.REAL_TIME_INVENTORY) {
                runOnUiThread(MainActivity.this::continueRealtimeInventory);
            } else {
                runOnUiThread(MainActivity.this::finishScan);
            }
        }

        @Override public void onTag(byte command, byte state, DataParameter tag) {
            if (!scanRunning) return;
            addTag(tag.getString(ParamCts.TAG_EPC, ""));
        }

        private void addTag(String epc) {
            Log.d(LOG_TAG, "RFID tag epc=" + epc);
            if (epc == null || epc.trim().isEmpty()) return;
            String trimmedEpc = epc.trim();
            int tagCount;
            synchronized (tags) {
                if (!tags.add(trimmedEpc)) return;
                tagCount = tags.size();
            }
            int count = tagCount;
            runOnUiThread(() -> {
                addLiveTag(trimmedEpc);
                setStatus(count + " tag(s) gevonden…", false);
            });
        }

        @Override public void onFailed(byte command, byte errorCode, String message) {
            Log.e(LOG_TAG, "RFID failure command=" + command + " code=" + errorCode + " message=" + message);
            if (!scanRunning) {
                Log.d(LOG_TAG, "Ignoring late RFID callback after scan completion.");
                return;
            }
            if (command == CMD.REAL_TIME_INVENTORY) {
                // De officiële Sunmi-demo start de korte realtime-opdracht
                // opnieuw, ook wanneer één inventarisatie geen resultaat gaf.
                runOnUiThread(MainActivity.this::continueRealtimeInventory);
                return;
            }
            runOnUiThread(() -> {
                scanRunning = false;
                scanActive = false;
                scanTimer.removeCallbacksAndMessages(null);
                presentationTimer.removeCallbacksAndMessages(null);
                scanButton.setEnabled(rfid != null);
                setStatus(rfidErrorMessage(errorCode), true);
            });
        }
    };

    private final ServiceConnectStatus connectionStatus = new ServiceConnectStatus() {
        @Override public void onServiceConnected() {
            rfid = RFIDManager.getInstance().getHelper();
            try {
                rfid.registerReaderCall(readerCall);
                runOnUiThread(() -> {
                    scanButton.setEnabled(true);
                    setStatus("RFID-lezer gereed", false);
                });
            } catch (Exception error) {
                runOnUiThread(() -> setStatus("RFID-lezer kon niet worden voorbereid: " + error.getMessage(), true));
            }
        }

        @Override public void onServiceDisconnected() {
            rfid = null;
            runOnUiThread(() -> {
                scanButton.setEnabled(false);
                setStatus("RFID-lezer niet verbonden", true);
            });
        }
    };

    @Override public void onCreate(Bundle state) {
        super.onCreate(state);
        setContentView(createScreen());
        RFIDManager.getInstance().addServiceConnectStatus(connectionStatus);
        RFIDManager.getInstance().connect(this);
    }

    @Override protected void onDestroy() {
        if (rfid != null) rfid.unregisterReaderCall();
        RFIDManager.getInstance().removeServiceConnectStatus(connectionStatus);
        RFIDManager.getInstance().disconnect();
        scanTimer.removeCallbacksAndMessages(null);
        presentationTimer.removeCallbacksAndMessages(null);
        network.shutdownNow();
        super.onDestroy();
    }

    private View createScreen() {
        int padding = dp(24);
        LinearLayout layout = new LinearLayout(this);
        layout.setOrientation(LinearLayout.VERTICAL);
        layout.setPadding(padding, dp(48), padding, padding);

        TextView title = new TextView(this);
        title.setText("Hardcups RFID");
        title.setTextSize(30);
        title.setTextColor(Color.rgb(20, 83, 45));
        layout.addView(title);

        TextView instruction = new TextView(this);
        instruction.setText("Kies de actie, houd de bekers bij de lezer en druk op Scannen.");
        instruction.setTextSize(16);
        instruction.setPadding(0, dp(12), 0, dp(18));
        layout.addView(instruction);

        direction = new RadioGroup(this);
        direction.setOrientation(RadioGroup.HORIZONTAL);
        RadioButton in = new RadioButton(this);
        in.setId(View.generateViewId());
        in.setText("Uitgeven");
        in.setChecked(true);
        RadioButton out = new RadioButton(this);
        out.setId(View.generateViewId());
        out.setText("Innemen");
        direction.addView(in);
        direction.addView(out);
        layout.addView(direction);

        scanButton = new Button(this);
        scanButton.setText("Scannen");
        scanButton.setTextSize(20);
        scanButton.setEnabled(false);
        LinearLayout.LayoutParams scanParams = new LinearLayout.LayoutParams(-1, dp(64));
        scanParams.setMargins(0, dp(24), 0, dp(16));
        layout.addView(scanButton, scanParams);
        scanButton.setOnClickListener(view -> startScan());

        status = new TextView(this);
        status.setText("RFID-lezer verbinden…");
        status.setTextSize(16);
        status.setGravity(Gravity.CENTER_HORIZONTAL);
        layout.addView(status);

        TextView liveTitle = new TextView(this);
        liveTitle.setText("Live gescande tags");
        liveTitle.setTextSize(16);
        liveTitle.setPadding(0, dp(28), 0, dp(8));
        layout.addView(liveTitle);

        ScrollView liveTagsScroll = new ScrollView(this);
        liveTags = new LinearLayout(this);
        liveTags.setOrientation(LinearLayout.VERTICAL);
        liveTagsEmpty = new TextView(this);
        liveTagsEmpty.setText("Nog geen tags gescand.");
        liveTagsEmpty.setTextColor(Color.rgb(120, 135, 126));
        liveTags.addView(liveTagsEmpty);
        liveTagsScroll.addView(liveTags);
        layout.addView(liveTagsScroll, new LinearLayout.LayoutParams(-1, 0, 1));

        return layout;
    }

    private void startScan() {
        if (rfid == null) {
            setStatus("RFID-lezer is nog niet gereed", true);
            return;
        }
        tags.clear();
        clearLiveTags();
        scanRunning = true;
        scanActive = true;
        inventoryCompleted = false;
        readerCompleted = false;
        uploadStarted = false;
        uploadRunning = false;
        scanButton.setEnabled(false);
        setStatus("RFID-tags zoeken…", false);
        startRealtimeInventory();
        scanTimer.postDelayed(this::stopRealtimeInventory, SCAN_DURATION_MS);
        presentationTimer.postDelayed(this::beginUpload, SCAN_DURATION_MS);
    }

    private void startRealtimeInventory() {
        if (!scanRunning || rfid == null) return;
        try {
            rfid.realTimeInventory(REALTIME_REPEAT);
            Log.d(LOG_TAG, "Starting realtime RFID inventory pass");
        } catch (Exception error) {
            Log.e(LOG_TAG, "Could not start RFID inventory", error);
            failScan("Starten van scan mislukt: " + error.getMessage());
        }
    }

    private void continueRealtimeInventory() {
        if (!scanRunning) return;
        startRealtimeInventory();
    }

    private void stopRealtimeInventory() {
        if (!scanRunning) return;
        scanRunning = false;
        inventoryCompleted = true;
        readerCompleted = true;
        scanTimer.removeCallbacksAndMessages(null);
        try {
            // Dit is ook het stopcommando dat de officiële Sunmi-demo gebruikt.
            rfid.inventory((byte) 0x01);
        } catch (Exception error) {
            Log.w(LOG_TAG, "Could not send RFID stop command", error);
        }
        finishScanIfReady();
    }

    private void finishScan() {
        stopRealtimeInventory();
    }

    private void beginUpload() {
        if (!scanActive || uploadStarted) return;
        uploadStarted = true;
        if (tags.isEmpty()) {
            setStatus("Geen hardcup-tags gevonden. Houd bekers dichterbij en probeer opnieuw.", true);
            finishScanIfReady();
            return;
        }
        setStatus(tags.size() + " tag(s) gevonden. Versturen…", false);
        uploadRunning = true;
        sendBatch(new LinkedHashSet<>(tags));
    }

    private void finishScanIfReady() {
        if (!scanActive || !readerCompleted || !uploadStarted || uploadRunning) return;
        scanActive = false;
        scanButton.setEnabled(rfid != null);
    }

    private void clearLiveTags() {
        liveTags.removeAllViews();
        liveTagsEmpty = new TextView(this);
        liveTagsEmpty.setText("Tags worden tijdens het scannen hier getoond.");
        liveTagsEmpty.setTextColor(Color.rgb(120, 135, 126));
        liveTags.addView(liveTagsEmpty);
    }

    private void addLiveTag(String epc) {
        if (liveTagsEmpty != null) {
            liveTags.removeView(liveTagsEmpty);
            liveTagsEmpty = null;
        }
        TextView tag = new TextView(this);
        tag.setText(epc);
        tag.setTextSize(14);
        tag.setTextColor(Color.rgb(217, 255, 230));
        tag.setTypeface(android.graphics.Typeface.MONOSPACE);
        tag.setPadding(dp(12), dp(10), dp(12), dp(10));
        tag.setBackgroundColor(Color.rgb(23, 34, 32));
        LinearLayout.LayoutParams params = new LinearLayout.LayoutParams(-1, -2);
        params.setMargins(0, 0, 0, dp(6));
        liveTags.addView(tag, params);
    }

    private void failScan(String message) {
        scanRunning = false;
        scanActive = false;
        scanTimer.removeCallbacksAndMessages(null);
        presentationTimer.removeCallbacksAndMessages(null);
        scanButton.setEnabled(rfid != null);
        setStatus(message, true);
    }

    private static String rfidErrorMessage(byte errorCode) {
        int code = errorCode & 0xFF;
        String description;
        switch (code) {
            case 0x10: description = "De bewerking is succesvol voltooid."; break;
            case 0x11: description = "De opdracht kon niet worden uitgevoerd."; break;
            case 0x20: description = "CPU-resetfout in de RFID-lezer."; break;
            case 0x21: description = "RF-draaggolf kon niet worden ingeschakeld."; break;
            case 0x22: description = "De RFID-antenne is niet aangesloten."; break;
            case 0x23: description = "Fout bij schrijven naar het Flash-geheugen."; break;
            case 0x24: description = "Fout bij lezen van het Flash-geheugen."; break;
            case 0x25: description = "Het zendvermogen kon niet worden ingesteld."; break;
            case 0x31: description = "Fout tijdens het inventariseren van RFID-tags."; break;
            case 0x32: description = "Fout tijdens het uitlezen van de RFID-tag."; break;
            case 0x33: description = "Fout tijdens het schrijven naar de RFID-tag."; break;
            case 0x34: description = "Fout tijdens het vergrendelen van de RFID-tag."; break;
            case 0x35: description = "Fout tijdens het uitschakelen van de RFID-tag."; break;
            case 0x36: description = "Geen bruikbare RFID-tag gevonden."; break;
            case 0x37: description = "Tags gevonden, maar toegang tot de tag mislukte."; break;
            case 0x38: description = "De RFID-buffer is leeg."; break;
            case 0x3C: description = "NXP-chipopdracht kon niet worden uitgevoerd."; break;
            case 0x40: description = "Toegang tot de tag geweigerd of onjuist toegangswachtwoord."; break;
            case 0x41: description = "Ongeldige parameter."; break;
            case 0x42: description = "Ongeldige parameter: woordlengte is te groot."; break;
            case 0x43: description = "Ongeldige parameter: geheugenbank valt buiten bereik."; break;
            case 0x44: description = "Ongeldige parameter: vergrendelgebied valt buiten bereik."; break;
            case 0x45: description = "Ongeldige parameter: vergrendelactie valt buiten bereik."; break;
            case 0x46: description = "Ongeldig adres van de RFID-lezer."; break;
            case 0x47: description = "Ongeldige parameter: antenne-ID valt buiten bereik."; break;
            case 0x48: description = "Ongeldige parameter: zendvermogen valt buiten bereik."; break;
            case 0x49: description = "Ongeldige parameter: frequentiebereik valt buiten bereik."; break;
            case 0x4A: description = "Ongeldige parameter: baudrate valt buiten bereik."; break;
            case 0x4B: description = "Ongeldige parameter: pieperinstelling valt buiten bereik."; break;
            case 0x4C: description = "EPC-overeenkomstlengte valt buiten bereik."; break;
            case 0x4D: description = "Ongeldige EPC-overeenkomstlengte."; break;
            case 0x4E: description = "Ongeldige EPC-overeenkomstmodus."; break;
            case 0x4F: description = "Ongeldige instelling voor het frequentiebereik."; break;
            case 0x50: description = "RN16 kon niet van de RFID-tag worden ontvangen."; break;
            case 0x51: description = "Ongeldige DRM-instelling."; break;
            case 0x52: description = "PLL kon niet worden vergrendeld."; break;
            case 0x53: description = "De RF-chip reageert niet."; break;
            case 0x54: description = "Het gewenste zendvermogen kon niet worden bereikt."; break;
            case 0x55: description = "Copyrightauthenticatie is mislukt."; break;
            case 0x56: description = "Fout in de spectrumregelgeving-instelling."; break;
            case 0x57: description = "Het zendvermogen is te laag."; break;
            case 0xEE: description = "Retourverlies van de RF-poort kon niet worden gemeten."; break;
            case 0x03: description = "Geen RFID-apparaat gevonden."; break;
            default: description = "Onbekende RFID-fout"; break;
        }
        return String.format(java.util.Locale.ROOT, "RFID-fout 0x%02X: %s", code, description);
    }


    private void sendBatch(Set<String> scannedTags) {
        final String endpoint = direction.getCheckedRadioButtonId() == direction.getChildAt(0).getId() ? "in" : "out";
        network.execute(() -> {
            try {
                JSONObject body = new JSONObject();
                body.put("request_id", "sunmi-l3-" + UUID.randomUUID());
                body.put("source", ApiConfig.SOURCE);
                JSONArray values = new JSONArray();
                for (String tag : scannedTags) values.put(tag);
                body.put("tags", values);

                HttpURLConnection connection = (HttpURLConnection) new URL(ApiConfig.BASE_URL + "/api/scans/" + endpoint).openConnection();
                connection.setRequestMethod("POST");
                connection.setConnectTimeout(8_000);
                connection.setReadTimeout(12_000);
                connection.setRequestProperty("Content-Type", "application/json");
                connection.setDoOutput(true);
                try (OutputStream output = connection.getOutputStream()) {
                    output.write(body.toString().getBytes(StandardCharsets.UTF_8));
                }
                int responseCode = connection.getResponseCode();
                String response = readResponse(connection);
                connection.disconnect();
                runOnUiThread(() -> {
                    uploadRunning = false;
                    if (responseCode >= 200 && responseCode < 300) {
                        setStatus(scannedTags.size() + " beker(s) " + (endpoint.equals("in") ? "uitgegeven" : "ingenomen") + ".", false);
                    } else {
                        setStatus("API-fout (" + responseCode + "): " + response, true);
                    }
                    finishScanIfReady();
                });
            } catch (Exception error) {
                runOnUiThread(() -> {
                    uploadRunning = false;
                    setStatus("Versturen mislukt: " + error.getMessage(), true);
                    finishScanIfReady();
                });
            }
        });
    }

    private static String readResponse(HttpURLConnection connection) {
        try {
            BufferedReader reader = new BufferedReader(new java.io.InputStreamReader(
                connection.getResponseCode() >= 400 ? connection.getErrorStream() : connection.getInputStream(), StandardCharsets.UTF_8));
            String line = reader.readLine();
            reader.close();
            return line == null ? "" : line;
        } catch (Exception ignored) {
            return "";
        }
    }

    private void setStatus(String value, boolean error) {
        status.setText(value);
        status.setTextColor(error ? Color.rgb(185, 28, 28) : Color.rgb(20, 83, 45));
    }

    private int dp(int value) {
        return Math.round(value * getResources().getDisplayMetrics().density);
    }
}
