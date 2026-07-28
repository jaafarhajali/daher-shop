# Daher Phone — The Update Process, Explained

Everything about how updates work: how versions are tracked, how the developer
publishes an update, what happens inside the shop's machine when it installs,
and why customer data can never be lost by an update.

Related files:
[app/Core/Updater.php](../app/Core/Updater.php) (the engine) ·
[app/Core/Migrator.php](../app/Core/Migrator.php) (database changes) ·
[app/Controllers/UpdateController.php](../app/Controllers/UpdateController.php) (the Updates page) ·
[deploy/build.ps1](../deploy/build.ps1) (package builder)

---

## 1. The big picture

```
DEVELOPER MACHINE                          SHOP MACHINE (Daher Phone package)
─────────────────                          ───────────────────────────────────
1. change code                             Updates page (admin only)
2. add migration .sql files                     │
3. bump VERSION  (1.3.0 → 1.4.0)                │  "Check for updates"
4. build.ps1 -UpdateZip                         ▼
      │                                    reads update.json ──► compares versions
      ├── DaherPhone-update-1.4.0.zip           │
      └── update.json (version+url+sha256)      │  "New update available: 1.4.0"
5. host both files anywhere ────────────►      │  [ Update Now ]
   (GitHub Releases, any web host)              ▼
                                           the 6-step apply pipeline (section 5)
```

There is **no silent auto-update**. The app checks and offers; a person clicks.
For software holding a shop's sales and debts, the owner stays in control.

---

## 2. Versioning — one source of truth

| What | Where | Who writes it |
|---|---|---|
| Installed version | `VERSION` file in the application folder | the updater, when an update succeeds |
| Available version | `version` field in the hosted `update.json` | the developer, at release time |

The app reads `VERSION` at startup (`config/config.php` → `APP_VERSION`) and
shows it in the sidebar footer and on the Updates page. Comparison uses PHP's
`version_compare()`, so `1.10.0` correctly beats `1.9.0`.

**Rule: an update is offered only when the remote version is strictly newer.**
Same or older version → "You already have the latest version."

---

## 3. Database changes — the migration system

The database is the customer's property; updates must never rebuild it.
Schema changes travel as small, numbered SQL files:

```
database/migrations/
├── 001_credit_returns_refunds.sql
├── 002_branding_daher_phone.sql
└── 003_whatever_comes_next.sql      ← you add these, never edit old ones
```

How the run-once guarantee works:

- A `migrations` table in the customer database records every applied filename.
- `Migrator::run()` executes only files **not** in that table, in name order,
  and records each one only after *all* of its statements succeeded.
- Fresh installs import the full `schema.sql` (which already contains
  everything) and then mark every migration as applied.
- Old databases from before the migration system are **adopted**: if the
  tracking table is empty but migration 001's changes are visibly present,
  001/002 are marked applied instead of being re-run against live data.

So a shop can skip versions: updating 1.3.0 → 1.6.0 simply runs migrations
003, 004, 005 in order — exactly the missing ones, exactly once.

**Writing a good migration:** additive changes only (`ADD COLUMN`,
`CREATE TABLE`, conditional `UPDATE`). Never `DROP TABLE`, never re-import
schema.sql, never assume the shop skipped nothing.

---

## 4. Publishing an update (developer workflow)

```powershell
# 1. finish the code changes; add database/migrations/00X_*.sql if needed
# 2. bump the version
echo 1.4.0 > VERSION
git add -A ; git commit -m "v1.4.0 - <what changed>"

# 3. build the update package
powershell -ExecutionPolicy Bypass -File deploy\build.ps1 -UpdateZip
```

This produces, in `deploy\build\`:

| File | What it is |
|---|---|
| `DaherPhone-update-1.4.0.zip` | the whole repository **minus** `.git`, `deploy/`, `storage/`, `config/app.ini` |
| `update.json` | the feed: `{ "version", "url", "sha256", "notes" }` — sha256 already computed |

Because of those exclusions, a package *physically cannot* contain anything
that would overwrite the shop's settings (`app.ini`), logs, or data.

**4. Host both files** anywhere reachable by the shop — GitHub Releases, any
web hosting, even a folder on a local server. Edit `update.json` and replace
`REPLACE-WITH-PUBLIC-URL/...` with the zip's real public URL. Write a short,
owner-readable sentence into `"notes"` — it is shown in the shop.

**5. One-time setup per shop:** paste the `update.json` URL into
*Updates → Update server address*. It is stored in the shop's settings.

### Example update.json

```json
{
  "version": "1.4.0",
  "url": "https://github.com/you/daher-phone/releases/download/v1.4.0/DaherPhone-update-1.4.0.zip",
  "sha256": "9f2c1a…e7",
  "notes": "Adds supplier tracking. Fixes receipt printing on 58mm printers."
}
```

---

## 5. Installing an update (what "Update Now" really does)

The pipeline in `app/Core/Updater.php`, in order. Each step exists because of
what could go wrong after it.

```
1. DOWNLOAD  + verify SHA-256 ──── corrupted/tampered file? → stop, nothing touched
2. BACKUP    database → Backups\backup_YYYYMMDD_HHMMSS.sql
3. SNAPSHOT  current code → Updates\rollback_<ver>_<time>\
4. STAGE     unzip to Updates\staging\ and validate it there
             (no VERSION file at the root? → "not a Daher Phone update package")
5. COPY      staging over Application\   [always skipping config\app.ini + storage\]
6. MIGRATE   run pending database migrations, record each one
             └─ SUCCESS → new VERSION is live → "Updated to version 1.4.0"
```

**If anything fails during 5 or 6 → automatic rollback:**

- The code folders are cleared and the snapshot from step 3 is copied back —
  a **mirror** restore, so files the broken update *added* (for example a bad
  migration that would fail again on every start) disappear too.
- Files locked by Windows (the currently running script) are skipped during
  clearing and simply overwritten by the copy — safe by design.
- The database was only ever touched by migrations (step 6), and step 2's
  backup exists before that. A migration that fails is not recorded, so
  fixing the package and updating again resumes exactly where it stopped.
- The user sees: *"Update failed and the previous version was restored."*

### The USB path (shops without internet)

*Updates → Install package* accepts a `DaherPhone-update-x.y.z.zip` from a
USB stick and runs the **same pipeline from step 2** — backup, snapshot,
staged validation, rollback. Only the download/checksum step is skipped,
because the file arrived by hand.

---

## 6. What updates can and cannot touch

| | In update packages? | Overwritten by updates? |
|---|---|---|
| Application code (`app/`, `public/`, `bin/`, `docs/`) | yes | yes |
| `database/migrations/` | yes | yes (new files run once) |
| `VERSION` | yes | yes — that's the point |
| `config/app.ini` (ports, passwords, paths) | **never packaged** | **never** |
| `storage/` (logs, sessions) | **never packaged** | **never** |
| `Database\` (MariaDB data files) | never | never — only migrations touch the DB |
| `Backups\` | never | never (updates *add* a backup) |
| Bundled PHP / MariaDB (`Server\`) | no | no — server upgrades ship as a new `DaherPhoneSetup.exe`, which installs over the top and also preserves all of the above |

---

## 7. How this was verified

The automated package test (`deploy\package-e2e.ps1`) runs against a real,
freshly-initialized package on every build:

- a genuine 1.3.0 → 1.3.1 update: applied, `VERSION` bumped, pre-update
  database backup present;
- a deliberately **broken** package (invalid migration): fails safely,
  `VERSION` rolled back, the bad migration file removed by the mirror
  restore, application still serving afterwards.

Both scenarios pass (16/16 checks in the current build).

---

## 8. Troubleshooting quick table

| Symptom in the shop | Meaning | Fix |
|---|---|---|
| "Could not reach the update server" | no internet / wrong URL | check the address; or use the USB path |
| "checksum mismatch. Update cancelled" | download corrupted or zip re-uploaded without updating `update.json` | re-generate `update.json` (`build.ps1 -UpdateZip`) and re-host both files |
| "not a Daher Phone update package" | wrong zip (e.g. the 212 MB full package or a random file) | send the `DaherPhone-update-*.zip`, not `DaherPhoneSetup.exe` |
| "Update failed and the previous version was restored" | a migration or file copy failed; shop keeps running on the old version | read `Application\storage\logs\php-error.log`, fix the package, release again |
| Update succeeded but a page errors | code bug in the new version, not an update failure | restore isn't needed — ship a fix as the next update; the pre-update DB backup is in `Backups\` if data was affected |
