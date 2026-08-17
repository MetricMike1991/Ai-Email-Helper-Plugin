# AI Email Helper (WordPress plugin)

Connect a SiteGround / IMAP email inbox to WordPress and use the OpenAI API to
triage mail, summarize it, draft replies in your own tone, and manage the work
on a built‑in AI‑assisted Kanban To‑Do board.

**Nothing is auto-sent.** The AI only drafts and suggests; you approve and send.

Repo: <https://github.com/MetricMike1991/Ai-Email-Helper-Plugin>

---

## Project status

**v0.3.2 — working against a live SiteGround mailbox.** IMAP connection, fetching,
read/unread sync, the To‑Do board and the AI chat assistant are all in use.

Verified working ✅
- Live IMAP connect + fetch from SiteGround (with the SSL‑cert workaround).
- Read/unread + move‑to‑folder mirrored to the real mailbox.
- AI summaries, tone‑matched reply drafting, and the Kanban To‑Do board.

Next / roadmap ⏳
- Turn inbox emails into tasks from the chat assistant.
- Scheduled "what's due" email digest via wp‑cron.
- Internal WP page scanning (currently external URLs only), embeddings for large
  FAQ sets, Gmail/Outlook OAuth, attachments/threading.

---

## Features

### Inbox
- **IMAP reading** in *peek* mode — fetching never marks mail read on the server.
- **Server‑mirrored status** — read/unread and replied come from the real IMAP
  flags. Marking read/unread or replying writes back to the mailbox by UID.
- **Move to folder** — a per‑email dropdown of your real mailbox folders.
- **Hybrid unread sync** — "Sync Unread from Server" runs a live `UNSEEN` search
  and reconciles the local view to match every device.
- **Filters** — All / Unread / Read / Replied, plus internal **category tags**.
- **Collapsible cards** with unread shading for a tidy list.
- **AI summaries** — concise summary of each email (handles nested‑MIME bodies).
- **Reply tools** — *Suggest Reply* (AI draft using your FAQ knowledge + learned
  tone) or *Write Reply* (type your own), then **Improve & match my tone** to
  polish wording to sound like you. Auto greeting + configurable sign‑off.
- **Approve & Send** via SMTP — nothing sends without your click; sent replies are
  stored to keep learning your tone.

### To‑Do Board (Kanban)
- **Customizable columns** with an **AI description** ("what belongs here").
- **Drag & drop** cards within/between columns; reorder columns.
- **Cards** with title, notes, priority, category, **due date**, and **recurrence**
  (daily/weekly/monthly). Add manually or from an email ("Add to To‑Do").
- **Complete / Reopen** — completing stamps a timestamped line into the notes and
  records a completion time; recurring cards reschedule and reopen automatically.
  Completion is tracked by that timestamp, **never** by which column a card is in.
- **AI Prioritise & Sort** — reads all open cards + column descriptions + the
  current date/time, assigns priorities, routes cards into the best column, and
  sorts each column.
- **AI Overview** — a briefing of what's urgent/overdue/next (also considers your
  recent chat).
- **Chat with AI** — talk about the board and issue plain‑English commands
  ("add a weekly boiler check on Fridays", "move the invoice card to Done",
  "what's overdue?"). It replies and performs the change.

### Knowledge & learning
- **FAQ sources** — paste public URLs; their text is used when drafting answers.
  Search, inline‑edit, and "Ask AI to revise" existing entries.
- **Learning** — approved replies are stored and used as tone examples; searchable
  and editable, with AI‑assisted revision.

### Security
- Passwords and the OpenAI key are **AES‑256 encrypted** at rest (key derived from
  WordPress salts).
- All admin actions require `manage_options` + a nonce.

---

## Requirements

- WordPress 6.0+, PHP 7.4+
- The PHP **imap** extension enabled (ask SiteGround support if it isn't).
- An OpenAI API key (<https://platform.openai.com>).

## SiteGround connection settings (typical)

| Setting   | Value                        |
| --------- | ---------------------------- |
| IMAP host | `mail.yourdomain.com`        |
| IMAP port | `993` (SSL)                  |
| SMTP host | `mail.yourdomain.com`        |
| SMTP port | `465` (SSL)                  |
| Username  | your full email address      |
| Password  | your mailbox password        |

Confirm exact values in **SiteGround → Email → Manage → Mail Configuration**.

> **SSL certificate note:** SiteGround mail certificates are issued for the
> server hostname (e.g. `gnldmXXXX.siteground.biz`), not your domain, so strict
> validation fails with a "hostname mismatch" error. Untick **Validate SSL
> certificate** under IMAP (and SMTP) in Settings — the connection stays
> encrypted. (Alternatively, use the server hostname as the host.)

---

## Install

Upload the packaged zip in **Plugins → Add New → Upload Plugin**, or copy the
`ai-email-helper` folder into `wp-content/plugins/`.

1. Activate **AI Email Helper**.
2. **AI Email → Settings**: enter IMAP, SMTP, OpenAI, and your Reply Signature; save.
3. Click **Test IMAP Connection** (save first — the test reads saved settings).
4. **AI Email → Inbox → Fetch New Email**.
5. Explore **To‑Do Board** and **Chat with AI** (needs the OpenAI key).

### Building the zip

```powershell
& ".\build-zip.ps1"
```

Produces `ai-email-helper-<version>.zip` — a WordPress‑ready package (single
top‑level `ai-email-helper/` folder, forward‑slash entries).

---

## Structure

```
ai-email-helper.php               Bootstrap, constants, hooks, version
uninstall.php                     Drops tables/options on delete
build-zip.ps1                     Packages a WordPress-ready zip
includes/
  class-aieh-crypto.php             AES-256 encrypt/decrypt for secrets
  class-aieh-settings.php           Settings storage (encrypts secrets)
  class-aieh-activator.php          Creates/upgrades DB tables
  class-aieh-imap-client.php        IMAP read/flags/folders/move/sync
  class-aieh-smtp-mailer.php        Sends approved replies via PHPMailer/SMTP
  class-aieh-openai-client.php      OpenAI Chat Completions wrapper
  class-aieh-faq-scanner.php        URL -> stored FAQ knowledge (+ edit)
  class-aieh-learning-store.php     Approved replies for tone learning
  class-aieh-email-processor.php    Summaries, drafting, signature
  class-aieh-tasks.php              Kanban tasks, columns, AI prioritise/overview/chat
  class-aieh-ajax.php               Admin AJAX endpoints (nonce + cap checks)
  class-aieh-admin.php              Menus, settings save, assets
  class-aieh-plugin.php             Loader, cron, DB upgrade
admin/
  views/{inbox,faq,learning,todo,settings}.php
  css/admin.css
  js/admin.js
```

## Database tables

`wp_aieh_messages`, `wp_aieh_drafts`, `wp_aieh_faqs`, `wp_aieh_learning`,
`wp_aieh_tasks`. Created on activation and upgraded automatically on update.

## Security notes

- Secrets are encrypted with a key derived from WordPress salts; keep
  `wp-config.php` salts private.
- All admin actions require the `manage_options` capability and a nonce.
- Draft/send never happens without an explicit click.
