# France État de Droit: website

A small website with a simple admin area. Editors sign in with Google and edit pages without touching code.
It runs on OVH shared hosting (PHP + SQLite). It is deployed from GitHub: OVH copies the code each time it changes on GitHub.

- Public site: `https://franceetatdedroit.fr`
- Admin area: `https://franceetatdedroit.fr/admin`

Contents

1. [How the site is laid out on OVH](#1-how-the-site-is-laid-out-on-ovh)
2. [First installation on OVH (Git)](#2-first-installation-on-ovh-git)
3. [Google sign-in setup](#3-google-sign-in-setup)
4. [Updating the site later](#4-updating-the-site-later)
5. [Backups and restoring](#5-backups-and-restoring)
6. [Working on your computer](#6-working-on-your-computer)
7. [Good to know](#7-good-to-know)
8. [Alternative: installing with FileZilla](#8-alternative-installing-with-filezilla)
9. [Making the second site (Objectif Solidarité)](#9-making-the-second-site-objectif-solidarité)

---

## 1. How the site is laid out on OVH

Two folders at the top of your OVH FTP space:

```
fred/              <- the Git folder. OVH fills it from GitHub. The domain points here.
  .htaccess        <- sends every visitor into public/ (nothing else is reachable)
  app/             <- the program
  public/          <- what visitors see: index.php, styles, images
  ...              <- tests, README... (in the repository, never served)
fred-private/      <- created by you once, by FTP. Git never touches it.
  config.php       <- your real settings and secrets
  data/            <- database, original images, sessions, logs (created automatically)
  media/           <- uploaded images and PDFs (created automatically)
```

Why two folders: everything that exists only on the server (settings, content, uploads) stays out of the Git folder. A deployment, a forced update or a re-link of Git can never delete it.

The site finds `fred-private/` on its own: it looks for `config.php` next to `app/`, then in a folder with the same name plus `-private` next to it.

What is in the repository:

| Folder or file | What it is |
|---|---|
| `app/` | The program (PHP). Page templates in `app/theme/`. |
| `public/` | The web folder: front controller, styles, fonts, images, editor bundle, `check.php`. |
| `.htaccess` | Serves only `public/` when the domain points at the repository folder. |
| `config.sample.php` | Settings template. |
| `editor-src/` | Source of the admin editor (Quill). Built on a computer, the result is in `public/assets/admin/`. |
| `tests/`, `build/`, `screenshots-src/` | Tests, build and review tools. Never served. |

Not in the repository: `config.php`, `data/`, `public/media/`, `tools/`, `source-content/`, `review/`, `dist/`.

## 2. First installation on OVH (Git)

Plan about 45 minutes, plus the wait for the SSL certificate.

### 2.1 Attach the domain to the `fred` folder

1. OVH Control Panel: **Web Cloud** > **Hébergements** > your plan > **Multisite** tab > **Ajouter un domaine ou un sous-domaine**.
   - Domain: `franceetatdedroit.fr` (also add `www.franceetatdedroit.fr`).
   - **Dossier racine**: `fred`
   - Tick **SSL** (free Let's Encrypt certificate).
2. If the domain is managed in another account, point its DNS to the hosting as OVH shows on the Multisite page.
3. Wait until `https://franceetatdedroit.fr` opens without a certificate warning (minutes to a few hours). **Informations générales** > **Certificat SSL** should say "Oui".

The `fred` folder must be **empty** when you link Git. If you uploaded files into it before, delete them first in FileZilla.

### 2.2 Link the folder to GitHub

The repository is public, so OVH reads it over HTTPS without any key. It contains no secrets: settings, content and uploads live only in `fred-private/` on the server.

(A private repository would need OVH's SSH key. In November 2026 OVH's deployment could not read its own key, "Load key ... invalid format", so the public route is used.)

1. Multisite tab > the `franceetatdedroit.fr` line > **...** > **Configurer Git**.
   - **Repository**: `https://github.com/drouq/france-etat-de-droit.git`
   - **Branch**: `main`
2. Copy the **Webhook URL** OVH shows. On GitHub: **Settings** > **Webhooks** > **Add webhook**:
   - **Payload URL**: the OVH webhook URL
   - **Content type**: `application/json`
   - **Which events**: **Just the push event**. **Active** ticked. **Add webhook**.
3. In OVH, click **Appliquer la configuration**. OVH copies the repository into `fred/`. If it fails, the **...** menu > **Informations du dernier déploiement** shows why.

Never commit `config.php`, passwords or keys: anyone can read the repository.

### 2.3 Create the private folder and config.php

1. In FileZilla, at the top of your FTP space (next to `fred`), create a folder named `fred-private`.
2. On your computer, copy `config.sample.php` to a new file named `config.php`. Open it in Notepad and fill in:
   - `'admin_email' => 'votre.adresse@gmail.com',  // the admin's Google account`
   - `'google_client_id'` and `'google_client_secret'` (see [section 3](#3-google-sign-in-setup)).
   - Leave `'env' => 'production'` and `'dev_login' => false`. Leave `'force_https' => true` once the certificate works.
3. Upload `config.php` into `fred-private/`. Keep a copy somewhere safe: it is never in the repository or in backups.

### 2.4 Check the installation

1. Open `https://franceetatdedroit.fr/check.php`.
2. Every line should say **OK**. Read the **ATTENTION** lines, fix any **ERREUR** line (the "Détail" column says how).
   - The config.php line should end with "(dossier privé, hors Git)".
   - If **SQLite** is missing, stop and tell me. The site would need the plan's MySQL database instead.
3. Then set `'check_enabled' => false` in `config.php` and upload it again. (Deleting `check.php` on the server does not last: the next deployment brings it back.)

### 2.5 Sign in and add the editors

1. Open `https://franceetatdedroit.fr/admin` and click **Se connecter avec Google** with the admin account. The database is created on this first visit, with a home page containing `[À COMPLÉTER ...]` placeholders.
2. **Réglages**: fill in the tagline, the short description, the contact e-mail and, if they exist, the separate HelloAsso links for donations and memberships.
3. **Utilisateurs**: add the 2 or 3 editors by their Gmail address, role **Éditeur**.
4. **Pages**: edit the home page, replace the placeholders, **Enregistrer**.

## 3. Google sign-in setup

About 30 minutes, with the Google account that will own the setup (yours).

1. Open <https://console.cloud.google.com/> and sign in.
2. Project list at the top > **New project**. Name: `France Etat de Droit site`. **Create**, then select it.
3. Menu > **APIs & Services** > **OAuth consent screen** (the newer console calls it **Google Auth Platform**). **Get started** if asked.
   - **App name**: `France État de Droit`. **User support email**: your address.
   - **Audience**: **External**. **Contact information**: your address. Accept the policy, **Create**.
4. **Data Access** (or **Scopes**): **Add or remove scopes**, tick `openid`, `.../auth/userinfo.email`, `.../auth/userinfo.profile`. **Update**, **Save**. These basic scopes need no Google review.
5. **Audience**: either add each editor's Gmail address under **Test users**, or click **Publish app**. Only the people listed in **Utilisateurs** on the site can get in either way.
6. **Clients** (or **Credentials** > **Create credentials** > **OAuth client ID**):
   - **Application type**: **Web application**. Name: `Site web`.
   - **Authorised redirect URIs**, add both:
     - `https://franceetatdedroit.fr/admin/auth/callback`
     - `http://localhost:8000/admin/auth/callback` (testing on your computer)
   - **Create**.
7. Copy the **Client ID** and **Client secret** into `config.php` (`google_client_id`, `google_client_secret`). Use the same values in your local `config.php`.

Visitors who type `www.franceetatdedroit.fr` are sent to `franceetatdedroit.fr`, so Google always sees the address set in `base_url`.

## 4. Updating the site later

1. Changes are committed and pushed to the `main` branch on GitHub.
2. The webhook tells OVH, which updates `fred/` within a minute or so. Without the webhook (or if it fails): Multisite > the domain line > **...** > **Déployer Git**.
3. Open the site once. Database changes (migrations) run on their own on this first visit. They only add things and never delete data.

`fred-private/` (settings, content, uploads) is never touched by an update.

Optional but wise before a big update: **Admin** > **Sauvegarde** > download a backup.

## 5. Backups and restoring

- **Admin** > **Sauvegarde** > **Télécharger une sauvegarde** gives one zip with the database, all images and PDFs (originals and web versions) and the settings template. Your real `config.php` is not included, on purpose: keep your own copy.
- Automatic backups are not part of version 1. Download one after each important change and keep it on your computer or Google Drive.

To restore on a clean installation:

1. Install the site as in section 2, with your `config.php` in `fred-private/`.
2. Unzip the backup. Upload its `data/` and `media/` folders into `fred-private/` (replacing what is there).
3. Open the site. Everything is back: pages, history, settings, users, files.

## 6. Working on your computer

The `tools/` folder holds portable PHP 8.5 and PHP 7.3 (official Windows builds). Nothing is installed on Windows. It is not in the repository: after a fresh clone, download them again (ask me, or see `windows.php.net`).

Start the site locally:

```bash
tools/php85/php.exe -S localhost:8000 -t public public/index.php
```

Then open <http://localhost:8000> and <http://localhost:8000/admin>. The last argument (`public/index.php`) makes `/sitemap.xml`, `/robots.txt` and `/media/...` work with PHP's built-in server. On OVH, Apache and `.htaccess` do that job.

Locally, `config.php` sits next to `app/` (not committed) with `'env' => 'development'`, `'base_url' => 'http://localhost:8000'`, `'force_https' => false`. With `'dev_login' => true`, the login page also shows **Entrer sans Google**. It only works on your own computer in development mode, never on the server.

Run the automatic tests on both PHP versions:

```bash
tools/php73/php.exe tests/run.php
```

```bash
tools/php85/php.exe tests/run.php
```

Rebuild the admin editor after changing `editor-src/src/admin.js` (needs Node.js), then commit the result in `public/assets/admin/`:

```bash
cd editor-src && npm install && npm run build
```

Screenshots for review, with example content on port 8200 (needs Node.js and Microsoft Edge):

```bash
tools/php85/php.exe build/review-site.php
```

```bash
tools/php85/php.exe -S localhost:8200 -t tests/tmp/review-site/www tests/tmp/review-site/www/index.php
```

```bash
cd screenshots-src && npm install && node shoot.mjs http://localhost:8200 ../review/latest
```

## 7. Good to know

- **PHP 7.3** no longer gets security fixes. The site runs on it because other sites on the plan need it. The code also runs unchanged on PHP 8.x (tested on 8.5). Move to PHP 8 as soon as you can. OVH documents that a `.ovhconfig` file in a site's root folder can set its PHP version in a multisite setup. Ask OVH whether that works on your plan: it could let this site use PHP 8 while the others stay on 7.3.
- **Upload limits** are in `config.php` (`max_image_mb`, `max_pdf_mb`). The server's PHP limits must be at least as high. `check.php` shows them.
- **Images** are resized on the server: web versions at 480, 960 and 1600 px wide (WebP), originals kept in `data/originals`. Phone photos are turned upright and their location data is removed.
- **Replacing a file** in the Médiathèque keeps every page that uses it working.
- **Changing a page address** creates a permanent (301) redirect from the old address automatically.
- **History**: each page keeps its last 20 versions. "Restaurer" brings back the text, title, image and search-engine texts, and keeps the current version in the history too.
- **Privacy**: no analytics, no outside fonts or scripts, no cookies for visitors (so no cookie banner). Videos come from `youtube-nocookie.com` or Vimeo and only load when the visitor clicks them. Only signed-in editors get a session cookie.
- **Errors** are written to `fred-private/data/logs/php-errors.log`, never shown to visitors.
- **Two editors on the same page**: if someone saved the page while you were editing it, your save is refused with a message, so nobody overwrites the other's work by mistake.
- **Legal pages** (mentions légales, privacy): create a normal page, untick "Afficher dans le menu principal", tick "Afficher dans le pied de page".
- **Never edit files in `fred/` by FTP**: the next deployment would overwrite them, or block on a conflict.

## 8. Alternative: installing with FileZilla

If Git deployment is not available, the site can be uploaded by hand.

1. Build the package: `tools/php85/php.exe build/build.php`. This creates `dist/` with `app/`, `www/`, `config.sample.php`.
2. On OVH, create `fred/` with `fred/www/` inside, and set the domain's **Dossier racine** to `fred/www`.
3. In FileZilla (**Server** > **Force showing hidden files**, so `.htaccess` files are sent): upload `dist/app` into `fred/`, the contents of `dist/www` into `fred/www/`.
4. Put `config.php` in `fred/` next to `app/`. `data/` is created next to it, uploads go to `fred/www/media/`.
5. Updates: build again and upload `dist/app` and `dist/www` with **Overwrite**. Never upload `config.php`, `data/` or `www/media/` from your computer. `dist/` never contains them.

## 9. Making the second site (Objectif Solidarité)

The second site is a separate copy of this repository (its own GitHub repository), with its own `fred-private`-style folder, `config.php`, domain and Google client. The two copies share nothing.

Everything specific to a site lives in:

- `config.php` (domain, Google client, admin, limits),
- the **Réglages** screen (name, contact, HelloAsso links, logo, footer),
- `app/theme/` (page templates), `public/assets/css/site.css` and `public/assets/img/` (look and logo).

The platform already supports 3 menu levels, card grids with optional links and the media library, which that site needs.
