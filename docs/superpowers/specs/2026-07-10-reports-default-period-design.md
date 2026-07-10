# Configurable default report period

## Goal

Introduce a single configurable value that controls the default date range shown
when any report opens. The value is expressed in **days**, where `1` means "today"
and is the default. `N` means "today plus the previous N-1 days" (N days inclusive
of today).

## Setting

- New `cfg_setting` row, handle `reports.default_period_days`, integer value.
- Default value: `1`.
- Semantics: start = today 00:00:00 minus (N-1) days; end = today 23:59:59.
  - `1` -> today only.
  - `7` -> today plus the previous 6 days.
- Value is clamped to `N >= 1`.

## Backend

No PHP logic changes are required. The existing settings routes already cover the
needs:

- `GET /settings/default/reports.default_period_days` -> `{res: bool, value: string}`
  (returns HTTP 400 with `res=false` when the row is absent). Used by the app to
  read the single value at startup.
- `GET /settings/default` -> list of all settings. Used by the Setup form to
  populate fields.
- `POST /settings/default/save` -> upserts settings. Used by the Setup form save
  button. `PBXSettings::setDefaultSettings` inserts the row if the handle does not
  yet exist, so no special handling is needed.

### Migration

New Phinx migration `YYYYMMDDHHMMSS_add_setting_reports_default_period.php`, mirroring
`20230331124905_add_setting_asterisk_http_port.php`:

- `up()`: `INSERT INTO cfg_setting SET updated = NOW(), handle = 'reports.default_period_days', val = '1';`
- `down()`: `DELETE FROM cfg_setting WHERE handle = 'reports.default_period_days';`

Seeding guarantees the value is present so the startup fetch succeeds immediately
and the row appears in the Setup list.

## Setup UI (`public/sources/views/views/setup.js`)

Add a numeric field to the "Основные" tab (form id `primarySetup`), where each
control's `id` equals the setting handle:

```js
{
  view: "counter",
  id: "reports.default_period_days",
  name: "reports.default_period_days",
  label: "Период отчётов по умолчанию (дней)",
  inputWidth: 300,
  value: 1,
  min: 1,
  step: 1,
}
```

The existing `loadData` (`webix.ajax().get("/settings/default", ...)`) sets the
field value from the stored setting, and the existing tab save handler
(`$$(activeTab).getValues()` -> `POST /settings/default/save`) persists it. No
changes to load/save handlers are needed.

## Frontend application (all report date pickers)

### `public/sources/views/admin.js`

- Keep `webix.startDayDateTime` (today 00:00:00) and `webix.endDayDateTime`
  (today 23:59:59) unchanged. They remain the semantic "today" boundaries used as
  arithmetic bases and as calendar-suggest bounds.
- Add a new global `webix.reportDefaultStart`, initialized to a copy of today
  00:00:00 (the N=1 fallback).
- Before `app.render()`, fetch the setting and compute the default start:

  ```js
  const applyReportPeriod = (days) => {
    const n = Math.max(1, parseInt(days, 10) || 1);
    const s = new Date();
    s.setHours(0, 0, 0, 0);
    s.setDate(s.getDate() - (n - 1));
    webix.reportDefaultStart = s;
  };
  ```

- Gate `auth.check(...)` / `app.render()` on the fetch so views read the final
  `reportDefaultStart` at build time:

  ```js
  webix.ajax().get("/settings/default/reports.default_period_days")
    .then((data) => { applyReportPeriod(data.json().value); })
    .catch(() => { /* keep N=1 fallback */ })
    .finally(() => { startApp(); });
  ```

  where `startApp()` wraps the existing `auth.check(...)` block. On error or HTTP
  400 (row missing) the N=1 fallback is used.

### `public/sources/views/views/cdr_reports.js`

Replace the hardcoded "today start" with `webix.reportDefaultStart` in the three
default-range spots; end stays `webix.endDayDateTime`:

- Filter `inputConfig.start` (~line 49).
- `onAfterRender` filter value (~lines 312-315).
- `loadData` `todayFilter` start (~lines 397-405).

### `public/sources/views/appfilter.js`

Replace the hardcoded `webix.Date.add(webix.startDayDateTime, -1, "week")` with
`webix.reportDefaultStart`; end stays `webix.endDayDateTime`:

- `reportDateRange` initial `value.start` (~line 147).
- `setIntervalToWeek()` (~line 43). (Method name is now a slight misnomer but is
  kept to avoid touching call sites; it now applies the configured period.)

## Behavior summary

- Default (`1`): every report opens on "today". CDR report unchanged; legacy
  reports change from the previous "last week" default to "today", consistent with
  a single shared value across all report date pickers.
- `7`: reports open on the last 7 days (today + previous 6). Any `N >= 1` works.

## Testing / verification

- Migration: verify `up`/`down` SQL applies and rolls back (`php vendor/bin/phoenix m`).
- Frontend: build (`cd public && npm run build`) and load a report with the setting
  at `1` (range = today) and at `7` (range = 7-day span ending today); confirm the
  Setup field loads/saves the value.

## Out of scope

- Per-user / per-group overrides of the period (settings support them, but not
  requested here).
- Changing report backends or query logic; only the default UI range changes.
