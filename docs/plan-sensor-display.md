# Plan: indoor air sensors (temperature, humidity, CO2) on the room display

Status (2026-11-15): kdvn_sensors 0.2.0 and kdvn_visitors 0.6.2 are deployed with the mock provider and tested by Thanh
("so far so good"): several sensors per office (offices come from Calendar Resource Management), per-sensor metrics and
thresholds, stand-alone display page, header strip on the room display (`&sensors=1`). Waiting for the supplier: the real
provider is not written (see section 6). Weather fallback not built.
Mockup: the room display with a sensor strip on top. The solar block is dropped.

## 1. What the supplier diagram says

Diagram: SVC <-> BACnet Service <-> public API <-> "Khach Hang App" (our app).

- BACnet Service reads the SVC (the controller) using config file (1), the XML with the SVC
  connection details.
- It stores the values it read in file (2), the XML with the results.
- Our app calls the BACnet Service public API. The service checks the request against the
  secret key in file (3), then returns the data from file (2).
- Our app sends the secret key defined in (4) with its API call.

So we do not talk BACnet. We make one authenticated HTTP call to the BACnet Service.
It is a pull model: the data is only as fresh as the service's last read of the SVC.

Windows SQL means the service is on a Windows host. SQL is the supplier's storage; we should
not need to touch it.

## 2. Design

### Data flow (browser never sees the secret)

    Sensor/SVC -> BACnet Service -> [our app: kdvn_sensors server side] -> display page JSON

- The Nextcloud server calls the supplier API. The display page only calls our app.
- A cache (60 s) in the app protects the BACnet Service from many displays polling.
- Secret key is stored in the admin page, shown once, never in git
  (same rule as the other apps).

### Source order (per sensor value)

1. Supplier BACnet API, if configured and answering.
2. Fallback for temperature and humidity: Nextcloud weather (outdoor, so label it
   "Outdoor"). Verify which weather source is installed (the weather_status app uses
   Open-Meteo and the user's location). Unverified on NC 34.
3. Otherwise show "NA". CO2 has no weather fallback, so it is always NA without the API.

The tile must say where the value comes from ("Indoor sensor" or "Outdoor (weather)") so
nobody reads an outdoor value as the room temperature.

### Status words (from the mockup)

| Value | Comfortable / Normal / Good | Warning | Bad |
|---|---|---|---|
| Temp | 22-26 degC | 20-22, 26-28 | outside that |
| Humidity | 40-60 % | 30-40, 60-70 | outside that |
| CO2 | under 800 ppm | 800-1200 | over 1200 |

Thresholds are admin settings; these are starting defaults (typical comfort guidance,
adjust with facilities).

### Two ways to show it

- Stand-alone page: a public display page with only the three tiles (and clock). Same
  token-link pattern as the room displays.
- Embedded: the existing room display fetches the same JSON endpoint and renders the strip
  at the top (the red box in the mockup). Controlled by a setting "show sensor strip".

## 3. Where to build it

Recommended: a **new small app `kdvn_sensors`** (plain PHP + vanilla JS, as the others), not
inside kdvn_visitors. Reasons: it has its own secret and settings, and it can be deployed
and rolled back without touching the visitor flow.

- `lib/Service/SensorService.php`: call supplier API, cache, fallback, status words.
- `lib/Controller/ApiController.php`: `GET /api/readings` for the display (token protected,
  same token check as the room displays).
- `lib/Controller/PageController.php`: stand-alone display page.
- `lib/Settings/Admin.php` + template: API URL, secret key, thresholds, fallback on/off,
  location label.
- The room display (kdvn_visitors) gets a small JS block that calls the readings endpoint.
  This is the only change in an existing app.

Route parameter rule: never name a URL parameter `action`, `caller` or `_route`.

## 4. Build steps (for a later, cheap session)

1. Create kdvn_sensors with a **mock provider** that returns fixed values, so the display
   can be built and tested without the supplier.
2. Add the weather fallback and NA handling.
3. Add the supplier provider once the API details are known (URL, how the key is sent,
   point names, units, sample response). Until then the parser is a guess.
4. Embed the strip in the room display.
5. Deploy per docs/deploy-apps.md. Needs no restart, no maintenance mode, no user
   downtime: enabling an app is `occ app:enable kdvn_sensors` (approval first).

## 5. Risks

- Supplier API may be HTTP only and on a different VLAN: check the firewall and use only
  an internal route. Do not expose it to the internet.
- Stale data: show the reading age and switch to NA when older than about 10 minutes.
- Sensor placement: one sensor per floor is not per room. Label tiles with the location.
- Weather fallback is outdoor air. Keep the label.

## 6. Waiting for the supplier: what the real provider needs

Nothing else blocks it. Answers needed (send to the supplier):

1. API base URL, port, HTTP or HTTPS, and whether the Nextcloud server can reach it (VLAN, firewall).
2. How the secret key is sent with a request (header name, query parameter or signed token).
3. A sample response (JSON or XML) with temperature, humidity and CO2.
4. The point names that tell the sensors apart (office area, server room, other offices). They go into each
   sensor's "Point name at the source" field in the admin page.
5. Units (degC, % RH, ppm) and how often the service refreshes its data.
6. Whether a reading carries a timestamp or an offline flag (decides when a tile shows NA).
7. A rate limit, and whether several clients may poll.
8. Whether the Base Station or HMI Station exposes the API, or a separate service.

When the answers arrive: write `lib/Service/SupplierProvider.php` (implements `ReadingProvider::fetch($sensor)`,
maps `$sensor['ref']` to the point, returns `at` from the response timestamp), add `supplier` as a data source in
`AppSettings` and the admin page, store the API URL and key in the admin page (key encrypted, never shown again), then
test with the mock still available. Update the version, deploy with `occ upgrade` (docs/deploy-apps.md).

## 7. Supplier answers (Azbil, received 2026-11-19)

Summary of the reply to the eight questions in section 6. The example endpoint and JSON are a proposal; the
supplier says endpoint and fields can be adjusted.

1. **Where and how:** the "BACnet Service" is installed on the Base Station (a Windows PC supplied by Kinden, in
   the Kinden office, on the same network as the BACnet controllers). Windows 10/11 or Server 2016+, .NET Core;
   no SQL Server (the service uses PostgreSQL). Port is configurable. Firewall: inbound TCP on that port, limited
   to the Nextcloud server's IP. HTTP by default; HTTPS possible if Kinden supplies a certificate.
2. **Endpoint (proposed):** `GET http://<IP>:<port>/api/points/values` returns
   `{"timestamp": ..., "data": [{"point", "value", "unit", "status": online|offline, "updated"}]}`
   (units degC, %RH, ppm; ISO 8601 times with +07:00).
3. **Auth:** HTTP header `X-API-Key: <secret>`. Missing or wrong key gives 401.
4. **Sensors:** one sensor per room. The point list does not exist yet; the supplier sends it with the budget.
   Example names: `R101_Temp`, `R101_RH`, `R101_CO2`.
5. **Refresh:** the service reads the BMS at different rates per point (for example 1 s, 10 s), not all
   continuously. The API reads stored data and never queries the BMS directly.
6. **NA rule:** show NA when `status` is offline, or when `updated` is older than N minutes (supplier suggests 5,
   adjustable).
7. **Limits:** no rate limit, several clients allowed. One poll per minute from one server is fine.
8. **Which machine:** the Base Station (Kinden PC) runs the BACnet Service that exposes the API.
9. **Alerts:** the system alerts on the Base Station only. SMS or email alerts would need extra Azbil software;
   Azbil recommends Kinden IT handles that (for example from Nextcloud).

### What this means for the build
- Still open: real IP and port, HTTP or HTTPS, the point list (comes with the budget), the final JSON.
- Provider design: one `GET` of all points per refresh (not per sensor), cache about 60 s, match `sensor.ref` to
  the point name, NA on offline or stale `updated`. The API key is stored encrypted in the admin page.
- Network: the Nextcloud container must reach the Base Station's IP and port (check routing and firewall).
- Because one room has one sensor, the admin list maps a sensor to point names per metric (Temp, RH, CO2).
  Today one "Point name" is used; it may need a prefix (`R101_`) plus suffixes, or three fields.

### Update 2026-11-19: diagram E-14 (BMS single line diagram) and what was built
- The fan is controlled by the DDC (Azbil controller) from the CO2 sensor: off below 400 ppm, running above 600 ppm.
  Nextcloud must not control it (it restarts, polls once a minute and the API is read-only). Nextcloud only shows the
  CO2 value and, if Azbil exposes it, a read-only fan running mark. Alerts (SMS, Talk, email) are for Kinden IT.
- Display bands in the diagram: below 400 very good, 400 to 600 good, 600 to 1000 poor. Default CO2 thresholds are now
  600 (good up to) and 1000 (warning up to); editable in the admin page.
- Built in kdvn_sensors 0.3.0 (code only, not deployed, not tested against the real service): supplier provider, admin
  fields (URL, encrypted key, NA age), three point names per sensor plus an optional fan point, fan mark on the display.
- Questions still to send to Azbil (written answers, no public documentation of this API was found):
  1. The API contract in writing: endpoint, exact JSON schema, error responses, how point names are built.
  2. Will the fan running signal be a point (for example `R101_Fan`), and what values does it return (0/1 or words)?
  3. CO2 history for the graph the diagram asks for, or should Nextcloud store its own series?
  4. Exact fan on and off thresholds and hysteresis (the diagram leaves 400 to 600 undefined; 400 ppm is about
     outdoor air, so the fan may rarely stop).
  5. Is the e-multimeter (power, current, voltage) in the API, and is it in scope?
  6. Fixed IP and port of the Base Station, HTTP or HTTPS, and the firewall rule for the Nextcloud server's IP.
