# Configurable Default Report Period Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a single admin-configurable setting (`reports.default_period_days`, default `1`) that controls the default date range every report opens with, where `1` = today and `N` = today plus the previous N-1 days.

**Architecture:** A seeded `cfg_setting` row holds the value; existing settings routes read/write it. The Setup admin form gains a numeric field bound to the handle. On app startup `admin.js` fetches the value, computes a global `webix.reportDefaultStart`, and gates rendering on the fetch. The CDR report and the legacy report date-range filter read that global instead of their previous hardcoded defaults.

**Tech Stack:** PHP (Slim 3), Phinx/lulco-phoenix migrations, Webix + webix-jet frontend bundled with webpack 5.

## Global Constraints

- Setting handle (verbatim): `reports.default_period_days`.
- Default value: `1` (string `'1'` in the DB; 1 = today only).
- Semantics: start = today 00:00:00 minus (N-1) days; end = today 23:59:59; clamp `N >= 1`.
- Migrations are append-only and must use the `YYYYMMDDHHMMSS_name.php` Phinx convention.
- No JS unit-test harness exists in this repo; frontend verification is `cd public && npm run lint` + `npm run build`, plus the stated behavioral check.
- Slim 3 / top-level `PBXSettings` conventions; no backend logic changes required beyond the seed migration.

---

### Task 1: Seed migration for the setting

**Files:**
- Create: `migrations/20260710000600_add_setting_reports_default_period.php`

**Interfaces:**
- Consumes: nothing.
- Produces: a `cfg_setting` row with `handle = 'reports.default_period_days'`, `val = '1'`. The Setup form (Task 2) and the startup fetch (Task 3) rely on this handle string existing.

- [ ] **Step 1: Create the migration file**

Mirror the existing `migrations/20230331124905_add_setting_asterisk_http_port.php`. Contents:

```php
<?php

use Phoenix\Migration\AbstractMigration;

class AddSettingReportsDefaultPeriod extends AbstractMigration
{
    protected function up(): void
    {
        $this->execute("INSERT INTO cfg_setting SET updated = NOW(), handle = 'reports.default_period_days', val = '1';");
    }

    protected function down(): void
    {
        $this->execute("DELETE FROM cfg_setting WHERE handle = 'reports.default_period_days';");
    }
}
```

- [ ] **Step 2: Apply the migration**

Run: `php vendor/bin/phoenix m`
Expected: output lists `AddSettingReportsDefaultPeriod` as migrated, no errors.

- [ ] **Step 3: Verify the row exists**

Run: `php vendor/bin/phoenix status`
Expected: the migration shows as `UP` / executed.
(If DB access is available, confirm `SELECT handle, val FROM cfg_setting WHERE handle='reports.default_period_days';` returns `val = 1`.)

- [ ] **Step 4: Verify rollback works, then re-apply**

Run: `php vendor/bin/phoenix rollback` then `php vendor/bin/phoenix m`
Expected: rollback deletes the row (no error), re-apply re-inserts it.

- [ ] **Step 5: Commit**

```bash
git add migrations/20260710000600_add_setting_reports_default_period.php docs/superpowers/specs/2026-07-10-reports-default-period-design.md docs/superpowers/plans/2026-07-10-reports-default-period.md
git commit -m "feat(settings): seed reports.default_period_days setting (default 1)"
```

---

### Task 2: Setup UI field

**Files:**
- Modify: `public/sources/views/views/setup.js` (the "Основные" tab, form id `primarySetup`, around lines 210-217 where the `acdclient.homepages` field sits)

**Interfaces:**
- Consumes: the `reports.default_period_days` handle (Task 1). The field's `id`/`name` must equal the handle so the existing `loadData` (`GET /settings/default` -> `$$(e.handle).setValue(e.val)`) populates it and the existing tab-save handler (`$$(activeTab).getValues()` -> `POST /settings/default/save`) persists it.
- Produces: nothing consumed by other tasks.

- [ ] **Step 1: Add the counter field**

In `public/sources/views/views/setup.js`, add a new element inside the `primarySetup` form rows (place it as a sibling row next to the existing `acdclient.homepages` text field, e.g. immediately after that field's object). Insert:

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
},
```

Ensure it is inside the `primarySetup` form so the tab-save handler includes it, and that surrounding array commas remain valid.

- [ ] **Step 2: Lint**

Run: `cd public && npm run lint`
Expected: no new errors for `setup.js`.

- [ ] **Step 3: Build**

Run: `cd public && npm run build`
Expected: build succeeds, `codebase/admin.js` regenerated.

- [ ] **Step 4: Behavioral check**

Load the admin app, open Setup -> "Основные" tab. Expected: a "Период отчётов по умолчанию (дней)" counter shows `1` (loaded from the seeded setting). Change it to `7`, click "Сохранить", reload the page, reopen Setup. Expected: the field shows `7` (persisted via `/settings/default/save`). Set it back to `1` afterward.

- [ ] **Step 5: Commit**

```bash
git add public/sources/views/views/setup.js
git commit -m "feat(setup): add default report period field"
```

---

### Task 3: Startup fetch + global in admin.js

**Files:**
- Modify: `public/sources/views/admin.js` (lines 48-89: the `startDayDateTime`/`endDayDateTime` definitions and the `auth.check(...)` block)

**Interfaces:**
- Consumes: `GET /settings/default/reports.default_period_days` -> JSON `{res: bool, value: string}` (HTTP 400 when the row is absent).
- Produces: global `webix.reportDefaultStart` — a `Date` at 00:00:00 equal to today minus (N-1) days, where N is the clamped (`>=1`) integer setting value; falls back to today 00:00:00 on any fetch/parse error. Set before `app.render()` runs. Tasks 4 and 5 read this global.

- [ ] **Step 1: Add the global default and the compute helper**

In `public/sources/views/admin.js`, immediately after the existing `webix.endDayDateTime` block (currently ending at line 56), add:

```js
  // Default report period start; overwritten by the fetched setting below.
  // Falls back to today (period = 1 day) on any error.
  webix.reportDefaultStart = new Date(webix.startDayDateTime);

  const applyReportPeriod = (days) => {
    const n = Math.max(1, parseInt(days, 10) || 1);
    const s = new Date();
    s.setHours(0, 0, 0, 0);
    s.setDate(s.getDate() - (n - 1));
    webix.reportDefaultStart = s;
  };
```

- [ ] **Step 2: Wrap the existing auth.check block and gate it on the fetch**

The existing block (currently lines 58-87) is:

```js
	auth.check(
		() => {
		  app.render().then(() => {
        // ... existing body unchanged ...
			});
			
			if ($$('person_template')) $$('person_template').refresh();
		});
```

Wrap it in a named function and call it only after the settings fetch resolves. Replace the leading `auth.check(` with a function definition, and add the fetch after it. Concretely, change the start of that block to:

```js
  const startApp = () => auth.check(
    () => {
      app.render().then(() => {
```

(keep the entire existing body of the `app.render().then(...)` callback and the trailing `if ($$('person_template')) $$('person_template').refresh();` exactly as-is, down to the closing `});` of `auth.check`).

Then, immediately after that closing `});`, add:

```js
  webix.ajax().get("/settings/default/reports.default_period_days")
    .then((data) => { applyReportPeriod(data.json().value); })
    .catch(() => { /* keep today (period = 1) fallback */ })
    .finally(() => { startApp(); });
```

- [ ] **Step 3: Lint**

Run: `cd public && npm run lint`
Expected: no new errors for `admin.js`.

- [ ] **Step 4: Build**

Run: `cd public && npm run build`
Expected: build succeeds.

- [ ] **Step 5: Behavioral check**

Load the app. In the browser console, evaluate `webix.reportDefaultStart` after the app has rendered. Expected with setting `1`: it equals today at 00:00:00. Temporarily set the Setup value to `7`, reload, re-check. Expected: `webix.reportDefaultStart` is 6 days before today at 00:00:00. Set back to `1`.

- [ ] **Step 6: Commit**

```bash
git add public/sources/views/admin.js
git commit -m "feat(reports): fetch default period setting and expose reportDefaultStart"
```

---

### Task 4: CDR report uses the configured default

**Files:**
- Modify: `public/sources/views/views/cdr_reports.js` (line ~49 `inputConfig`; lines ~312-315 `onAfterRender`; lines ~397-405 `loadData`)

**Interfaces:**
- Consumes: `webix.reportDefaultStart` and `webix.endDayDateTime` (globals from Task 3 / admin.js).
- Produces: nothing consumed by other tasks.

- [ ] **Step 1: Replace the filter inputConfig start**

At line ~49, change:

```js
              start: webix.startDayDateTime, finish: webix.endDayDateTime,
```

to:

```js
              start: webix.reportDefaultStart, finish: webix.endDayDateTime,
```

- [ ] **Step 2: Replace the onAfterRender default value**

At lines ~312-315, change:

```js
              $$("cdr_reports").getFilter("time").config.value = {
                start: webix.startDayDateTime,
                end: webix.endDayDateTime
              };
```

to:

```js
              $$("cdr_reports").getFilter("time").config.value = {
                start: webix.reportDefaultStart,
                end: webix.endDayDateTime
              };
```

- [ ] **Step 3: Replace the loadData default range**

At lines ~397-405, change the two references so the loaded range and the filter value both use `webix.reportDefaultStart`:

```js
        const todayFilter = encodeURIComponent(JSON.stringify({
          start: fmt(webix.reportDefaultStart),
          end: fmt(webix.endDayDateTime)
        }));
        if ($$("cdr_reports").getFilter("time")) {
          $$("cdr_reports").getFilter("time").config.value = {
            start: webix.reportDefaultStart,
            end: webix.endDayDateTime
          };
        }
```

(Only the `start:` values change from `webix.startDayDateTime` to `webix.reportDefaultStart`; leave `fmt`, the URL, and everything else unchanged.)

- [ ] **Step 4: Lint**

Run: `cd public && npm run lint`
Expected: no new errors for `cdr_reports.js`.

- [ ] **Step 5: Build**

Run: `cd public && npm run build`
Expected: build succeeds.

- [ ] **Step 6: Behavioral check**

Load the app, open the CDR report (Звонки). With setting `1`: the date filter defaults to today (00:00 -> 23:59) and rows load for today. Set the Setup value to `7`, reload, reopen the report. Expected: the date filter spans the last 7 days (today minus 6 at 00:00 -> today 23:59). Set back to `1`.

- [ ] **Step 7: Commit**

```bash
git add public/sources/views/views/cdr_reports.js
git commit -m "feat(reports): CDR report honors configured default period"
```

---

### Task 5: Legacy report date-range filter uses the configured default

**Files:**
- Modify: `public/sources/views/appfilter.js` (line ~43 `setIntervalToWeek()`; line ~147 `reportDateRange` initial value)

**Interfaces:**
- Consumes: `webix.reportDefaultStart` and `webix.endDayDateTime` (globals from Task 3 / admin.js).
- Produces: nothing consumed by other tasks.

- [ ] **Step 1: Replace the setIntervalToWeek start**

At lines ~42-45, change:

```js
		$$("reportDateRange").setValue({
			start: webix.Date.add(webix.startDayDateTime, -1, "week"),
			end: webix.endDayDateTime
		})
```

to:

```js
		$$("reportDateRange").setValue({
			start: webix.reportDefaultStart,
			end: webix.endDayDateTime
		})
```

- [ ] **Step 2: Replace the reportDateRange initial value start**

At lines ~146-149, change:

```js
													value: {
														start: webix.Date.add(webix.startDayDateTime, -1, "week"),
														end: webix.endDayDateTime
													},
```

to:

```js
													value: {
														start: webix.reportDefaultStart,
														end: webix.endDayDateTime
													},
```

- [ ] **Step 3: Lint**

Run: `cd public && npm run lint`
Expected: no new errors for `appfilter.js`.

- [ ] **Step 4: Build**

Run: `cd public && npm run build`
Expected: build succeeds.

- [ ] **Step 5: Behavioral check**

Load the app, open a legacy report that uses the "Период" (`reportDateRange`) filter. With setting `1`: the period defaults to today. Set the Setup value to `7`, reload, reopen. Expected: the period spans the last 7 days. Set back to `1`.

- [ ] **Step 6: Commit**

```bash
git add public/sources/views/appfilter.js
git commit -m "feat(reports): legacy report period filter honors configured default"
```

---

## Notes for the implementer

- Do not change `webix.startDayDateTime` / `webix.endDayDateTime` semantics — they remain today 00:00 / today 23:59 and are still used as calendar-suggest bounds and arithmetic bases elsewhere.
- `setIntervalToWeek()` keeps its name even though it no longer forces a week, to avoid touching call sites; its behavior now follows the configured period.
- The line numbers in this plan are approximate anchors — match on the surrounding code shown, not the number.
