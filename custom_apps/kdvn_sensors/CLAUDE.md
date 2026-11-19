# kdvn_sensors

Indoor temperature, humidity and CO2 on a public display page. Design: docs/plan-sensor-display.md.

## Rules
- Public page /apps/kdvn_sensors/display/{key} needs no login; the key is a secret stored hashed
  (appconfig display_key_hash). Never log or print it.
- The browser never sees a supplier secret: the server calls the provider, the page only calls
  /display/{key}/readings (60 s cache, rate limited, brute-force protected).
- Providers implement Service/ReadingProvider: `off`, `mock` (fixed values, testing only) and `supplier`
  (Service/SupplierProvider, written 0.3.0 against the SAMPLE JSON Azbil sent on 2026-11-19, see
  docs/plan-sensor-display.md section 7; not tested against the real service). One GET returns all points;
  all parsing is in `SupplierProvider::parse()` (pure, unit-testable) so a changed JSON is a change in one place.
  The API key is an appconfig sensitive value (encrypted), sent in the X-API-Key header, never shown or logged.
  A failed call pauses further calls for 30 s so the display does not wait on a timeout every refresh.
  Nextcloud refuses internal IPs unless `allow_local_remote_servers` is true in config.php.
- A tile shows NA when the value is missing, or older than 10 minutes (AppSettings::STALE_SECONDS). For the
  supplier a value is also NA when its status is not "online" or `updated` is older than the admin's limit
  (1 to 10 minutes, default 5).
- The exhaust fan is controlled by the BMS (DDC), never by Nextcloud. The optional "fan" point only shows a
  read-only "Exhaust fan running" mark.
- Never name a route URL parameter `action`, `caller` or `_route`.

## Sensors and offices
- Sensors are a list (appconfig `sensors`, JSON): id (generated, stable), name, buildingId (a Calendar
  Resource Management building, read-only through Service/BuildingCatalog; null = all offices), metrics
  (temperature, humidity, co2), points (point name per metric at the supplier: temperature, humidity, co2, optional fan), roomDisplay (show in the room display
  strip), optional own thresholds (default thresholds are global). With no list saved, one implicit
  sensor "Office" keeps the page working. Sanitizing: AppSettings::cleanSensors().
- `ReadingProvider::fetch($sensor)` is per sensor; readings are cached per sensor id for 60 s.
- Display page: `?building=ID` shows only that office's sensors. kdvn_visitors asks
  `SensorService::readings($buildingId, true)` (roomDisplay sensors of the screen's office).

## Not done yet
- CO2 history graph (Azbil's diagram asks for one; the API returns current values only).
- Fan mark is not in the kdvn_visitors header strip yet.
- Weather fallback (outdoor, must be labelled), embedding the strip in the
  kdvn_visitors room display. Not tested on a live server.
