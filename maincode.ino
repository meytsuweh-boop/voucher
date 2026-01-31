/*******************************************************
  BOTTLE WIFI + VOUCHER INTEGRATION + INSTANT REDIRECT
  ✅ Session expiry = instant redirect to portal
  ✅ Voucher system integration via HTTP API
*******************************************************/

#include "HX711.h"
#include <math.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <WebServer.h>
#include <DNSServer.h>
#include <HTTPClient.h>

DNSServer dns;
bool dnsHijackOn = true;

extern "C"
{
#include "lwip/lwip_napt.h"
#include "lwip/tcpip.h"
}

// Forward declaration
extern const char INDEX_HTML[] PROGMEM;

// ================= VOUCHER CONFIG =================
const char *VOUCHER_SERVER = "https://voucher-siys.onrender.com";

const int VOUCHER_PORT = 80;

// ================= SERVO LITE =================
class ServoLite
{
public:
  bool attach(int pin, int minUs = 500, int maxUs = 2500, int freq = 50, int resolutionBits = 16)
  {
    _pin = pin;
    _minUs = minUs;
    _maxUs = maxUs;
    _freq = freq;
    _resBits = resolutionBits;

    bool ok = ledcAttach(_pin, _freq, _resBits);
    if (!ok)
      return false;

    _attached = true;
    write(0);
    return true;
  }

  void detach()
  {
    if (_attached)
    {
      ledcDetach(_pin);
      _attached = false;
    }
  }

  void write(int angle)
  {
    if (!_attached)
      return;
    angle = constrain(angle, 0, 180);

    int pulseUs = map(angle, 0, 180, _minUs, _maxUs);
    const uint32_t periodUs = 1000000UL / _freq;
    const uint32_t maxDuty = (1UL << _resBits) - 1;

    uint32_t duty = (uint32_t)(((uint64_t)pulseUs * maxDuty) / periodUs);
    ledcWrite(_pin, duty);
  }

private:
  int _pin = -1;
  int _minUs = 500;
  int _maxUs = 2500;
  int _freq = 50;
  int _resBits = 16;
  bool _attached = false;
};

// ================= WIFI =================
const char *AP_SSID = "BottleCounter";
const char *UP_SSID = "BARRIA_FAM";
const char *UP_PASS = "2771d5a44c";

WebServer server(80);

// ================= PINS =================
#define DOUT 4
#define CLK 5
#define BUZZER_PIN 18
#define SERVO_PIN 32
#define BUTTON_PIN 19
#define GREEN_LED_PIN 33
#define RED_LED_PIN 25
#define IR1_PIN 16
#define IR2_PIN 17

HX711 scale;
ServoLite servo;

float calibration_factor = 97.12;

// ================= BOTTLE DETECTION =================
int bottle_count_total = 0;
bool bottleAccepted = false;
unsigned long detectWindowStartMs = 0;
const unsigned long DETECT_WINDOW_MS = 1800;
bool detectWindowActive = false;
int stableHits = 0;
// Faster re-accept: fewer stable hits (tune up if you get false accepts)
const int REQUIRED_HITS = 1;
// Require weight to be stable for a short period before counting as a hit.
unsigned long stableStartMs = 0;
float stableRefGrams = 0;
unsigned long emptyStableStart = 0;
const unsigned long AUTO_TARE_MS = 1200;
bool justTared = false;
unsigned long lastAcceptMs = 0;
const unsigned long MIN_GAP_MS = 500;
bool irTriggered = false; // ✅ NEW: One-time IR trigger flag
unsigned long acceptClearStartMs = 0;
const unsigned long ACCEPT_CLEAR_MS = 350;

const float MIN_G = 3.0;
const float MAX_G = 68.0;
const float ZERO_CLAMP_G = 2.0;
const float STABLE_DELTA_G = 1.5;
const unsigned long STABLE_TIME_MS = 350;

float lastGrams = 0;
const float WEIGHT_RISE_G = 3.0;
bool needRetare = false;
const float OVERWEIGHT_G = 90.0;

const int SERVO_CLOSED = 42;
const int SERVO_OPEN = 134;
// Faster cycle time (tune up if bottles jam)
const unsigned long SERVO_OPEN_MS = 800;
const unsigned long BUZZ_MS = 200;
const unsigned long SERVO_RETRY_GAP_MS = 80;

// ================= POWER SWITCH =================
bool lastSwitchReading = HIGH;
bool powerOn = false;
unsigned long lastDebounceTime = 0;
const unsigned long DEBOUNCE_MS = 40;

// ================= USERS / SESSIONS =================
struct UserSession
{
  String id;
  int bottles;
  long creditSec;
  unsigned long sessionStartMs;
  unsigned long sessionEndMs;
  bool inUse;
  bool expired; // ✅ NEW FLAG for expired sessions
};

const int MAX_USERS = 10;
UserSession users[MAX_USERS];

String activeDropperId = "";
bool dropperLocked = false;

const int BOTTLES_PER_REDEEM = 5;
const long SECONDS_PER_REDEEM = 300;

// ================= NAT CONTROL =================
volatile bool natState = false;
static uint32_t apIpU32 = 0;

void nat_enable_cb(void *arg)
{
  if (apIpU32 != 0)
  {
    ip_napt_enable(apIpU32, 1);
    natState = true;
  }
}

void nat_disable_cb(void *arg)
{
  if (apIpU32 != 0)
  {
    ip_napt_enable(apIpU32, 0);
    natState = false;
  }
}

void setNAT(bool on)
{
  if (on)
    tcpip_callback(nat_enable_cb, NULL);
  else
    tcpip_callback(nat_disable_cb, NULL);
}

// ================= VOUCHER API =================
static bool splitHttpsUrl(const String &url, String &hostOut, String &pathOut)
{
  const String prefix = "https://";
  if (!url.startsWith(prefix))
    return false;

  int hostStart = prefix.length();
  int pathStart = url.indexOf('/', hostStart);
  if (pathStart < 0)
  {
    hostOut = url.substring(hostStart);
    pathOut = "/";
  }
  else
  {
    hostOut = url.substring(hostStart, pathStart);
    pathOut = url.substring(pathStart);
  }
  return hostOut.length() > 0;
}

static bool httpsGetRaw(const String &host, const String &path, String &responseOut, int &httpCodeOut, int timeoutMs)
{
  WiFiClientSecure client;
  client.setInsecure();
  client.setTimeout(timeoutMs);
  client.setHandshakeTimeout(timeoutMs);

  if (!client.connect(host.c_str(), 443))
  {
    httpCodeOut = -1;
    responseOut = "TLS connect failed";
    return false;
  }

  client.print(String("GET ") + path + " HTTP/1.1\r\n" +
               "Host: " + host + "\r\n" +
               "User-Agent: Mozilla/5.0 (ESP32; Arduino)\r\n" +
               "Accept: application/json\r\n" +
               "Accept-Encoding: identity\r\n" +
               "Connection: close\r\n\r\n");

  String statusLine = client.readStringUntil('\n');
  statusLine.trim();
  int code = 0;
  int sp1 = statusLine.indexOf(' ');
  int sp2 = statusLine.indexOf(' ', sp1 + 1);
  if (sp1 > 0 && sp2 > sp1)
    code = statusLine.substring(sp1 + 1, sp2).toInt();

  while (client.connected())
  {
    String line = client.readStringUntil('\n');
    if (line == "\r" || line.length() == 0)
      break;
  }

  responseOut = client.readString();
  httpCodeOut = code;
  client.stop();
  return code == 200;
}

static bool httpGetUrl(const String &url, String &responseOut, int &httpCodeOut, int timeoutMs = 7000, bool followRedirects = true)
{
  if (url.startsWith("https://"))
  {
    String host, path;
    if (!splitHttpsUrl(url, host, path))
    {
      httpCodeOut = -1;
      responseOut = "Bad HTTPS URL";
      return false;
    }
    return httpsGetRaw(host, path, responseOut, httpCodeOut, timeoutMs);
  }

  HTTPClient http;
  http.begin(url);

  http.setUserAgent("Mozilla/5.0 (ESP32; Arduino)");
  http.addHeader("Accept", "application/json");
  http.addHeader("Accept-Encoding", "identity");
  http.setTimeout(timeoutMs);
  http.setFollowRedirects(followRedirects ? HTTPC_STRICT_FOLLOW_REDIRECTS : HTTPC_DISABLE_FOLLOW_REDIRECTS);
  int httpCode = http.GET();
  httpCodeOut = httpCode;
  responseOut = http.getString();
  if (httpCode == 200)
  {
    http.end();
    return true;
  }
  http.end();
  return false;
}

static String urlEncode(const String &s)
{
  String out;
  out.reserve(s.length() * 3);
  for (size_t i = 0; i < s.length(); ++i)
  {
    char c = s[i];
    if ((c >= 'a' && c <= 'z') ||
        (c >= 'A' && c <= 'Z') ||
        (c >= '0' && c <= '9') ||
        c == '-' || c == '_' || c == '.' || c == '~')
    {
      out += c;
    }
    else
    {
      char buf[4];
      snprintf(buf, sizeof(buf), "%%%02X", (unsigned char)c);
      out += buf;
    }
  }
  return out;
}

// ✅ Call voucher server to verify code (InfinityFree API)
bool verifyVoucherWithServer(const String &code, int &minutesOut)
{
  if (WiFi.status() != WL_CONNECTED)
  {
    Serial.println("❌ No STA connection for voucher check");
    return false;
  }

  String payload;
  String url = String(VOUCHER_SERVER) + "/api/validate.php?code=" + urlEncode(code);

  int httpCode = 0;
  if (!httpGetUrl(url, payload, httpCode))
  {
    Serial.println("❌ Voucher validate HTTP " + String(httpCode));
    Serial.println("❌ Voucher validate payload: " + payload);
    return false;
  }

  // Expected JSON: {"ok":true,"voucher":{"code":"...","minutes":30,"expiry_date":null,"status":"UNUSED"}}
  if (payload.indexOf("\"ok\":true") > 0)
  {
    int minIdx = payload.indexOf("\"minutes\":");
    int statusIdx = payload.indexOf("\"status\":\"");
    if (minIdx > 0 && statusIdx > 0)
    {
      String minStr = payload.substring(minIdx + 10);
      int minEnd = minStr.indexOf(",");
      if (minEnd > 0)
        minStr = minStr.substring(0, minEnd);
      minutesOut = minStr.toInt();

      String statusStr = payload.substring(statusIdx + 10);
      int statusEnd = statusStr.indexOf("\"");
      if (statusEnd > 0)
        statusStr = statusStr.substring(0, statusEnd);

      if (statusStr == "UNUSED" && minutesOut > 0)
      {
        String redeemPayload;
        String redeemUrl = String(VOUCHER_SERVER) + "/api/redeem.php?code=" + urlEncode(code);
        int redeemCode = 0;
        httpGetUrl(redeemUrl, redeemPayload, redeemCode);

        if (redeemCode == 200)
        {
          Serial.println("✅ Voucher verified: " + String(minutesOut) + " minutes");
          return true;
        }
        Serial.println("❌ Voucher redeem HTTP " + String(redeemCode));
        Serial.println("❌ Voucher redeem payload: " + redeemPayload);
      }
    }
  }
  Serial.println("❌ Voucher validate payload: " + payload);

  return false;
}

// ================= HELPERS =================
void smartDelay(unsigned long ms)
{
  unsigned long t = millis();
  while (millis() - t < ms)
  {
    server.handleClient();
    dns.processNextRequest();
    delay(1);
  }
}

int findUser(const String &id)
{
  for (int i = 0; i < MAX_USERS; i++)
  {
    if (users[i].inUse && users[i].id == id)
      return i;
  }
  return -1;
}

int ensureUser(const String &id)
{
  int idx = findUser(id);
  if (idx >= 0)
    return idx;

  for (int i = 0; i < MAX_USERS; i++)
  {
    if (!users[i].inUse)
    {
      users[i].inUse = true;
      users[i].id = id;
      users[i].bottles = 0;
      users[i].creditSec = 0;
      users[i].sessionStartMs = 0;
      users[i].sessionEndMs = 0;
      users[i].expired = false;
      return i;
    }
  }
  return -1;
}

bool userHasInternet(const String &ip)
{
  if (!powerOn)
    return false;
  int idx = findUser(ip);
  if (idx < 0)
    return false;

  // ✅ Check if expired flag is set
  if (users[idx].expired)
    return false;

  return (users[idx].sessionEndMs > 0) && (users[idx].sessionEndMs > millis()) && natState;
}

String requesterId()
{
  IPAddress ip = server.client().remoteIP();
  return ip.toString();
}

void applyPowerState(bool on)
{
  powerOn = on;

  digitalWrite(GREEN_LED_PIN, powerOn ? HIGH : LOW);
  digitalWrite(RED_LED_PIN, powerOn ? LOW : HIGH);

  if (!powerOn)
  {
    servo.write(SERVO_CLOSED);
    digitalWrite(BUZZER_PIN, LOW);

    bottleAccepted = false;
    detectWindowActive = false;
    stableHits = 0;
    emptyStableStart = 0;
    justTared = false;

    dropperLocked = false;
    activeDropperId = "";

    for (int i = 0; i < MAX_USERS; i++)
    {
      users[i].sessionStartMs = 0;
      users[i].sessionEndMs = 0;
      users[i].creditSec = 0;
      users[i].expired = false;
    }

    setNAT(false);

    if (!dnsHijackOn)
    {
      dns.start(53, "*", WiFi.softAPIP());
    }
    dnsHijackOn = true;
    return;
  }

  servo.write(SERVO_CLOSED);
  digitalWrite(BUZZER_PIN, LOW);

  setNAT(false);

  if (!dnsHijackOn)
  {
    dns.start(53, "*", WiFi.softAPIP());
  }
  dnsHijackOn = true;
}

void captiveSend()
{
  server.sendHeader("Cache-Control", "no-cache, no-store, must-revalidate");
  server.sendHeader("Pragma", "no-cache");
  server.sendHeader("Expires", "0");
  server.send(200, "text/html", INDEX_HTML);
}

void creditBottleToActiveUser()
{
  if (!dropperLocked || activeDropperId.length() == 0)
    return;

  int idx = findUser(activeDropperId);
  if (idx >= 0)
  {
    users[idx].bottles++;
    Serial.print("🎁 Bottle credited to ");
    Serial.print(activeDropperId);
    Serial.print(" | total bottles = ");
    Serial.println(users[idx].bottles);
  }
}

void servoPulseMove(int openAngle, int closeAngle, unsigned long openMs)
{
  // Keep buzzer off during servo movement to reduce power dip
  digitalWrite(BUZZER_PIN, LOW);

  // Re-init PWM in case of a stalled channel
  servo.detach();
  delay(5);
  servo.attach(SERVO_PIN, 500, 2500);

  servo.write(openAngle);
  smartDelay(openMs);
  servo.write(closeAngle);
  smartDelay(200);
}

void servoOpenCloseWithRetry()
{
  // Single cycle by default (faster). Retry only if still blocked / not cleared.
  servoPulseMove(SERVO_OPEN, SERVO_CLOSED, SERVO_OPEN_MS);

  smartDelay(120);
  const bool irStillBlocked = (digitalRead(IR1_PIN) == LOW) || (digitalRead(IR2_PIN) == LOW);
  const float gramsNow = scale.get_units(1);
  if (irStillBlocked || gramsNow > ZERO_CLAMP_G)
  {
    smartDelay(SERVO_RETRY_GAP_MS);
    servoPulseMove(SERVO_OPEN, SERVO_CLOSED, SERVO_OPEN_MS);
  }
}

// ================= WEB UI (UPDATED WITH EXPIRY CHECK) =================
const char INDEX_HTML[] PROGMEM = R"HTML(
              <!doctype html>
              <html lang="en">
              <head>
              <meta charset="utf-8"/>
              <meta name="viewport" content="width=device-width, initial-scale=1"/>
              <title>Bottle WiFi</title>

              <style>
                :root{
                  --bg1:#070b18; --bg2:#0b1530;
                  --text:#eaf1ff; --muted:rgba(234,241,255,.75);
                  --stroke:rgba(255,255,255,.10);
                  --blue:#2b66ff; --green:#19c37d; --red:#ff4d4d; --amber:#ffcc66;
                  --shadow: 0 18px 45px rgba(0,0,0,.45);
                  --r:18px;
                }
                *{box-sizing:border-box}
                body{
                  margin:0;
                  font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
                  color:var(--text);
                  background:
                    radial-gradient(1200px 700px at 10% 10%, rgba(43,102,255,.22), transparent 60%),
                    radial-gradient(900px 600px at 95% 15%, rgba(25,195,125,.18), transparent 55%),
                    linear-gradient(180deg, var(--bg1), var(--bg2));
                  min-height:100vh;
                  display:flex;
                  align-items:center;
                  justify-content:center;
                  padding:16px;
                }
                .wrap{width:min(780px, 100%); display:grid; gap:14px;}
                .brand{
                  padding:16px 16px;
                  border:1px solid var(--stroke);
                  border-radius:var(--r);
                  background:linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.03));
                  box-shadow: var(--shadow);
                }
                .brand h1{
                  margin:0; font-size:18px; letter-spacing:.2px;
                  display:flex; align-items:center; gap:10px;
                }
                .dot{
                  width:10px; height:10px; border-radius:999px; background:var(--amber);
                  box-shadow:0 0 18px rgba(255,204,102,.55);
                }
                .sub{margin:6px 0 0; color:var(--muted); font-size:13px; line-height:1.35}

                .grid{
                  display:grid;
                  grid-template-columns: 1.1fr .9fr;
                  gap:14px;
                }
                @media (max-width:760px){ .grid{grid-template-columns:1fr} }

                .card{
                  border:1px solid var(--stroke);
                  border-radius:var(--r);
                  background:linear-gradient(180deg, rgba(255,255,255,.05), rgba(255,255,255,.02));
                  box-shadow: var(--shadow);
                  overflow:hidden;
                }
                .card .hd{
                  padding:14px 16px;
                  background:linear-gradient(180deg, rgba(255,255,255,.06), transparent);
                  border-bottom:1px solid var(--stroke);
                  display:flex; align-items:center; justify-content:space-between; gap:10px;
                }
                .card .hd b{font-size:14px}
                .pill{
                  display:inline-flex; align-items:center; gap:8px;
                  padding:6px 10px;
                  border:1px solid var(--stroke);
                  border-radius:999px;
                  color:var(--muted);
                  font-size:12px;
                  background:rgba(0,0,0,.15);
                }
                .content{padding:16px}
                .row{display:flex; gap:10px; flex-wrap:wrap}
                button{
                  border:0; cursor:pointer;
                  border-radius:14px;
                  padding:12px 14px;
                  font-weight:800;
                  letter-spacing:.2px;
                  color:#fff;
                  transition: transform .06s ease, opacity .2s ease;
                  min-width: 150px;
                }
                button:active{transform: translateY(1px)}
                button:disabled{opacity:.45; cursor:not-allowed; transform:none}
                .btn-blue{background:linear-gradient(180deg, rgba(43,102,255,1), rgba(43,102,255,.78))}
                .btn-green{background:linear-gradient(180deg, rgba(25,195,125,1), rgba(25,195,125,.78))}
                .btn-red{background:linear-gradient(180deg, rgba(255,77,77,1), rgba(255,77,77,.78))}
                .hint{margin-top:10px; color:var(--muted); font-size:12.5px; line-height:1.35}
                .big{
                  font-size:16px; font-weight:900;
                  margin:2px 0 10px;
                  display:flex; align-items:center; justify-content:space-between; gap:10px;
                }
                .status{
                  margin-top:12px;
                  padding:12px 12px;
                  border-radius:14px;
                  border:1px solid var(--stroke);
                  background:rgba(0,0,0,.18);
                  color:var(--muted);
                  font-size:13px;
                  line-height:1.35;
                }

                .prog{
                  margin-top:10px;
                  border:1px solid var(--stroke);
                  background:rgba(0,0,0,.16);
                  border-radius:14px;
                  overflow:hidden;
                }
                .bar{
                  height:12px;
                  width:0%;
                  background:linear-gradient(90deg, rgba(43,102,255,1), rgba(25,195,125,1));
                  transition: width .35s ease;
                }
                .kpi{
                  display:grid;
                  grid-template-columns: 1fr 1fr;
                  gap:10px;
                  margin-top:12px;
                }
                .tile{
                  border:1px solid var(--stroke);
                  background:rgba(0,0,0,.16);
                  border-radius:14px;
                  padding:12px;
                }
                .tile .t{color:var(--muted); font-size:12px}
                .tile .v{font-weight:900; font-size:18px; margin-top:4px}
                .mono{font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace}

                .swal-backdrop{
                  position:fixed; inset:0;
                  background:rgba(0,0,0,.55);
                  display:none;
                  align-items:center;
                  justify-content:center;
                  padding:18px;
                  z-index:10002;
                }
                .swal{
                  width:min(420px, 100%);
                  border-radius:18px;
                  border:1px solid rgba(255,255,255,.12);
                  background:linear-gradient(180deg, rgba(255,255,255,.08), rgba(0,0,0,.18));
                  box-shadow:0 25px 70px rgba(0,0,0,.55);
                  overflow:hidden;
                  transform: translateY(8px);
                  opacity:0;
                  transition: all .18s ease;
                }
                .swal.show{ transform: translateY(0); opacity:1; }
                .swal-hd{
                  padding:16px 16px 10px;
                  display:flex; gap:12px; align-items:flex-start;
                }
                .swal-ico{
                  width:44px; height:44px; border-radius:14px;
                  display:grid; place-items:center;
                  background:rgba(255,255,255,.08);
                  border:1px solid rgba(255,255,255,.10);
                  font-size:22px;
                  flex:0 0 auto;
                  color:#eaf1ff;
                }
                .swal-ico svg{ width:24px; height:24px; }
                .swal-title{ font-weight:900; font-size:16px; margin:0; }
                .swal-text{
                  margin-top:4px;
                  color:rgba(234,241,255,.8);
                  font-size:13.5px; line-height:1.35;
                }
                .swal-actions{
                  padding:12px 16px 16px;
                  display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap;
                }
                .swal-actions button{
                  min-width:auto;
                  padding:10px 12px;
                  border-radius:12px;
                  font-weight:900;
                }
                .swal-btn-cancel{
                  background:rgba(255,255,255,.08);
                  color:#eaf1ff;
                  border:1px solid rgba(255,255,255,.12);
                }
                .swal-btn-ok{ background:linear-gradient(180deg, rgba(25,195,125,1), rgba(25,195,125,.78)); }
                .swal-btn-blue{ background:linear-gradient(180deg, rgba(43,102,255,1), rgba(43,102,255,.78)); }
                .swal-btn-red{ background:linear-gradient(180deg, rgba(255,77,77,1), rgba(255,77,77,.78)); }

                .toast{
                  position:fixed;
                  left:50%; bottom:18px;
                  transform:translateX(-50%);
                  padding:10px 12px;
                  border-radius:14px;
                  border:1px solid rgba(255,255,255,.12);
                  background:rgba(0,0,0,.55);
                  color:#eaf1ff;
                  font-size:13px;
                  opacity:0;
                  pointer-events:none;
                  transition:opacity .2s ease, transform .2s ease;
                  z-index:10000;
                }
                .toast.show{
                  opacity:1;
                  transform:translateX(-50%) translateY(-2px);
                }

                .actions{
                  display: grid;
                  grid-template-columns: 1fr;
                  gap: 10px;
                }

                @media (min-width: 480px){
                  .actions{
                    grid-template-columns: 1fr 1fr;
                  }
                }

                @media (min-width: 768px){
                  .actions{
                    grid-template-columns: repeat(4, 1fr);
                  }
                }
                
                /* ✅ EXPIRY MODAL */
                #expiredOverlay{
                  position:fixed; inset:0;
                  background:rgba(0,0,0,.85);
                  display:none;
                  align-items:center;
                  justify-content:center;
                  z-index:10001;
                }
                #expiredModal{
                  background:#111a2e;
                  border:2px solid #ff4d4d;
                  border-radius:18px;
                  padding:24px;
                  text-align:center;
                  max-width:400px;
                }
                #expiredModal h2{
                  color:#ff4d4d;
                  margin:0 0 12px;
                }
              </style>
              </head>

              <body>
                <div class="wrap">
                  <div class="brand">
                    <h1><span class="dot" id="dot"></span> Bottle WiFi Portal</h1>
                    <div class="sub">
                      Drop bottles -> redeem minutes -> start internet. <b>One active dropper</b> at a time.
                    </div>
                  </div>

                  <div class="grid">
                    <div class="card">
                      <div class="hd">
                        <b>Actions</b>
                        <span class="pill"><span class="mono" id="me">...</span></span>
                      </div>
                      <div class="content">
                        <div class="big">
                          <div id="activeLine">Active dropper: ...</div>
                          <span class="pill" id="pwrPill">Power: ...</span>
                        </div>

                        <div class="actions">
                          <button class="btn-blue" id="connectBtn">CONNECT</button>
                          <button class="btn-green" id="redeemBtn" onclick="redeem()" disabled>REDEEM</button>
                          <button class="btn-blue" onclick="scanQR()">SCAN VOUCHER</button>
                          <button class="btn-green" id="startBtn" onclick="startNet()" disabled>START</button>
                          <button class="btn-blue" id="addTimeBtn" onclick="addTime()" style="display:none">ADD</button>
                        </div>

                        <div class="hint">
                          Tip: If "no internet" after START, toggle WiFi OFF/ON on your phone once.
                        </div>

                        <div class="status" id="msg">Loading...</div>
                      </div>
                    </div>

                    <div class="card">
                      <div class="hd">
                        <b>My Stats</b>
                        <span class="pill" id="lockPill">Lock: ...</span>
                      </div>
                      <div class="content">
                        <div class="kpi">
                          <div class="tile">
                            <div class="t">My bottles</div>
                            <div class="v" id="btl">0</div>
                          </div>
                          <div class="tile">
                            <div class="t">My credit</div>
                            <div class="v" id="cred">0:00</div>
                          </div>
                          <div class="tile">
                            <div class="t">Time left</div>
                            <div class="v" id="rem">0s</div>
                          </div>
                          <div class="tile">
                            <div class="t">Redeem progress</div>
                            <div class="v" id="progTxt">0 / 5</div>
                          </div>
                        </div>

                        <div class="prog"><div class="bar" id="bar"></div></div>
                        <div class="status" id="statusNote">Ready.</div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="swal-backdrop" id="swalBackdrop">
                  <div class="swal" id="swalBox">
                    <div class="swal-hd">
                      <div class="swal-ico" id="swalIco"></div>
                      <div>
                        <div class="swal-title" id="swalTitle">Title</div>
                        <div class="swal-text" id="swalText">Message</div>
                      </div>
                    </div>
                    <div class="swal-actions">
                      <button class="swal-btn-cancel" id="swalCancel">Cancel</button>
                      <button class="swal-btn-ok" id="swalOk">OK</button>
                    </div>
                  </div>
                </div>

                <div class="toast" id="toast"></div>

                <!-- ✅ EXPIRY MODAL -->
                <div id="expiredOverlay">
                  <div id="expiredModal">
                    <h2>⏱️ Session Expired</h2>
                    <p>Your internet time has ended.</p>
                    <button class="btn-blue" onclick="location.reload()">RELOAD PAGE</button>
                  </div>
                </div>

                <div id="qrModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.7); z-index:9999;">
                  <div style="max-width:420px; margin:40px auto; background:#111a2e; padding:14px; border-radius:14px; position:relative;">
                    <h3>Scan / Upload / Type Voucher</h3>
                    <div id="qr-reader" style="width:100%; position:relative; z-index:1;"></div>
                    <button class="btn-blue" style="margin-top:10px" onclick="document.getElementById('qrFile').click()">UPLOAD QR</button>
                    <input type="file" id="qrFile" accept="image/*" style="margin-top:10px; display:block; width:100%; color:#eaf1ff; background:#121a33; border:1px solid rgba(255,255,255,.12); padding:8px; border-radius:8px; position:relative; z-index:2;"/>
                    <input id="qrText" placeholder="Enter code manually" style="width:100%; margin-top:10px; padding:10px; border-radius:8px"/>
                    <button class="btn-green" style="margin-top:10px" onclick="submitQR()">SUBMIT</button>
                    <button class="btn-red" style="margin-top:10px" onclick="closeQR()">CLOSE</button>
                  </div>
                </div>

                <script src="https://unpkg.com/html5-qrcode"></script>
                <script>
              let qrScanner = null;
              let scannedValue = "";
              let lastExpiredCheck = 0;

              function scanQR(){
                document.getElementById('qrModal').style.display = "block";
                scannedValue = "";
                if (typeof Html5Qrcode === "undefined") {
                  swalAlert({
                    icon: "question",
                    title: "Scanner unavailable",
                    text: "Offline mode can't load the QR scanner. Please type the code manually.",
                    okText: "OK",
                    okClass: "swal-btn-blue"
                  });
                  const input = document.getElementById("qrText");
                  if (input) { input.focus(); }
                  return;
                }
                qrScanner = new Html5Qrcode("qr-reader");
                qrScanner.start(
                  { facingMode: "environment" },
                  { fps: 10, qrbox: 250 },
                  (text) => {
                    scannedValue = text;
                    qrScanner.stop();
                    submitQR();
                  }
                ).catch(()=>{});
              }

              document.getElementById("qrFile").addEventListener("change", async (e)=>{
                const file = e.target.files[0];
                if(!file) return;
                if (typeof Html5Qrcode === "undefined") {
                  swalAlert({
                    icon: "question",
                    title: "Upload not available",
                    text: "Offline mode can't decode images. Please type the code manually.",
                    okText: "OK",
                    okClass: "swal-btn-blue"
                  });
                  e.target.value = "";
                  return;
                }
                if(!qrScanner) qrScanner = new Html5Qrcode("qr-reader");
                const text = await qrScanner.scanFile(file, true);
                scannedValue = text;
                submitQR();
                e.target.value = "";
              });

              async function submitQR(){
                const manual = document.getElementById("qrText").value.trim();
                const code = scannedValue || manual;
                if(!code){ toast("No voucher code detected"); return; }
                try{
                  const r = await api('/verifyQR?code=' + encodeURIComponent(code), 'POST', 12000);
                  if (r && r.ok) {
                    await swalAlert({
                      icon: "gift",
                      title: "Voucher accepted",
                      text: r.msg || "Credit added to My Credit.",
                      okText: "OK",
                      okClass: "swal-btn-ok"
                    });
                  } else {
                    await swalAlert({
                      icon: "question",
                      title: "Voucher not accepted",
                      text: (r && r.msg) ? r.msg : "Please try again.",
                      okText: "OK",
                      okClass: "swal-btn-red"
                    });
                  }
                  closeQR();
                  refresh();
                }catch(e){
                  await swalAlert({
                    icon: "question",
                    title: "Syncing...",
                    text: "If the voucher was valid, credit will appear shortly. Please refresh.",
                    okText: "OK",
                    okClass: "swal-btn-blue"
                  });
                  closeQR();
                  refresh();
                }
              }

              function closeQR(){
                if(qrScanner){
                  qrScanner.stop().catch(()=>{});
                  qrScanner = null;
                }
                document.getElementById('qrModal').style.display = "none";
              }

              let refreshing = false;
              let lastStatus = {};
              let isBusy = false;
              let cdTimer = null;
              let serverDelta = 0;
              let endAtLocal = 0;

              document.getElementById('connectBtn').addEventListener('click', connectMe);

              const ICONS = {
                question: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 2-3 4"/><line x1="12" y1="17" x2="12" y2="17"/></svg>`,
                lock: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>`,
                gift: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7c-1.5 0-3-1-3-2.5S10.5 2 12 4c1.5-2 3-1.5 3 0s-1.5 3-3 3z"/></svg>`,
                wifi: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5a10 10 0 0 1 14 0"/><path d="M8.5 16a6 6 0 0 1 7 0"/><path d="M12 20h0"/></svg>`
              };

              async function api(path, method="GET", timeout=6000){
                const controller = new AbortController();
                const id = setTimeout(() => controller.abort(), timeout);
                const url = path + (path.includes('?') ? '&' : '?') + '_ts=' + Date.now();
                try {
                  const r = await fetch(url, {
                    method,
                    cache: "no-store",
                    headers: {
                      "Cache-Control": "no-cache, no-store, must-revalidate",
                      "Pragma": "no-cache",
                      "Expires": "0"
                    },
                    signal: controller.signal
                  });
                  clearTimeout(id);
                  return await r.json();
                } catch (e) {
                  clearTimeout(id);
                  throw e;
                }
              }

              async function redeem(){
                const ok = await swalConfirm({
                  icon: "gift",
                  title: "Redeem bottles?",
                  text: "Convert bottles into internet time?",
                  okText: "YES, REDEEM",
                  cancelText: "Cancel",
                  okClass: "swal-btn-ok"
                });
                if (!ok) return;
                try{
                  const j = await api('/redeem','POST');
                  toast(j.msg || "Redeemed");
                  refresh();
                }catch(e){
                  toast("Redeem failed");
                }
              }

              function mmss(sec){
                sec = Math.max(0, sec|0);
                const m = Math.floor(sec/60);
                const s = sec%60;
                return m + ":" + (s<10?("0"+s):s);
              }

              function setDot(color){
                const d = document.getElementById('dot');
                d.style.background = color;
                d.style.boxShadow = "0 0 18px " + color + "55";
              }

              function toast(msg, ms=1500){
                const t=document.getElementById('toast');
                t.textContent=msg;
                t.classList.add('show');
                clearTimeout(window.__toastT);
                window.__toastT=setTimeout(()=>t.classList.remove('show'), ms);
              }

              function swalConfirm({icon="question", title="Confirm", text="", okText="OK", cancelText="Cancel", okClass="swal-btn-ok"}){
                return new Promise((resolve)=>{
                  const bd = document.getElementById('swalBackdrop');
                  const box= document.getElementById('swalBox');
                  const ico= document.getElementById('swalIco');
                  const ttl= document.getElementById('swalTitle');
                  const txt= document.getElementById('swalText');
                  const ok = document.getElementById('swalOk');
                  const ca = document.getElementById('swalCancel');

                  ico.innerHTML = ICONS[icon] || ICONS.question;
                  ttl.textContent = title;
                  txt.textContent = text;
                  ok.textContent = okText;
                  ca.textContent = cancelText;
                  ok.className = okClass;
                  ca.className = "swal-btn-cancel";
                  ca.style.display = "";

                  function close(val){
                    bd.style.display="none";
                    box.classList.remove('show');
                    ok.onclick = null;
                    ca.onclick = null;
                    bd.onclick = null;
                    resolve(val);
                  }

                  bd.style.display="flex";
                  requestAnimationFrame(()=> box.classList.add('show'));
                  ok.onclick = ()=> close(true);
                  ca.onclick = ()=> close(false);
                  bd.onclick = (e)=>{ if(e.target===bd) close(false); };
                });
              }

              function swalAlert({icon="question", title="Notice", text="", okText="OK", okClass="swal-btn-ok"}){
                return new Promise((resolve)=>{
                  const bd = document.getElementById('swalBackdrop');
                  const box= document.getElementById('swalBox');
                  const ico= document.getElementById('swalIco');
                  const ttl= document.getElementById('swalTitle');
                  const txt= document.getElementById('swalText');
                  const ok = document.getElementById('swalOk');
                  const ca = document.getElementById('swalCancel');

                  ico.innerHTML = ICONS[icon] || ICONS.question;
                  ttl.textContent = title;
                  txt.textContent = text;
                  ok.textContent = okText;
                  ok.className = okClass;
                  ca.style.display = "none";

                  function close(){
                    bd.style.display="none";
                    box.classList.remove('show');
                    ok.onclick = null;
                    ca.onclick = null;
                    bd.onclick = null;
                    resolve(true);
                  }

                  bd.style.display="flex";
                  requestAnimationFrame(()=> box.classList.add('show'));
                  ok.onclick = ()=> close();
                  bd.onclick = (e)=>{ if(e.target===bd) close(); };
                });
              }

              // ✅ CHECK FOR SESSION EXPIRY
              let expiredShown = false;
              async function checkExpiry() {
                try {
                  const j = await api('/checkExpired', 'GET', 3000);
                  if (j.expired && !expiredShown) {
                    document.getElementById('expiredOverlay').style.display = 'flex';
                    clearInterval(cdTimer);
                    cdTimer = null;
                    expiredShown = true;
                  }
                } catch(e) {}
              }

              async function refresh() {
                if (refreshing) return;
                refreshing = true;

                // ✅ CHECK EXPIRY EVERY 3 SECONDS
                const now = Date.now();
                if (now - lastExpiredCheck > 3000) {
                  lastExpiredCheck = now;
                  checkExpiry();
                }

                if (cdTimer && (endAtLocal === 0)) {
                  clearInterval(cdTimer);
                  cdTimer = null;
                  document.getElementById('rem').textContent = "0:00";
                }

                try {
                  const j = await api('/status');
                  lastStatus = j;

                  if ((j.timeLeftSec | 0) <= 0 && j.powerOn) {
                    document.getElementById('statusNote').textContent = "Session expired. Please reconnect to continue.";
                  }

                  const addBtn = document.getElementById('addTimeBtn');
                  const hasActiveSession = (j.endMs | 0) > 0 && (j.timeLeftSec | 0) > 0;
                  const hasCredit = (j.myCreditSec | 0) > 0;
                  addBtn.style.display = (hasActiveSession && hasCredit) ? "block" : "none";

                  document.getElementById('me').textContent = j.me;
                  document.getElementById('activeLine').textContent = "Active dropper: " + (j.activeDropper || "none");

                  const pwr = document.getElementById('pwrPill');
                  pwr.textContent = "Power: " + (j.powerOn ? "ON" : "OFF");

                  const lockedByOther = j.dropperLocked && j.activeDropper && (j.activeDropper !== j.me);
                  const isMe = (j.activeDropper === j.me);

                  const connectBtn = document.getElementById('connectBtn');
                  if (isMe) {
                    connectBtn.textContent = "DISCONNECT";
                    connectBtn.classList.remove('btn-blue');
                    connectBtn.classList.add('btn-red');
                    connectBtn.dataset.connected = "true";
                    connectBtn.disabled = false;
                  } else {
                    connectBtn.textContent = "CONNECT";
                    connectBtn.classList.remove('btn-red');
                    connectBtn.classList.add('btn-blue');
                    connectBtn.dataset.connected = "false";
                    connectBtn.disabled = (!j.powerOn) || lockedByOther;
                  }

                  document.getElementById('redeemBtn').disabled = !(j.powerOn && isMe && (j.myBottles >= 5));
                  document.getElementById('startBtn').disabled = !(j.powerOn && isMe && (j.myCreditSec > 0) && (j.timeLeftSec === 0));
                  document.getElementById('btl').textContent = j.myBottles;
                  document.getElementById('cred').textContent = mmss(j.myCreditSec);

                  if ((j.endMs | 0) > 0) {
                    serverDelta = Date.now() - (j.nowMs | 0);
                    endAtLocal = (j.endMs | 0) + serverDelta;

                    if (cdTimer) {
                      clearInterval(cdTimer);
                      cdTimer = null;
                    }

                    cdTimer = setInterval(() => {
                      const left = Math.max(0, Math.floor((endAtLocal - Date.now()) / 1000));
                      document.getElementById('rem').textContent = mmss(left);
                      if (left <= 0) {
                        clearInterval(cdTimer);
                        cdTimer = null;
                        checkExpiry();
                      }
                    }, 1000);
                  } else {
                    if (cdTimer) {
                      clearInterval(cdTimer);
                      cdTimer = null;
                    }
                    document.getElementById('rem').textContent = "0:00";
                  }

                  if ((j.startMs | 0) > 0 && (j.endMs | 0) > 0 && (j.nowMs | 0) > 0) {
                    const totalSec = Math.floor((j.endMs - j.startMs) / 1000);
                    const leftSec = Math.floor((j.endMs - j.nowMs) / 1000);
                    const usedSec = Math.max(0, totalSec - leftSec);
                    const percent = Math.min(100, (usedSec / totalSec) * 100);
                    document.getElementById('progTxt').textContent = mmss(leftSec) + " left";
                    document.getElementById('bar').style.width = percent.toFixed(1) + "%";
                  } else {
                    document.getElementById('progTxt').textContent = "0:00";
                    document.getElementById('bar').style.width = "0%";
                  }

                  const lockP = document.getElementById('lockPill');
                  if (j.dropperLocked && j.activeDropper) {
                    lockP.textContent = isMe ? "Lock: YOU" : "Lock: BUSY";
                  } else {
                    lockP.textContent = "Lock: FREE";
                  }

                  const note = document.getElementById('statusNote');
                  if (!j.powerOn)
                    note.textContent = "Power is OFF. Detection is disabled.";
                  else if (lockedByOther)
                    note.textContent = "Busy: another user is the active dropper.";
                  else if (isMe)
                    note.textContent = "You are the active dropper. Drop bottles -> REDEEM -> start internet.";
                  else
                    note.textContent = "Ready. Tap CONNECT to become the active dropper.";

                  document.getElementById('msg').textContent = j.msg || ("Updated: " + new Date().toLocaleTimeString());
                } catch (e) {
                  document.getElementById('msg').textContent = "Failed to fetch. Please wait...";
                }

                refreshing = false;
              }

              async function addTime(){
                const maxMin = Math.floor((lastStatus.myCreditSec || 0) / 60);
                if (maxMin <= 0) { toast("No credit available"); return; }
                let mins = Number(prompt("Enter minutes (1 - " + maxMin + "):"));
                if (!Number.isInteger(mins) || mins <= 0 || mins > maxMin) { toast("Invalid number"); return; }
                try{
                  const j = await api('/addtime?min=' + mins, 'POST');
                  toast(j.msg || "Time added");
                  refresh();
                }catch(e){
                  toast("Failed to add time");
                }
              }

              async function connectMe(){
                document.getElementById('msg').textContent = "Connecting...";
                document.getElementById('connectBtn').disabled = true;

                const btn = document.getElementById('connectBtn');

                if (btn.dataset.connected === 'true') {
                  const ok = await swalConfirm({
                    icon: "lock",
                    title: "Disconnect as active dropper?",
                    text: "You will release the lock and allow others to connect.",
                    okText: "Disconnect",
                    cancelText: "Cancel",
                    okClass: "swal-btn-red"
                  });
                  if (!ok){
                    document.getElementById('connectBtn').disabled = false;
                    return;
                  }
                  const j = await api('/disconnect','POST');
                  toast(j.msg || "Disconnected");
                  refresh();
                  return;
                }

                const ok = await swalConfirm({
                  icon:"lock",
                  title:"Connect as active dropper?",
                  text:"Only you can drop bottles while the lock is active. Continue?",
                  okText:"Connect",
                  cancelText:"Cancel",
                  okClass:"swal-btn-blue"
                });

                if(!ok){
                  document.getElementById('connectBtn').disabled = false;
                  document.getElementById('msg').textContent = "Cancelled.";
                  return;
                }

                try {
                  const j = await api('/connect','POST');
                  toast(j.msg || "Connected");
                } catch(e) {
                  toast("Connected (syncing...)");
                }

                document.getElementById('connectBtn').disabled = false;
                await refresh();
              }

              document.addEventListener("visibilitychange", () => {
                if (!document.hidden) { refresh(); }
              });

              async function startNet(){
                if(isBusy) return;
                isBusy = true;

                const ok = await swalConfirm({
                  icon:"wifi",
                  title:"Start internet?",
                  text:"Please wait while the system is working.",
                  okText:"Start",
                  cancelText:"Cancel",
                  okClass:"swal-btn-ok"
                });

                if(!ok){
                  isBusy = false;
                  return;
                }

                document.getElementById('rem').textContent = "starting...";
                fetch('/start', { method:'POST' }).catch(()=>{});

                let tries = 0;
                const iv = setInterval(async ()=>{
                  try{
                    const j = await api('/status','GET',3000);
                    if ((j.timeLeftSec | 0) > 0) {
                      toast("Internet started");
                      clearInterval(iv);
                      isBusy = false;
                      refresh();
                    }
                  }catch(e){}
                  tries++;
                  if (tries > 10) {
                    toast("Please wait...");
                    clearInterval(iv);
                    isBusy = false;
                  }
                }, 700);
              }

              setInterval(() => {
                if (!refreshing) refresh();
              }, 2000);

              refresh();
              // Reset expiredShown on reload
              window.addEventListener('beforeunload', () => { expiredShown = false; });
                </script>
              </body>
              </html>
              )HTML";

// ================= WEB SETUP =================
void setupWeb()
{
  server.on("/", HTTP_GET, []()
            { captiveSend(); });

  server.on("/generate_204", HTTP_GET, []()
            {
                  String me = requesterId();
                  if (userHasInternet(me)) {
                    server.send(204);
                  } else {
                    server.sendHeader("Location", "http://192.168.50.1/", true);
                    server.send(302, "text/plain", "");
                  } });

  server.on("/gen_204", HTTP_GET, []()
            {
                  String me = requesterId();
                  if (userHasInternet(me)) {
                    server.send(204);
                  } else {
                    server.sendHeader("Location", "http://192.168.50.1/", true);
                    server.send(302, "text/plain", "");
                  } });

  server.on("/hotspot-detect.html", HTTP_GET, []()
            {
                  String me = requesterId();
                  if (userHasInternet(me)) {
                    server.send(200, "text/html", "<HTML><HEAD><TITLE>Success</TITLE></HEAD><BODY>Success</BODY></HTML>");
                  } else {
                    captiveSend();
                  } });

  server.on("/library/test/success.html", HTTP_GET, []()
            { captiveSend(); });
  server.on("/success.txt", HTTP_GET, []()
            { captiveSend(); });

  server.on("/connecttest.txt", HTTP_GET, []()
            {
                  String me = requesterId();
                  if (userHasInternet(me)) {
                    server.send(200, "text/plain", "Microsoft Connect Test");
                  } else {
                    server.sendHeader("Location", "http://192.168.50.1/", true);
                    server.send(302, "text/plain", "");
                  } });

  server.on("/redirect", HTTP_GET, []()
            { captiveSend(); });
  server.on("/ncsi.txt", HTTP_GET, []()
            { captiveSend(); });

  // ✅ NEW ENDPOINT: Check if session expired
  server.on("/checkExpired", HTTP_GET, []()
            {
                  String me = requesterId();
                  int idx = findUser(me);
                  
                  bool expired = false;
                  if (idx >= 0 && users[idx].expired) {
                    expired = true;
                  }
                  
                  String json = "{\"expired\":" + String(expired ? "true" : "false") + "}";
                  server.send(200, "application/json", json); });

  server.on("/connect", HTTP_POST, []()
            {
                  String me = requesterId();
                  int idx = ensureUser(me);
                  // Always clear expired flag on connect attempt
                  if (idx >= 0) {
                    users[idx].expired = false;
                  }

                  if (!powerOn) {
                    server.send(200, "application/json", "{\"ok\":false,\"msg\":\"Power OFF\"}");
                    return;
                  }

                  if (dropperLocked && activeDropperId != me && activeDropperId.length()) {
                    server.send(200, "application/json", "{\"ok\":false,\"msg\":\"Busy\"}");
                    return;
                  }

                  activeDropperId = me;
                  dropperLocked = true;
                  server.send(200, "application/json", "{\"ok\":true,\"msg\":\"Connected\"}"); });

  server.on("/disconnect", HTTP_POST, []()
            {
                  String me = requesterId();
                  if (activeDropperId == me && dropperLocked) {
                    dropperLocked = false;
                    activeDropperId = "";
                    server.send(200, "application/json", "{\"ok\":true,\"msg\":\"Disconnected\"}");
                  } else {
                    server.send(200, "application/json", "{\"ok\":false,\"msg\":\"Not active dropper\"}");
                  } });

  server.on("/redeem", HTTP_POST, []()
            {
                  String me = requesterId();
                  int idx = findUser(me);

                  if (idx < 0) {
                    server.send(200, "application/json", "{\"ok\":false,\"msg\":\"User not found\"}");
                    return;
                  }
                  if (!powerOn) {
                    server.send(200, "application/json", "{\"ok\":false,\"msg\":\"Power OFF\"}");
                    return;
                  }
                  if (activeDropperId != me) {
                    server.send(200, "application/json", "{\"ok\":false,\"msg\":\"Not active dropper\"}");
                    return;
                  }

                  if (users[idx].bottles >= BOTTLES_PER_REDEEM) {
                    int sets = users[idx].bottles / BOTTLES_PER_REDEEM;
                    long addedSec = sets * SECONDS_PER_REDEEM;
                    users[idx].bottles -= sets * BOTTLES_PER_REDEEM;
                    users[idx].creditSec += addedSec;
                    server.send(200, "application/json",
                                "{\"ok\":true,\"msg\":\"Redeemed all (" + String(sets) +
                                    " x 5 bottles = " + String(addedSec / 60) + " minutes)\"}");
                  } else {
                    server.send(200, "application/json", "{\"ok\":false,\"msg\":\"Need at least 5 bottles\"}");
                  } });

  server.on("/addtime", HTTP_POST, []()
            {
                  String me = requesterId();
                  int idx = findUser(me);

                  if (idx < 0) {
                    server.send(200, "application/json", "{\"ok\":false,\"msg\":\"User not found\"}");
                    return;
                  }

                  if (users[idx].sessionEndMs <= millis()) {
                    server.send(200, "application/json", "{\"ok\":false,\"msg\":\"No active session\"}");
                    return;
                  }

                  int mins = 0;
                  if (server.hasArg("min"))
                    mins = server.arg("min").toInt();
                  else if (server.hasArg("plain"))
                    mins = server.arg("plain").toInt();

                  if (mins <= 0) {
                    server.send(200, "application/json", "{\"ok\":false,\"msg\":\"Invalid minutes\"}");
                    return;
                  }

                  long addSec = mins * 60L;
                  if (addSec > users[idx].creditSec) {
                    server.send(200, "application/json", "{\"ok\":false,\"msg\":\"Not enough credit\"}");
                    return;
                  }

                  users[idx].creditSec -= addSec;
                  users[idx].sessionEndMs += addSec * 1000UL;

                  if (activeDropperId == me) {
                    dropperLocked = false;
                    activeDropperId = "";
                  }

                  server.send(200, "application/json", "{\"ok\":true,\"msg\":\"Time added\"}"); });

  server.on("/start", HTTP_POST, []()
            {
                  String me = requesterId();
                  int idx = findUser(me);

                  if (idx < 0) {
                    server.send(200, "application/json", "{\"ok\":false,\"msg\":\"User not found\"}");
                    return;
                  }
                  if (!powerOn) {
                    server.send(200, "application/json", "{\"ok\":false,\"msg\":\"Power OFF\"}");
                    return;
                  }
                  if (activeDropperId != me) {
                    server.send(200, "application/json", "{\"ok\":false,\"msg\":\"Not active dropper\"}");
                    return;
                  }
                  if (users[idx].creditSec <= 0) {
                    server.send(200, "application/json", "{\"ok\":false,\"msg\":\"No credit\"}");
                    return;
                  }
                  if (WiFi.status() != WL_CONNECTED) {
                    server.send(200, "application/json", "{\"ok\":false,\"msg\":\"STA not connected\"}");
                    return;
                  }


                  users[idx].sessionStartMs = millis();
                  users[idx].sessionEndMs = users[idx].sessionStartMs + (unsigned long)users[idx].creditSec * 1000UL;
                  users[idx].creditSec = 0;
                  users[idx].expired = false;  // ✅ RESET EXPIRY FLAG

                  setNAT(true);
                  dns.stop();
                  dnsHijackOn = false;
                  
                  dropperLocked = false;
                  activeDropperId = "";

                  server.send(200, "application/json", "{\"ok\":true,\"msg\":\"Internet started\"}"); });

  // ✅ VOUCHER VERIFICATION - CALLS YOUR VOUCHER SERVER
  server.on("/verifyQR", HTTP_POST, []()
            {
                  String me = requesterId();
                  int idx = ensureUser(me);

                  if (!server.hasArg("code")) {
                    server.send(200, "application/json", "{\"ok\":false,\"msg\":\"No voucher code\"}");
                    return;
                  }

                  String code = server.arg("code");
                  int minutes = 0;
                  
                  // ✅ CALL EXTERNAL VOUCHER SERVER
                  bool valid = verifyVoucherWithServer(code, minutes);
                  
                  if (valid && minutes > 0) {
                    users[idx].creditSec += (minutes * 60);
                    
                    String json = "{\"ok\":true,\"msg\":\"Voucher accepted! +" + String(minutes) + " minutes\"}";
                    server.send(200, "application/json", json);
                    
                    Serial.println("✅ Voucher redeemed: " + code + " = " + String(minutes) + " min");
                  } else {
                    server.send(200, "application/json", "{\"ok\":false,\"msg\":\"Invalid or already used voucher\"}");
                  } });

  server.on("/status", HTTP_GET, []()
            {
                  String me = requesterId();
                  int idx = findUser(me);

                  long nowMs = millis();
                  long endMs = (idx >= 0) ? users[idx].sessionEndMs : 0;
                  long leftSec = (endMs > nowMs) ? (endMs - nowMs) / 1000 : 0;

                  String json = "{";
                  json += "\"me\":\"" + me + "\",";
                  json += "\"powerOn\":" + String(powerOn ? "true" : "false") + ",";
                  json += "\"dropperLocked\":" + String(dropperLocked ? "true" : "false") + ",";
                  json += "\"activeDropper\":\"" + activeDropperId + "\",";
                  json += "\"myBottles\":" + String(idx >= 0 ? users[idx].bottles : 0) + ",";
                  json += "\"myCreditSec\":" + String(idx >= 0 ? users[idx].creditSec : 0) + ",";
                  json += "\"startMs\":" + String(idx >= 0 ? users[idx].sessionStartMs : 0) + ",";
                  json += "\"endMs\":" + String(endMs) + ",";
                  json += "\"timeLeftSec\":" + String(leftSec) + ",";
                  json += "\"nowMs\":" + String(nowMs);
                  json += "}";

                  server.send(200, "application/json", json); });

  server.onNotFound([]()
                    {
                  String me = requesterId();
                  String u = server.uri();

                  if (u.startsWith("/status") ||
                      u.startsWith("/connect") ||
                      u.startsWith("/disconnect") ||
                      u.startsWith("/redeem") ||
                      u.startsWith("/start") ||
                      u.startsWith("/addtime") ||
                      u.startsWith("/verifyQR") ||
                      u.startsWith("/checkExpired")) {
                    server.send(404, "text/plain", "OK");
                    return;
                  }

                  if (userHasInternet(me)) {
                    server.send(404, "text/plain", "Not Found");
                    return;
                  }

                  captiveSend(); });

  server.begin();
  Serial.println("✅ Web server started with captive portal");
}

// ================= WIFI SETUP =================
void setupWiFi()
{
  WiFi.mode(WIFI_AP_STA);

  WiFi.begin(UP_SSID, UP_PASS);
  Serial.print("Connecting STA");
  unsigned long t0 = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - t0 < 20000)
  {
    delay(400);
    Serial.print(".");
  }
  Serial.println();

  if (WiFi.status() == WL_CONNECTED)
  {
    Serial.print("STA IP: ");
    Serial.println(WiFi.localIP());
  }
  else
  {
    Serial.println("⚠️ STA not connected. No real internet source.");
  }

  IPAddress apIP(192, 168, 50, 1);
  IPAddress apGW(192, 168, 50, 1);
  IPAddress apMask(255, 255, 255, 0);

  WiFi.softAPConfig(apIP, apGW, apMask);
  WiFi.softAP(AP_SSID);

  Serial.print("AP IP: ");
  Serial.println(WiFi.softAPIP());

  dns.start(53, "*", WiFi.softAPIP());
  dns.setErrorReplyCode(DNSReplyCode::NoError);
  dnsHijackOn = true;

  apIpU32 = ipaddr_addr(WiFi.softAPIP().toString().c_str());

  setNAT(false);
  Serial.println("✅ NAT OFF (portal mode)");
}

// ================= SETUP =================
void setup()
{
  Serial.begin(115200);
  Serial.println(String("VOUCHER_SERVER=") + VOUCHER_SERVER);

  pinMode(BUZZER_PIN, OUTPUT);
  digitalWrite(BUZZER_PIN, LOW);

  pinMode(GREEN_LED_PIN, OUTPUT);
  pinMode(RED_LED_PIN, OUTPUT);

  pinMode(BUTTON_PIN, INPUT_PULLUP);
  pinMode(IR1_PIN, INPUT_PULLUP);
  pinMode(IR2_PIN, INPUT_PULLUP);

  servo.attach(SERVO_PIN, 500, 2500);
  servo.write(SERVO_CLOSED);

  scale.begin(DOUT, CLK);
  scale.set_scale(calibration_factor);

  delay(1500);
  scale.tare();
  Serial.println("✅ TARE DONE");

  for (int i = 0; i < MAX_USERS; i++)
  {
    users[i].inUse = false;
  }

  setupWiFi();
  setupWeb();

  bool swOn = (digitalRead(BUTTON_PIN) == LOW);
  applyPowerState(swOn);

  Serial.println("✅ Open website: http://192.168.50.1");
  Serial.println("✅ Configure VOUCHER_SERVER to your voucher manager IP");
}

// ================= LOOP =================
void loop()
{
  if (dnsHijackOn)
  {
    dns.processNextRequest();
  }

  server.handleClient();

  // ✅ SESSION EXPIRY - IMMEDIATE REDIRECT
  if (!dnsHijackOn)
  {
    for (int i = 0; i < MAX_USERS; i++)
    {
      if (users[i].inUse &&
          users[i].sessionEndMs > 0 &&
          users[i].sessionEndMs <= millis() &&
          !users[i].expired)
      {
        // ✅ MARK AS EXPIRED
        users[i].expired = true;
        users[i].sessionStartMs = 0;
        users[i].sessionEndMs = 0;

        // ✅ DISABLE INTERNET
        setNAT(false);

        // ✅ RESTART DNS HIJACKING
        dns.start(53, "*", WiFi.softAPIP());
        dnsHijackOn = true;

        // ✅ FORCE RECONNECT
        WiFi.softAPdisconnect(false);
        delay(100);
        WiFi.softAP(AP_SSID);

        Serial.println("⏱️ Session expired for " + users[i].id + " - Portal mode restored");
        break;
      }
    }
  }

  bool swOn = (digitalRead(BUTTON_PIN) == LOW);
  if (swOn != powerOn)
  {
    applyPowerState(swOn);
  }

  if (!powerOn)
  {
    return;
  }

  bool ir1 = (digitalRead(IR1_PIN) == LOW);
  bool ir2 = (digitalRead(IR2_PIN) == LOW);

  // Faster response: fewer averaged reads (tune to 2 if noisy)
  float grams = scale.get_units(1);
  if (grams < ZERO_CLAMP_G)
    grams = 0;

  bool overweight = (grams > OVERWEIGHT_G);

  if (overweight)
  {
    digitalWrite(BUZZER_PIN, HIGH);
    servo.write(SERVO_CLOSED);
    detectWindowActive = false;
    stableHits = 0;
    stableStartMs = 0;
    stableRefGrams = 0;
    bottleAccepted = false;
    irTriggered = false; // ✅ RESET IR trigger
  }
  else
  {
    digitalWrite(BUZZER_PIN, LOW);
  }

  if (!overweight)
  {
    bool weightRise = (grams - lastGrams) >= WEIGHT_RISE_G;

    // ✅ Check if IR sensor is triggered (one-time check)
    if ((ir1 || ir2) && !irTriggered && !detectWindowActive && !bottleAccepted)
    {
      irTriggered = true; // Mark as triggered
      Serial.println("🔴 IR SENSOR TRIGGERED - Bottle detection enabled");
    }

    // ✅ Start detection window if IR was triggered AND weight rises
    if (irTriggered && weightRise && !detectWindowActive && !bottleAccepted)
    {
      detectWindowActive = true;
      detectWindowStartMs = millis();
      stableHits = 0;
      stableStartMs = 0;
      stableRefGrams = 0;
      Serial.println("👀 IR CONFIRMED + WEIGHT RISE → START WINDOW");
    }
  }

  if (detectWindowActive && !bottleAccepted && !overweight)
  {
    if (millis() - detectWindowStartMs > DETECT_WINDOW_MS)
    {
      Serial.println("⌛ WINDOW EXPIRED");
      detectWindowActive = false;
      stableHits = 0;
      stableStartMs = 0;
      stableRefGrams = 0;
      irTriggered = false; // ✅ RESET IR trigger - ready for next attempt
    }
    else
    {
      if (grams >= MIN_G && grams <= MAX_G)
      {
        // Require the weight to stay within +/- STABLE_DELTA_G for STABLE_TIME_MS before counting as a hit.
        if (stableStartMs == 0)
        {
          stableStartMs = millis();
          stableRefGrams = grams;
        }
        else if (fabs(grams - stableRefGrams) > STABLE_DELTA_G)
        {
          // Weight moved too much: restart stability window and discard partial hits.
          stableStartMs = millis();
          stableRefGrams = grams;
          stableHits = 0;
        }
        else if (millis() - stableStartMs >= STABLE_TIME_MS)
        {
          stableHits++;
          stableStartMs = 0;
          Serial.print("✔ WEIGHT STABLE ");
          Serial.println(stableHits);

          if (stableHits >= REQUIRED_HITS &&
              millis() - lastAcceptMs > MIN_GAP_MS)
          {
            lastAcceptMs = millis();

            Serial.println("✅ BOTTLE ACCEPTED");
            creditBottleToActiveUser();

            digitalWrite(BUZZER_PIN, HIGH);
            smartDelay(120);
            digitalWrite(BUZZER_PIN, LOW);

            servoOpenCloseWithRetry();

            // ✅ Immediately tare after close so next bottle can be dropped right away
            scale.tare();
            lastGrams = 0;
            emptyStableStart = 0;
            justTared = true;
            needRetare = false;

            bottleAccepted = false;
            irTriggered = false;
            acceptClearStartMs = 0;
            detectWindowActive = false;
            stableHits = 0;
            stableStartMs = 0;
            stableRefGrams = 0;
            Serial.println("⚖️ TARE DONE (POST-CLOSE)");
          }
        }
      }
      else
      {
        stableHits = 0;
        stableStartMs = 0;
      }
    }
  }

  if (needRetare && !ir1 && !ir2)
  {
    Serial.println("⚖️ RETARE AFTER SERVO CLOSE");
    scale.tare();
    needRetare = false;
    justTared = true;
    lastGrams = 0;
    emptyStableStart = 0;
  }

  if (!detectWindowActive && !bottleAccepted && grams <= ZERO_CLAMP_G)
  {
    if (emptyStableStart == 0)
      emptyStableStart = millis();

    if (!justTared && millis() - emptyStableStart >= AUTO_TARE_MS)
    {
      Serial.println("⚖️ AUTO TARE");
      scale.tare();
      justTared = true;
      emptyStableStart = 0;
    }
  }
  else
  {
    emptyStableStart = 0;
    justTared = false;
  }

  if (bottleAccepted && !ir1 && !ir2)
  {
    bottleAccepted = false;
    irTriggered = false; // ✅ RESET IR trigger for next bottle
    Serial.println("🔄 READY FOR NEXT BOTTLE");
  }

  lastGrams = grams;
}
