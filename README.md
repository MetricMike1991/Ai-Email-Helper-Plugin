# AI Email Helper (WordPress plugin)

Connect a SiteGround / IMAP email inbox to WordPress and use the OpenAI API to
summarize incoming mail, suggest reply drafts, learn from your past replies, and
answer using content scanned from your website's FAQ pages.

**Nothing is auto-sent.** The AI only drafts; you approve and send.

## Project status — where we are

**Stage: initial scaffold complete (v0.1.0). Not yet tested against a live server.**

Done ✅
- Full plugin structure (bootstrap, 11 classes, admin UI, assets, uninstall).
- IMAP reading, SMTP sending (on approval), OpenAI summaries + reply drafts.
- Encrypted credential storage, FAQ URL scanning, tone learning, 4 DB tables.
- Admin pages: Inbox, FAQ Sources, Settings (with Test IMAP button).
- Cron auto-fetch every 10 minutes.

Not done yet / next steps ⏳
- **Live testing** — no local WordPress + PHP environment set up yet, so nothing
  has been run/linted against a real server. (PHP is not installed on this machine.)
- Verify the SiteGround IMAP/SMTP host, ports and app-password.
- Confirm the PHP `imap` extension is enabled on the host.
- Roadmap: internal WP page picker (currently external URLs only), embeddings for
  smarter FAQ retrieval, Gmail/Outlook OAuth, attachments/threading, categorization.

How to try it right now: copy the folder to `wp-content/plugins/`, activate,
fill in **AI Email → Settings**, click **Test IMAP Connection**, then **Fetch New Email**.

## Features

- **IMAP reading** — fetches recent messages from your mailbox into WordPress.
- **AI summaries** — 2–3 sentence summary of each email (OpenAI).
- **Suggested replies** — drafts a reply you can edit, then approve & send via SMTP.
- **Learning** — every reply you approve is stored and used to match your tone.
- **FAQ knowledge** — paste public URLs; their text is used when drafting answers.
- **Encrypted secrets** — passwords and the OpenAI key are AES-256 encrypted at rest.

## Requirements

- WordPress 6.0+, PHP 7.4+
- The PHP **imap** extension enabled (ask SiteGround support if it isn't).
- An OpenAI API key (platform.openai.com).

## SiteGround connection settings (typical)

| Setting        | Value                          |
| -------------- | ------------------------------ |
| IMAP host      | `mail.yourdomain.com`          |
| IMAP port      | `993` (SSL)                    |
| SMTP host      | `mail.yourdomain.com`          |
| SMTP port      | `465` (SSL)                    |
| Username       | your full email address        |
| Password       | your mailbox / app password    |

Confirm exact values in **SiteGround → Email → Manage → Mail Configuration**.

## Install (development)

1. Copy this folder into `wp-content/plugins/ai-email-helper`.
2. Activate **AI Email Helper** in WordPress admin.
3. Go to **AI Email → Settings**, enter IMAP/SMTP + OpenAI details, and save.
4. Click **Test IMAP Connection**.
5. Go to **AI Email → Inbox** and click **Fetch New Email**.

## Structure

```
ai-email-helper.php          Bootstrap + constants + hooks
uninstall.php                Drops tables/options on delete
includes/
  class-aieh-crypto.php        AES-256 encrypt/decrypt for secrets
  class-aieh-settings.php      Settings storage (encrypts secrets)
  class-aieh-activator.php     Creates DB tables
  class-aieh-imap-client.php   Reads mail via PHP imap
  class-aieh-smtp-mailer.php   Sends approved replies via PHPMailer/SMTP
  class-aieh-openai-client.php OpenAI Chat Completions wrapper
  class-aieh-faq-scanner.php   Fetches URLs -> stored FAQ knowledge
  class-aieh-learning-store.php Stores approved replies for tone learning
  class-aieh-email-processor.php Summaries + reply drafting
  class-aieh-ajax.php          Admin AJAX endpoints (nonce + cap checks)
  class-aieh-admin.php         Menus, settings save, assets
  class-aieh-plugin.php        Loader + cron
admin/
  views/{inbox,faq,settings}.php
  css/admin.css
  js/admin.js
```

## Roadmap ideas

- Gmail/Outlook OAuth as an alternative to IMAP passwords.
- Vector embeddings for smarter FAQ retrieval on large knowledge bases.
- Per-folder fetching, attachments, threading, and categorization/priority.
- Scan internal WP pages/posts (not just external URLs).

## Security notes

- Secrets are encrypted with a key derived from WordPress salts; keep
  `wp-config.php` salts private.
- All admin actions require the `manage_options` capability and a nonce.
- Draft/send never happens without an explicit click.
