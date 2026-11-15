# kdvn_sensors

Indoor temperature, humidity and CO2 on a public display page. Design: docs/plan-sensor-display.md.

## Rules
- Public page /apps/kdvn_sensors/display/{key} needs no login; the key is a secret stored hashed
  (appconfig display_key_hash). Never log or print it.
- The browser never sees a supplier secret: the server calls the provider, the page only calls
  /display/{key}/readings (60 s cache, rate limited, brute-force protected).
- Providers implement Service/ReadingProvider. Today: `mock` (fixed values, testing only) and `off`.
  The supplier BACnet provider is not written: its API details are not known yet.
- A tile shows NA when the value is missing or older than 10 minutes (AppSettings::STALE_SECONDS).
- Never name a route URL parameter `action`, `caller` or `_route`.

## Sensors and offices
- Sensors are a list (appconfig `sensors`, JSON): id (generated, stable), name, buildingId (a Calendar
  Resource Management building, read-only through Service/BuildingCatalog; null = all offices), metrics
  (temperature, humidity, co2), ref (point name at the data source), roomDisplay (show in the room display
  strip), optional own thresholds (default thresholds are global). With no list saved, one implicit
  sensor "Office" keeps the page working. Sanitizing: AppSettings::cleanSensors().
- `ReadingProvider::fetch($sensor)` is per sensor; readings are cached per sensor id for 60 s.
- Display page: `?building=ID` shows only that office's sensors. kdvn_visitors asks
  `SensorService::readings($buildingId, true)` (roomDisplay sensors of the screen's office).

## Not done yet
- Supplier provider, weather fallback (outdoor, must be labelled), embedding the strip in the
  kdvn_visitors room display. Not tested on a live server.
