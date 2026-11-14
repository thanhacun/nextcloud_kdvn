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

## Not done yet
- Supplier provider, weather fallback (outdoor, must be labelled), embedding the strip in the
  kdvn_visitors room display. Not tested on a live server.
