# SitePin – Nextcloud app inspired by SpiderPlus (design notes)

Status: design only, no code yet. Target: Nextcloud 32, Postgres 17, custom_apps/, PWA frontend (Vue 3 + PDF.js).
Scope: BOTH electrical installation inspection AND defect/punch lists, using one shared pin model.

## Core idea
One pin model on PDF drawings. Each pin has a `kind`:
- `defect` – punch-list item: photos, description, trade, severity, assignee, due date, status open → fixed → verified.
- `inspection` – checklist instance from a template: measured values, pass/fail per row, photos, inspector, date.

## Data model (oc_ prefix added by NC)
- sitepin_projects (id, name, code, group_id, folder_file_id, created_by)
- sitepin_drawings (id, project_id, file_id, floor, title, revision, page)
- sitepin_pins (id, drawing_id, kind, x, y [0–1 relative], status, title, assignee, due_date, severity, trade, template_id, created_by, timestamps)
- sitepin_photos (id, pin_id, file_id, caption, blackboard_json)
- sitepin_comments (id, pin_id, user_id, message, created_at)
- sitepin_templates (id, name, kind, fields_json, version)
- sitepin_results (id, pin_id, template_id, values_json, result pass/fail, inspected_by, inspected_at)
Files (drawings, photos, reports) live in a normal Nextcloud folder per project.

## Electrical checklist templates (first set)
1. Insulation resistance test – circuit, test voltage (250/500/1000 V), L-N / L-E / N-E MΩ, min required, pass/fail.
2. Earth (grounding) resistance – electrode/point, method, measured Ω, limit Ω, pass/fail.
3. Continuity / polarity – circuit, protective conductor continuity Ω, polarity OK.
4. Distribution panel visual check – labels, torque marks, breaker ratings vs drawing, cable sizes, IP/cover, cleanliness.
5. RCD/ELCB trip test – rated mA, trip time ms, pass/fail.
6. Cable tray / conduit installation – supports spacing, fill, bonding, fire-stopping.
Templates are JSON (field type: number/text/select/bool/photo, unit, min/max for auto pass/fail).

## Defect list fields
trade (electrical, ELV, mechanical, plumbing, fire, civil), severity (minor/major/critical), location (auto from drawing+floor), before/after photos.

## Reports
- Defect list: Excel (PhpSpreadsheet) – one row per pin, drawing snapshot with pin numbers, before/after photos.
- Inspection report: Excel per template type (test sheet format), plus PDF via Stirling PDF / OnlyOffice conversion.

## Phases
1. MVP (online): projects, drawing upload, pins (both kinds), photos, comments, status, defect Excel export.
2. Templates engine + electrical test sheets, photo markup, blackboard overlay, PDF report, NC notifications.
3. Offline PWA (IndexedDB + sync queue), drawing revisions with pin carry-over, dashboards.

## Deployment notes
- info.xml: min-version 32, max-version 32 (avoid the nocollabora_filter max-version issue).
- Enable: docker exec -u www-data nextcloud-app php occ app:enable sitepin
- No compose/HAProxy change needed.
