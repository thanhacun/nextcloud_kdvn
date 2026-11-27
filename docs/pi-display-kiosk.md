# Room display on a Raspberry Pi 5 (kiosk)

Decision 2026-11-27: monitor MSI Pro MP165 E6 (16 inch, 16:9, 1920x1080) plus a Raspberry Pi 5 running Chromium in
kiosk mode, showing the kdvn_visitors room display with `layout=condensed`. Never put the real display link in chat or git.

## Layout check (kdvn_visitors, 2026-11-27)
- 3 rooms per monitor work: landscape gives 3 columns of about 640 px, room names fit on one line. Screenshot from the Pi
  (via WayVNC) with the mock sensor strip fitted in the header.
- Base text is about 22 px, 4 mm on a 16-inch panel: readable at the door (1 to 1.5 m), not from a corridor. Do not add a
  larger font: it cuts the characters per line (27 px gives about 37).
- Avoid `timeline` (about 90 px per 30-minute meeting) and portrait mounting (2 columns, 3 rooms wrap).
- Still to check with real data: a busy room (long titles, 3 or more meetings, page turn every 8 s, later-day lines), and the
  sensor strip once the values are real. `&sensors=1` stays off staff screens until then (values are mock).
- The Dell P2418D (24 inch) used as test screen makes the text look about 1.5 times larger than the MSI will.

## Pi setup (Raspberry Pi OS 64-bit with desktop, Wayland, labwc)
1. Imager: hostname, user, SSH, timezone Asia/Ho_Chi_Minh, wired Ethernet if possible.
2. `sudo raspi-config`: System Options > Boot / Auto Login > Desktop Autologin; Display Options > Screen Blanking off.
3. Link in a private file: `nano ~/display.url` (one line), `chmod 600 ~/display.url`.
4. `~/kiosk.sh` (chmod +x): loop `chromium --kiosk --incognito --noerrdialogs --disable-infobars --no-first-run
   --disable-session-crashed-bubble --password-store=basic "$(cat ~/display.url)"` then `sleep 5`.
5. `~/.config/labwc/autostart`: first the resolution line, then `~/kiosk.sh &`.
6. Resolution: `wlr-randr` must show the real monitor (HDMI-A-1 or HDMI-A-2) at 1920x1080 60 Hz, scale 1.0. If it shows
   `NOOP-1 "Headless output"` no monitor was seen: power the monitor first, check the micro-HDMI port and cable, reboot.
   Autostart line that works on any port:
   `wlr-randr --output "$(wlr-randr | awk '/^[^ ]/{print $1; exit}')" --mode 1920x1080@60 --scale 1`
7. Nightly reboot: `sudo crontab -e`, `30 2 * * * /sbin/reboot` (the Pi clock is local time, unlike the server).
8. Power the Pi from its own 27 W USB-C supply, not from the monitor.

Open: which HDMI port the MSI uses (not tested yet), a screenshot of a busy room, a mounting plan.
