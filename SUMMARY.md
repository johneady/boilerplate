# Dokploy migration — session summary (2026-09-17 → 18)

Context for picking this up cold. Two servers, ten deployments, nine applications
migrated from building on the deploy host to pulling prebuilt images from GHCR,
plus a move to Alpine base images.

---

## The two servers

| Host | Role |
|---|---|
| `23.94.206.118` | **The one that matters.** Dokploy, 10 deployments, all the work below. `john` is in the `docker` group — no sudo needed. |
| `23.94.253.248` | A *second, separate* Dokploy install. Runs the `boilerplate` app. Only touched by accident at the start of the session. |

They are independent installs with independent databases. Nothing configured on
one appears on the other — this caused real confusion mid-session (see *GHCR
credential*, below).

---

## Part 1 — Infrastructure audit (`23.94.206.118`)

### What is good

**Backups are the strongest part of the setup.**

- **33 database backups**: all 10 MariaDB instances have the full
  **daily / weekly / monthly** triad, plus the same three for Dokploy's own
  config. No database is missed.
- **30 volume backups**: all 10 app volumes (uploads, images, WP data) also
  have all three tiers.
- Retention is tiered: 7 daily / 5 weekly / 2 monthly.
- All 63 schedules enabled; none silently off.
- Cron times **staggered in 6-minute steps** (00:00→00:57 daily, 03:00→03:59
  weekly, 05:00→06:00 monthly). On a 2-core box this matters and was deliberate.
- **Verified actually working**, not just configured: logs show
  `✅ Backup uploaded to S3 successfully`. Tally across all history is
  **353 success / 10 failure**, and all 10 failures were Aug 27–Sep 1 setup
  errors (wrong DB name). Zero failures since.
- Off-site destination: **iDrive e2** (`ca-east-1`) — correctly not on the same box.

**Other solid items**

- All 17 domains on HTTPS with Let's Encrypt, `www` variants covered.
- 2FA on the admin account; SSH hardened (`PermitRootLogin no`,
  `PasswordAuthentication no` via `00-hardening.conf`).
- Per-container memory limits on most containers; actual usage low.
- `unattended-upgrades` installed; consistent `unless-stopped` restart policies.

### Changed during the session

**Port 3000 closed.** The Dokploy admin panel was published on `0.0.0.0:3000`
over plaintext HTTP. Removed with:

```
docker service update --publish-rm "published=3000,target=3000,protocol=tcp,mode=host" dokploy
```

Note: plain `--publish-rm 3000` reported success but silently did nothing —
host-mode ports need the full descriptor. Access is unchanged via
`https://panel.powerphpscripts.com`, which was already configured in
`/etc/dokploy/traefik/dynamic/dokploy.yml` with Let's Encrypt. Rollback spec
saved at `~/dokploy-port-rollback/` on the server.

**Correction to the original audit:** the claim that ports 3000/2377/7946 were
"OPEN from internet" was **unreliable**. It used bash `/dev/tcp`, and a control
test showed ports 9999 and 44321 also report "OPEN" with nothing behind them —
the upstream network (RackNerd) accepts TCP handshakes on arbitrary ports.
Closing 3000 was still correct (it genuinely bound `0.0.0.0` and served the
panel), but **2377/7946 exposure was never actually verified**. They do bind to
all interfaces, which is worth restricting on a single-node swarm, but there is
no evidence they are reachable externally. To settle it, run
`nmap -Pn -sV -p 2377,7946 23.94.206.118` from outside the network.

**Disk reclaimed on the server:** 4GB (26G→22G) by removing six superseded
images **by name**. Deliberately did *not* run `docker image prune -a` — three
images showing as `<none>` are actively serving (pet-adoption's containers,
Dokploy itself, and its Postgres). A blanket prune would have taken those out.

---

## Part 2 — GHCR migration (the main work)

### The pattern, per repo

1. Copy `docker/publish-image.sh`, rename `BUILDER` to `<repo>-publish`
2. Copy `.github/workflows/docker.yml`, **match the repo's default branch**
   (`master` for kckitties and TeamScheduler)
3. Swap `build: context: .` → `image: ghcr.io/${GHCR_REPOSITORY}:${IMAGE_TAG}`
   plus `pull_policy: always`
4. Add `/.docker-cache` to `.gitignore` — **needed in all 8 repos**; kckitties'
   cache alone was 3.7GB and would have been committed
5. Pick the smoke test by framework: `artisan --version` (Laravel) or
   `bin/cake.php version` with a throwaway `SECURITY_SALT` (CakePHP)
6. Dry-run build, push, set Dokploy env vars, then `git push`

**Order matters:** `autoDeploy` is on for every app, so `git push` triggers a
redeploy immediately. Set `GHCR_REPOSITORY` and `IMAGE_TAG` in Dokploy *first*
or the deploy fails (`GHCR_REPOSITORY` has a `:?` guard; `IMAGE_TAG` falls back
to `latest`, which is never published). A failed deploy is harmless — running
containers keep serving.

### Status

| Repo | Site | Size | Live |
|---|---|---|---|
| support-manager | support.powerphpscripts.com | → Alpine | ✅ |
| kckitties | kckitties.com | → Alpine | ✅ |
| affiliate-master | am.powerphpscripts.com | 782MB → 261MB | ✅ |
| powerphpscripts | powerphpscripts.com | 805MB → 277MB | ✅ |
| TeamScheduler | jet-teams.com | 805MB → 396MB | ✅ |
| matthewalexandarfurniture | MatthewAlexanderFurniture.com | 765MB → 269MB | ✅ |
| volunteer-scheduler | CommunityCare.duckdns.org | 765MB → 240MB | ✅ |
| pet-adoption | pet-manager + TheForgottenFerals | 1.27GB → 301MB | image building |
| *(boilerplate)* | *on the other server* | 757MB → 272MB | ✅ |

**The Hart Foundation** (WordPress) is out of scope — `sourceType: raw`, no repo.

### GHCR credential — the confusing part

Dokploy stores compose env **encrypted** in its Postgres (`enc:v1:...`), so
grepping the DB for `GHCR_REPOSITORY` **always returns nothing** regardless of
what is set. The `.env` file at
`/etc/dokploy/compose/<app>/code/.env` is only written **at deploy time**, so a
saved-but-not-yet-deployed var does not appear there either. An early claim that
"the vars are not set" was wrong for exactly this reason.

How the pull actually authenticates: adding a registry in the Dokploy UI writes
a row to the `registry` table **and** a credential into the Dokploy container's
own `/root/.docker/config.json`. Compose deploys shell out from inside that
container, so that file is what does the work. Don't hand-edit it — Dokploy
rewrites it and a container restart loses the change.

---

## Part 3 — Alpine migration

Boilerplate moved first (commit `f2c827f`): **757MB → 272MB on disk, 229MB → 83MB
per pull**. The rest followed. Key mechanics, all documented in
`.ai/rules/dockerfile.md`:

- Toolchain goes into a `.build-deps` **virtual package** deleted in the same
  `RUN`. On Debian those ~190MB can't be reclaimed — a delete in a child layer
  only writes whiteouts, so the bytes still ship.
- A **`scanelf` pass** re-adds the shared libraries the freshly built `.so` files
  actually link against, *before* `apk del` runs. Without it, apk takes icu's
  libraries and `intl` fails to load.
- Alpine package names differ: `icu-dev`, `libjpeg-turbo-dev`, `freetype-dev`.

### Two Alpine traps, both now guarded everywhere

1. **supervisor** lives at `/etc/supervisord.conf` with an `/etc/supervisor.d`
   include, not Debian's `conf.d` layout the `CMD` speaks. Recreated with a
   `sed`, guarded by `grep -q` — otherwise the container starts with **no
   programs**, boots, and looks healthy while running nothing.
2. **nginx** includes `/etc/nginx/http.d/*.conf` and has no `sites-enabled`. The
   vhost must overwrite `http.d/default.conf` (same filename as Alpine's stock
   default server, which is what removes it). Guarded by `RUN nginx -t` —
   otherwise the container **serves 404s while supervisor reports RUNNING**.

### The "NOT Alpine" claim was stale in every repo

Every Dockerfile carried a header saying Alpine was rejected because
`package.json` pins `@rollup/rollup-linux-x64-gnu`,
`@tailwindcss/oxide-linux-x64-gnu` and `lightningcss-linux-x64-gnu`, which are
glibc-only.

**Tested rather than assumed, in every repo**: npm resolves the `-musl` variants
(all already in `package-lock.json`) and the emitted assets are
**md5-identical** to the Debian build, file for file — including
volunteer-scheduler's full set of 11 (fonts and manifest included), and across
matthewalexandarfurniture's Node 22 → 24 bump.

---

## Bugs found and fixed along the way

**gd built without JPEG/FreeType — shipped to production in 2 repos.**
`docker-php-ext-install` re-runs `./configure` for whatever it is building,
resetting the cached configure state of anything configured earlier in the same
`RUN`. With `configure gd` first and `install intl`/`zip` in between, gd came out
PNG-only. Confirmed against the **running production containers** for
**powerphpscripts** and **volunteer-scheduler** — `gd_info()` reported JPEG and
FreeType both empty. Fixed by moving `configure gd` to immediately before
`install gd`; jpeg round trips now succeed. pet-adoption, TeamScheduler and
matthewalexandarfurniture already had this right.

**`curl` would have silently vanished (volunteer-scheduler).** Never explicitly
installed — on Debian it arrived transitively and the `HEALTHCHECK` relied on
that. On Alpine the container would have sat **permanently unhealthy while
serving traffic fine**. Added explicitly; smoke test now reaches `healthy`.

**Multi-GB directories shipping into images via `COPY . .`**, all gitignored so
invisible in diffs:
- `demos/.venv-kokoro` — **1.5GB** PyTorch venv (affiliate-master *and*
  pet-adoption)
- `storage/app/package-sandbox-*` — **338MB** (pet-adoption)
- `storage/app/package-test-*` — **92MB** (affiliate-master); the existing
  `test-*` glob does not match the `package-` prefix
- `storage/framework/views` — Pest's parallel runner writes into `views/test_N/`
  **subdirectories**, which the old `views/*` glob never matched

**PHP 8.4 → 8.5 (matthewalexandarfurniture).** Verified first:
`composer check-platform-reqs` reported `php 8.5.10 success` against the
existing lock. Then bumped `config.platform.php` 8.4.1 → 8.5.0 and regenerated
`platform-overrides` — clean 4-line lock change, no dependency churn.

**Build-time extension assertions added** where missing (affiliate-master,
powerphpscripts, volunteer-scheduler, pet-adoption), checking gd **per format**
— which is precisely what caught the ordering bug.

---

## Git lesson worth keeping

powerphpscripts hit a **divergent branches** error: 2 local commits vs 25 on
origin, because work began without fetching first. `7b330ec` on origin rebuilt
the image on **Sury distro packages**, directly contradicting the Alpine commit —
two designs for one file.

Resolved by merging and taking Alpine wholesale (`git checkout --ours Dockerfile`).
**The important part:** two *other* files from that commit merged **cleanly and
silently** and would have broken Alpine — `supervisord.conf` had
`command=php-fpm8.5` (the Sury binary name; Alpine's is `php-fpm`, so supervisor
would fail to start it every boot). A clean merge is not a correct merge.

**Habit adopted:** `git fetch && git status` before touching any repo. This
caught matthewalexandarfurniture (4 behind, one touching the Dockerfile) and
pet-adoption (18 behind) before they became the same problem.

---

## Local disk cleanup

Workstation hit **99% full / 3.5GB free** mid-session. Freed **58.5GB**:

| Action | Reclaimed |
|---|---|
| Default buildx builder cache | 20GB |
| support-manager + kckitties builder caches | 16GB |
| Test images + 83 dangling images | 13GB |
| Local `.docker-cache` dirs (4 repos) | 7.3GB |

**Why it accumulates:** each `publish-image.sh` run creates a dedicated
`docker-container` buildx builder (required — the default `docker` driver cannot
export a cache), and each keeps its **own** 2–8GB cache. Plus a `.docker-cache`
directory per repo on disk. So every repo costs roughly 2–8GB *twice*.

After a repo is pushed and deployed, drop its cache:

```
docker buildx prune -af --builder <repo>-publish
rm -rf /home/john/php/<repo>/.docker-cache
```

**Not touched:** 60 dangling volumes (9.4GB), 25 of them *named* and holding real
data (`powerphpscripts_mysql-data`, `kckitties_images-pets`, `ollama_ollama`).
They are "dangling" only because their containers are stopped. `docker volume
prune` would destroy them.

---

# Outstanding items

Ordered by value. **#1 is worth more than everything else combined** — it was
measured at ~30x on the live box, and the change is one line per repo.

### 1. OPcache: shared memory is OFF in every FPM worker ⭐ do this first (~30x, measured)

**Not** "opcache disabled" — OPcache is loaded and `opcache.enable=1` everywhere.
But the **shared-memory cache is off**, so FPM reads compiled opcodes from disk
(`/tmp/opcache`) instead of RAM on every request. Real continuous cost on a
2-core box serving ten sites.

Cause is one line in `docker/php/php.ini`:

```ini
opcache.file_cache_only = 1
```

It was added deliberately for the **scheduler/CLI**, with real measurements
(819ms → 431ms warm for forked `schedule:run`). That reasoning is sound — but
`php.ini` has **no SAPI scoping**, so a CLI-intended directive also applies to
FPM. The file's own comment claims these settings "cannot perturb php-fpm's own
shared-memory opcache above", which is exactly what they do.

**Confirmed OFF in production:**
`powerphpscripts`, `support-manager`, `affiliate-master`,
`matthewalexandarfurniture`, `volunteer-scheduler` — all `shm=OFF
file_cache_only=1`.

**Proof the fix works:** `kckitties` reports **`shm=ON`**. Same Alpine base, same
build — its `php.ini` simply omits `file_cache_only`. It has been sitting at
`cached_scripts=665 hits=376363 misses=667` — a **99.8% hit rate** across 376k
requests. That is what the others should look like.

### Measured impact — ~30x faster

Benchmarked on `powerphpscripts` (the live container), same app, same `/up`
endpoint, **one line changed**, 30 requests each:

| | median | min | max |
|---|---|---|---|
| `file_cache_only = 1` (as deployed) | **738ms** | 680ms | 3093ms |
| `file_cache_only = 0` (SHM on) | **24ms** | 22ms | 35ms |

The homepage (a real page, not the health endpoint) came in at a **66ms** median
with SHM on.

This is far beyond the usual "opcache vs no opcache" rule of thumb of 2–3x. The
reason it is so extreme: `file_cache_only` does not merely make caching slower —
for a Laravel app it means **every request does filesystem I/O for ~1000 script
files**, on a 2-core VPS where ten sites compete for the same disk.

Note the **max** column as much as the median: **3093ms → 35ms**. Those
multi-second outliers are the ones users actually notice; they disappear
entirely. Consistency improves more than the average does.

Capacity: at 740ms one PHP worker handles ~1.4 req/s; at 24ms, ~40 req/s.
Roughly **30x more headroom on the same hardware**.

The test container was reverted to `file_cache_only = 1` afterwards and verified
healthy (`powerphpscripts.com` → HTTP 200), so **nothing is fixed yet** — the
real change belongs in `docker/php/php.ini` plus a rebuild and redeploy.

Caveats:
- The 24ms figure is **warm**. Immediately after an FPM restart the hit rate was
  80% and climbing; the first few requests still compile. Normal, self-correcting.
- SHM costs RAM: `opcache.memory_consumption = 192` MB per app. Six apps on a
  3.8GB box is real memory — though it is a ceiling, not a reservation, and the
  measured app used well under it. Worth watching `free -h` after rollout.
- Only `powerphpscripts` was benchmarked; the other five should behave similarly
  but will vary with application size.

**Repos carrying the line:** support-manager, affiliate-master, powerphpscripts,
matthewalexandarfurniture, volunteer-scheduler, pet-adoption.
**Already clean:** boilerplate, kckitties, TeamScheduler.

**The fix:** boilerplate already shows the correct pattern — `opcache.enable=1`,
`opcache.enable_cli=1`, `opcache.file_cache=/tmp/opcache`, and **no**
`file_cache_only`. Either drop the line (CLI still gets the file cache as a
second tier) or, to preserve the measured scheduler win exactly, have the
entrypoint write it into a CLI-only ini per role. Worth re-measuring the
scheduler before and after, since those numbers were carefully obtained.

*How to check:* drop a one-liner calling `opcache_get_status(false)` into the
app's docroot and curl it **through nginx** — a `php -r` on the CLI reports the
CLI's own state, not FPM's, which is what made this invisible.

### 2. TeamScheduler: seeders cannot run on a fresh database

*(This is the "something else I already forgot".)*

`config/Seeds/*.php` — **13 files** — all `use Migrations\AbstractSeed`, but
`cakephp/migrations` **5.2.6** renamed that class to `BaseSeed`. Seeding fails:

```
Failed to load seeds: Class "Migrations\AbstractSeed" not found
[entrypoint] FATAL: seeding failed.
```

**Pre-existing and not Alpine-related** — the production image has the same
problem. It is latent because the live database was restored from a dump rather
than seeded.

It only bites on a genuinely **fresh** database — a new staging instance or a
disaster-recovery rebuild, i.e. exactly when you least want to find it. It is
load-bearing: `src/Application.php` calls `Settings->get(1)` **while building the
middleware queue**, so a migrated-but-unseeded database 500s on every page.

Fix is a search-and-replace across `config/Seeds/` (`AbstractSeed` → `BaseSeed`,
including the `use` lines). Application code rather than infrastructure, so it
was left alone.

### 3. matthewalexandarfurniture: doubled `ghcr.io` prefix

Currently running:

```
ghcr.io/ghcr.io/johneady/matthewalexandarfurniture:sha-93f4e46...
```

`GHCR_REPOSITORY` was set to `ghcr.io/johneady/matthewalexandarfurniture`, but
the compose file already supplies the `ghcr.io/` prefix. Should be:

```
GHCR_REPOSITORY=johneady/matthewalexandarfurniture
```

Working right now (GHCR tolerated the path), containers healthy, so no urgency —
but fix before the next deploy so the tag names the canonical package path.

### 4. Backup alerts are OFF — backups are silent

The only enabled notification is `appBuildError`. All of these are **off**:
`databaseBackup`, `volumeBackup`, `dokployBackup`, `serverThreshold`,
`appDeploy`, `dokployRestart`, `dockerCleanup`.

Backups are excellent but nothing tells you when they break. The Sep 1 failures
were only found by grepping logs.

Toggle at **Settings → Notifications → "Dokploy" → Edit** (email via Brevo to
`john.eady@gmail.com` is already configured and working; there is a
*Test Notification* button).

**Important caveat:** the toggle covers **both** success and failure — the email
template has `✅ Database Backup Successful` / `❌ Database Backup Failed`
branches gated by the one flag. With 63 schedules that is **~21 emails/day,
~640/month**, nearly all green. Pair it with a Gmail filter (`Successful` → skip
inbox + label; `Failed` → keep in inbox / mark important) or the red ones get
buried, which is no better than silence.

Also set **Server Threshold** to ~85% CPU/memory — lower will page during nightly
backup windows when several dumps overlap.

### 5. No restore has ever been tested

353 successful uploads prove **writes** work. Nothing proves a dump is
**restorable**. Untested backups are a hypothesis.

One drill: pull the latest dump for a low-stakes DB (kckitties), restore into a
scratch container, confirm table counts.

### 6. Monthly backup retention is thin

`keepLatestCount: 2` on monthly ≈ 60 days of history. For a slow-corrupting bug
or a client asking for something from six months ago, that is not much. Consider
6–12 — dumps are small (most complete in 3–4s) and storage is cheap.

### 7. Smaller items

- **2 MariaDB containers have no memory limit** (`powerphpscripts-databsae-uigqz4`,
  `powerphpscripts-petmananger-qmilct`) while the others are capped at 192m —
  worth making consistent.
- **Swarm ports 2377/7946** bind to all interfaces. Unverified as externally
  reachable (see the audit correction above) — confirm with `nmap` from outside,
  then restrict via `DOCKER-USER` rules if needed. Requires the sudo password.
- **Stale `*:dokploy` images** on the server become prunable as each app moves to
  GHCR. Remove **by name** after confirming zero container references — never
  `docker image prune -a`.
- **1 pending security update**; Dokploy is v0.30.5, pinned by digest.
