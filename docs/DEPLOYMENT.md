# Daher Phone — Deployment guide

How the developer turns this repository into `DaherPhoneSetup.exe`, and how the
customer experiences it. Written for future-you; the customer never reads this.

---

## What the customer gets

1. You send them **`DaherPhoneSetup.exe`** (~35 MB).
2. They run it: normal Windows wizard → *Next → Install → Finish*.
3. A **Daher Phone** icon appears on the Desktop and in the Start Menu.
4. Opening it shows a small "Starting…" window (first run also prepares the
   database — about a minute), then the app opens in their browser and the
   launcher waits in the system tray.
5. Tray icon → *Open Daher Phone* / *Exit (stop services)*.

They never see XAMPP, PHP, Apache, MySQL, Git or VS Code. Login:
`admin / admin123` — make them change it on day one.

## Architecture decisions (read before changing things)

- **No Apache.** The bundled PHP binary serves the app itself on
  `127.0.0.1:8123`. One process fewer, no `httpd.conf`, no port-80 fights,
  and it is the exact configuration every automated test in this repo runs.
  `server.ini` isolates the choice — a real web server can be slotted in later.
- **Bundled MariaDB on port 3307** — never collides with a developer XAMPP
  (3306) on the same machine.
- **Everything lives in `C:\Daher Phone`** (user-changeable) because the
  package contains the database and receives backups — Program Files is
  read-only by design and wrong for this.

## Package layout (what the installer ships)

```
Daher Phone\
├── Server\PHP\          bundled PHP (php.ini included; no Apache — see above)
├── Server\MariaDB\      bundled MariaDB (bin + share; GPL — COPYING included)
├── Application\         the PHP application (this repository, production copy)
│   └── config\app.ini   installation settings — NEVER overwritten by updates
├── Database\data\       MariaDB data files (created on first launch)
├── Backups\             daily + pre-update database backups (.sql)
├── Updates\             update downloads, staging, rollback copies
├── Logs\                launcher + PHP error logs
├── server.ini           ports & folder names for the launcher
└── DaherPhone.exe       the launcher (C#, compiled by build.ps1)
```

## Build steps (developer machine)

```powershell
# 1. Full package → deploy\build\Daher Phone\   (+ update zip & feed template)
powershell -ExecutionPolicy Bypass -File deploy\build.ps1 -UpdateZip

# 2. Prove the package works, headless (16 automated checks:
#    fresh DB → login → update → broken-update rollback → backup)
powershell -ExecutionPolicy Bypass -File deploy\package-e2e.ps1

# 3. Rebuild pristine (the E2E dirties the stage), then compile the installer
powershell -ExecutionPolicy Bypass -File deploy\build.ps1
powershell -ExecutionPolicy Bypass -File deploy\build-installer.ps1
#    → deploy\build\DaherPhoneSetup.exe
```

Server binaries are sourced from the local XAMPP (`C:\xampp\php`,
`C:\xampp\mysql`) — override with `-PhpSource` / `-MariaDbSource`.

## Releasing an update

> Full reference — versioning, migration rules, the apply pipeline, rollback
> behavior, troubleshooting: **[docs/UPDATES.md](UPDATES.md)**. Short version:

1. Bump the `VERSION` file (e.g. `1.4.0`); add any schema change as
   `database/migrations/NNN_description.sql`. Commit.
2. `deploy\build.ps1 -UpdateZip` → produces
   `DaherPhone-update-1.4.0.zip` + `update.json` (sha256 pre-filled).
3. Host both files anywhere (GitHub Releases works); put the real zip URL
   into `update.json`.
4. In the shop: **Updates → Check for updates → Update now.** The app backs up
   the database, keeps a rollback copy, applies files, runs migrations — and
   restores itself automatically if anything fails.
5. No internet at the shop? Put the zip on a USB stick →
   **Updates → Install package**.

Update packages never touch `config/app.ini`, `storage/`, `Database\`,
`Backups\` — customer data and settings survive by construction.

## Production configuration checklist (Phase 1)

- [x] `app.ini` present → `debug = 0` (stack traces off), port 3307, package paths
- [x] Errors logged to `Application\storage\logs\php-error.log`
- [x] All paths derived from the install location — no hardcoded directories
- [x] `expose_php = Off`, sane `memory_limit`/upload limits in bundled `php.ini`
- [x] Data folders writable (install target is user-writable by design)
- [x] Default password documented as must-change

## Clean-machine test plan (Phase 10)

On a Windows PC (or VM) without XAMPP/PHP:

| # | Test | Expected |
|---|------|----------|
| 1 | Run `DaherPhoneSetup.exe` | wizard completes, Desktop + Start Menu shortcuts |
| 2 | First launch | "preparing database" ≈ 1 min → browser opens → login works |
| 3 | Second launch (already running) | opens a browser tab instantly (single instance) |
| 4 | Reboot → launch | services start cleanly again |
| 5 | Sell / repair / return / refund | daily flows work (see docs/TESTING.md) |
| 6 | Backup page → create + download | file appears in `Backups\` |
| 7 | Restore an earlier backup | data returns to that state |
| 8 | Updates → install a test package | version bumps; data intact |
| 9 | Updates → install a broken package | friendly error; old version still runs |
| 10 | Tray → Exit | php + mysqld gone from Task Manager |
| 11 | Re-install same setup on top | data, settings, backups all preserved |
| 12 | Uninstall | program gone; `Database\`, `Backups\`, `Logs\` left behind |

The four failure paths the launcher handles with friendly messages: database
won't start, database won't prepare, web port taken, app not answering — each
names `Logs\launcher.log` for support.
