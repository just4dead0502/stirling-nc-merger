# stirlingmerge — Merge to PDF for Nextcloud

A Nextcloud 32+ app that adds a **"Merge to PDF"** file action. Select two or more images in the Files view, reorder them in a dialog, and the app calls a [Stirling PDF](https://github.com/Stirling-Tools/Stirling-PDF) instance to produce a PDF.

- **Authenticated users** — PDF is saved in the same folder as the source images, file list updates instantly.
- **Public share recipients** — PDF is downloaded directly to the browser. No NC account required.
- **Admin settings** — Stirling URL and API key configurable via Admin → Additional settings → Merge to PDF.

> Built with Claude Code (AI-assisted / vibe coded). Disclosed honestly.

---

## Requirements

| Component | Notes |
|-----------|-------|
| Nextcloud | ≥ 32 (tested on 32.0.6) |
| PHP | ≥ 8.1 |
| Stirling PDF | Any recent version. Must be reachable from the NC server (not from the browser). |
| curl | PHP curl extension (standard on all NC installs) |

Stirling PDF does **not** need to be publicly accessible. NC calls it server-side.

---

## Deploy on any server

### Option A — tar.gz (recommended)

```bash
# Copy the package to the NC server
scp stirlingmerge-1.0.0.tar.gz user@nc-server:/tmp/

# On the NC server (adjust paths/container name as needed):
tar -xzf /tmp/stirlingmerge-1.0.0.tar.gz -C /var/www/html/apps/
php occ app:enable stirlingmerge
```

For Docker:
```bash
docker cp stirlingmerge-1.0.0.tar.gz nc-container:/var/www/html/apps/
docker exec nc-container sh -c "tar -xzf /var/www/html/apps/stirlingmerge-1.0.0.tar.gz -C /var/www/html/apps/ && rm /var/www/html/apps/stirlingmerge-1.0.0.tar.gz"
docker exec --user www-data nc-container php occ app:enable stirlingmerge
```

### Configure Stirling URL

Via occ (fastest):
```bash
php occ config:app:set stirlingmerge stirling_url --value='http://stirling-pdf:8080'
php occ config:app:set stirlingmerge stirling_api_key --value='YOUR_KEY'
```

Via admin UI: **Admin settings → Additional settings → Merge to PDF**

---

## Build from source

Requires: Python 3, `paramiko`, a Docker host that can run `node:20-alpine`.

```bash
pip install paramiko

# Edit deploy.py — set HOST, USER, PASSWORD, NC_CONTAINER to match your environment
python3 deploy.py
```

`deploy.py` will:
1. Upload source to the Docker host
2. Run `npm install && npm run build` inside `node:20-alpine`
3. Package the tar.gz (excluding src/, node_modules/)
4. Copy and extract into the NC container
5. Restart the container to clear OPcache
6. Save the tar.gz locally

To build the package only (without deploying):
```bash
# On a machine with Docker:
bash package-app.sh
```

---

## Update

Same process as deploy — the new package overwrites the old app directory:

```bash
# Re-run deploy.py after editing source, or:
docker exec nc-container sh -c "rm -rf /var/www/html/apps/stirlingmerge"
# then extract new package as above
docker restart nc-container   # clear OPcache
```

If a **Nextcloud upgrade** breaks the app (rare — no upper version cap is set):
1. Check NC changelog for `IRegistrationContext`, `FileAction`, or OCS API changes
2. The most likely breakage points are listed in the [Architecture](#architecture) section below
3. Bump `max-version` in `appinfo/info.xml` once confirmed working

---

## Architecture

### File map — what does what

```
stirlingmerge/
├── appinfo/
│   ├── info.xml                  App metadata, NC version range, category
│   └── routes.php                URL → controller mapping
│
├── lib/
│   ├── AppInfo/Application.php   IBootstrap: registers scripts + admin settings
│   ├── Controller/
│   │   ├── MergeController.php   OCS endpoint — authenticated merge (saves to NC)
│   │   ├── PublicMergeController.php  Public endpoint — share-token merge (download)
│   │   └── SettingsController.php     Admin save + test-connection endpoints
│   ├── Service/
│   │   └── MergeService.php      Core logic: resolves files → calls Stirling → returns PDF
│   └── Settings/
│       └── AdminSettings.php     ISettings panel registration
│
├── src/                          Frontend source (compiled by webpack → js/)
│   ├── main.js                   Registers FileAction with @nextcloud/files
│   ├── admin-settings.js         Admin page save/test button handlers
│   └── components/
│       └── MergeDialog.vue       Drag-to-reorder dialog (pure Vue 3, no @nextcloud/vue)
│
├── templates/settings/
│   └── admin.php                 Admin settings HTML form
│
├── webpack.config.js             Custom webpack (no @nextcloud/webpack-vue-config)
└── package.json                  JS dependencies
```

### Request flows

**Authenticated user:**
```
Browser → FileAction.execBatch() → MergeDialog
  → POST /ocs/v2.php/apps/stirlingmerge/api/merge  (fileIds, outputName)
    → MergeService.merge()
      → Stirling PDF /api/v1/convert/img/pdf
    → Save PDF to NC storage → return filePath
  → WebDAV stat → emit files:node:created → file list updates
```

**Public share recipient:**
```
Browser (unauthenticated) → FileAction.execBatch() → MergeDialog (isPublic=true)
  → POST /apps/stirlingmerge/public/merge  (shareToken, paths[], outputName)
    → Verify share token → resolve files within share
      → MergeService.mergeToBytes()
        → Stirling PDF /api/v1/convert/img/pdf
    → DataDownloadResponse (PDF bytes)
  → Browser download
```

### Key technical decisions

| Decision | Reason |
|----------|--------|
| No `@nextcloud/vue` components in dialog | NC ships both Vue 2 (legacy) and Vue 3; mixing causes `_c is not a function` at runtime |
| `devtool: false` in webpack | NC's CSP blocks `eval`; any source-map strategy using eval causes silent JS failure |
| Admin JS as webpack entry, not inline `<script>` | NC32 CSP requires a nonce for inline scripts; nonce is not exposed to app templates |
| `ISettingsManager::registerSetting()` in `boot()` | `IRegistrationContext::registerSettings()` was removed in NC32 |
| Public endpoint returns `DataDownloadResponse` | Share recipients have no NC user context; cannot save to NC storage |

---

## Security notes

- **Public endpoint** (`/apps/stirlingmerge/public/merge`) requires a valid NC share token. NC tokens are cryptographically random and not guessable.
- Path traversal is blocked: any path containing `..` or null bytes is rejected before `Folder::get()` is called.
- Stirling PDF is called server-side only. It never needs to be internet-accessible.
- Only image MIME types are accepted (JPEG, PNG, WebP, GIF, TIFF). No arbitrary file pass-through.
- No credentials are stored in code. Stirling URL and API key live in NC's app config table.

---

## Supported image types

`image/jpeg`, `image/png`, `image/webp`, `image/gif`, `image/tiff`

---

## Limitations

- Mobile apps (Nextcloud Android/iOS) do not execute server-side JS file actions. Users on mobile can use the web browser interface or call the OCS endpoint directly with an app password.
- Merging > 30 images may time out depending on Stirling PDF server capacity (60 s curl timeout).
- Public share merge only works when the share is a **folder** (not a single-file share).

---

## Files to review for a security audit

| File | What to check |
|------|--------------|
| `lib/Controller/PublicMergeController.php` | Share token validation, path traversal guard, MIME enforcement |
| `lib/Service/MergeService.php` | File resolution, multipart construction, Stirling call, output saving |
| `lib/Controller/MergeController.php` | Auth check, file ID ownership, input validation |
| `lib/Controller/SettingsController.php` | URL validation on save, CSRF on save endpoint |
| `appinfo/routes.php` | Route verbs, which routes are public vs protected |
| `src/components/MergeDialog.vue` | `doPublicMerge` — what is sent to the server, how response is handled |
