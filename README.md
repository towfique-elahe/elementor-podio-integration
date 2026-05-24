# Elementor → Podio Integration

**Version:** 2.0  
**Author:** [Towfique Elahe](https://towfiqueelahe.com/)  
**Requires:** WordPress 5.8+, PHP 7.4+, Elementor Pro

Sends Elementor form submissions to Podio items. Supports multiple forms with fully dynamic field mapping — no code changes needed when your forms change.

---

## Table of Contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Quick Start](#quick-start)
4. [Admin Pages](#admin-pages)
5. [Step 1 — API Credentials](#step-1--api-credentials)
6. [Step 2 — Authentication](#step-2--authentication)
7. [Step 3 — Form Mappings](#step-3--form-mappings)
   - [Finding the Elementor Form Name](#finding-the-elementor-form-name)
   - [Finding the Podio App ID](#finding-the-podio-app-id)
   - [Finding Podio External Field IDs](#finding-podio-external-field-ids)
   - [Supported Field Types](#supported-field-types)
   - [Category Field Mapping](#category-field-mapping)
8. [Debug Logging](#debug-logging)
9. [How Form Submission Works](#how-form-submission-works)
10. [File Structure](#file-structure)
11. [Troubleshooting](#troubleshooting)
12. [Changelog](#changelog)

---

## Requirements

| Requirement      | Minimum     |
|-----------------|-------------|
| WordPress       | 5.8         |
| PHP             | 7.4         |
| Elementor Pro   | Any version |
| Podio account   | With API access enabled |

---

## Installation

1. Upload the `elementor-podio-integration` folder to `/wp-content/plugins/`.
2. Activate the plugin via **Plugins → Installed Plugins**.
3. Follow the [Quick Start](#quick-start) steps below.

---

## Quick Start

1. Go to **Settings → Podio Integration** and enter your API credentials.
2. Go to **Podio Integration → Authentication** and click **Authenticate with Podio**.
3. Go to **Podio Integration → Form Mappings** and configure each form.
4. Submit a test form and check **Podio Integration → Debug Logs** to confirm it worked.

---

## Admin Pages

The plugin adds two areas to your WordPress admin:

### Settings → Podio Integration
API credentials only — Client ID, Client Secret, your Podio email, password, and the debug mode toggle.

### Podio Integration (top-level menu)
Three sub-pages:

| Sub-page | Purpose |
|----------|---------|
| **Form Mappings** | Configure which Elementor forms map to which Podio apps, and how each field maps |
| **Authentication** | View token status, authenticate, refresh, or revoke the access token; test the live API connection |
| **Debug Logs** | View, filter, copy, download, and clear the plugin's activity log |

---

## Step 1 — API Credentials

Navigate to **Settings → Podio Integration**.

| Field | Where to find it |
|-------|-----------------|
| **Client ID** | [podio.com/settings/api](https://podio.com/settings/api) → your API key |
| **Client Secret** | Same page, next to Client ID |
| **Podio Account Email** | Your Podio login email |
| **Podio Account Password** | Your Podio login password |
| **Debug Mode** | Check this while setting up; uncheck on production |

Click **Save Credentials**, then move to Step 2.

---

## Step 2 — Authentication

Navigate to **Podio Integration → Authentication**.

Click **Authenticate with Podio**. The plugin calls `https://podio.com/oauth/token` using the password grant flow and stores the resulting access token and refresh token in the WordPress database.

### Token lifecycle

- Tokens expire after approximately **4 hours**.
- If a **refresh token** is available, the plugin renews the access token automatically in the background the next time any form is submitted (via the WordPress `shutdown` hook).
- You can also manually refresh or revoke from this page at any time.
- Use the **Test Connection** button to verify the token works without submitting a real form.

> **Security note:** Tokens are stored in `wp_options`. WordPress encrypts data at the database level only if your host enables encryption at rest. For most installs this is fine — the token grants API access equivalent to your Podio password.

---

## Step 3 — Form Mappings

Navigate to **Podio Integration → Form Mappings**.

Click **+ Add Form Mapping** to create a new block. You can add as many blocks as you have forms.

Each block contains:
- **Elementor Form Name** — must match exactly
- **Podio App ID** — the destination Podio app
- **Field Mappings** — a dynamic table of field pairs

Click **+ Add Field** to add a row. Click **Save Mappings** when done.

---

### Finding the Elementor Form Name

1. Open the page in Elementor editor.
2. Click the Form widget.
3. In the left panel, go to **Content** tab.
4. The **Form Name** field is near the top.

The plugin matches submissions by this exact name (case-sensitive).

---

### Finding the Podio App ID

1. Open your Podio app.
2. Click the **wrench (⚙)** icon in the top right of the app.
3. Select **Developer** from the dropdown.
4. The **App ID** is displayed on that page.

---

### Finding Podio External Field IDs

The **External ID** is how Podio identifies each field programmatically.

1. Open your Podio app.
2. Click the **wrench (⚙)** icon → **Developer**.
3. Scroll down to the **Fields** section.
4. Each field lists its **External ID** (e.g. `title`, `contact-email`, `deal-type`).

> **Tip:** External IDs are usually lowercase with hyphens. The Podio UI sometimes calls them "Field ID" or "External Field ID".

---

### Supported Field Types

Select the correct type for each field in the **Field Type** dropdown.

| Type | Elementor value format | Podio value sent |
|------|------------------------|-----------------|
| **Text** | Any string | Plain string |
| **Number** | Numeric string | Plain string (Podio casts it) |
| **Email** | Email address | `[{"type":"other","value":"..."}]` |
| **Phone** | Phone number | `[{"type":"mobile","value":"..."}]` |
| **Date** | Any parseable date string | `{"start":"YYYY-MM-DD HH:MM:SS","end":"..."}` |
| **Category** | Must match a configured label | Podio option ID (integer) |

---

### Category Field Mapping

Category fields (Podio dropdowns, radio buttons, checkboxes) require mapping submitted text labels to Podio's internal integer option IDs.

**To find option IDs:**
1. Go to your Podio app → wrench → Developer.
2. Find the category field and expand it — each option has a numeric ID.

**To configure in the plugin:**
1. Set the field type to **Category (dropdown / radio)**.
2. A sub-panel appears: **Map each submitted label to its Podio Option ID**.
3. Click **+ Add Option** for each possible value.
4. Enter the label exactly as Elementor submits it (e.g. `Assignment`) and the matching Podio option ID (e.g. `1`).

**Example:**

| Submitted label | Podio Option ID |
|----------------|----------------|
| Assignment | 1 |
| Double Close | 2 |
| Novation | 3 |
| Creative Finance | 4 |

If a submitted value has no matching option, the field is skipped and a warning is logged.

---

### Elementor Field ID vs. Field Label

The **Elementor Field ID** is the custom ID you set per field, **not** the label shown to the user.

To set it in Elementor:
1. Click the field inside the Form widget.
2. Go to the **Advanced** tab in the left panel.
3. Set the **ID** field (e.g. `email`, `phone`, `deal-type`).

The plugin automatically strips the `form-field-` prefix, so entering `email` matches both `email` and `form-field-email` from the submission.

---

## Debug Logging

Navigate to **Podio Integration → Debug Logs**.

Enable **Debug Mode** under Settings → Podio Integration first.

### What is logged

- Every form submission hook firing
- Form name matched (or skipped)
- Each field mapped (Elementor ID → Podio External ID, type)
- The full JSON payload sent to Podio
- The HTTP response code and body from Podio
- Token refresh events
- Any warnings (unknown category values, unparseable dates, missing fields)
- SUCCESS/ERROR result of the item creation

### Log controls

| Button | Action |
|--------|--------|
| **All / Errors / Warnings / Success** | Filter visible lines |
| **Copy** | Copy log text to clipboard |
| **Wrap** | Toggle line wrapping |
| **Download** | Save as `.txt` file with today's date |
| **Clear All** | Delete all stored log entries |

Up to 200 entries are kept. Older entries are rotated out automatically.

---

## How Form Submission Works

```
Elementor form submitted
        ↓
elementor_pro/forms/new_record hook fires
        ↓
Plugin reads the form name
        ↓
Searches epod_form_mappings for a matching entry
        ↓
No match → return (other forms unaffected)
        ↓
Match found → reads App ID + field mappings
        ↓
Builds Podio field payload dynamically
(converts email/phone/date/category to correct format)
        ↓
Schedules submission via WordPress shutdown hook
(non-blocking — form success message shows immediately)
        ↓
POST /item/app/{app_id}/ → Podio API
        ↓
Logs result (SUCCESS / ERROR)
        ↓
On 401 → schedules token refresh
```

The submission is async (via the `shutdown` hook) so it never delays the form response shown to the visitor.

---

## File Structure

```
elementor-podio-integration/
├── elementor-podio-integration.php   Bootstrap — defines constants, loads files, runs init
├── includes/
│   ├── class-logger.php              Debug log read/write/clear
│   ├── class-auth.php                Podio OAuth2 (authenticate, refresh, revoke, get token)
│   ├── class-api.php                 Podio REST API requests
│   ├── class-form-handler.php        Elementor hook → field mapping → Podio submission
│   └── admin/
│       ├── class-settings-page.php   Settings → Podio Integration (credentials)
│       └── class-admin-page.php      Top-level Podio Integration menu (mappings, auth, logs)
└── README.md
```

---

## Troubleshooting

### "No mapping configured for form" in logs
The form name in the mapping doesn't match the name set in Elementor. Check for extra spaces or capitalisation differences. The match is case-sensitive.

### Fields are skipped ("not in submission")
The Elementor Field ID you entered doesn't match what Elementor is sending. Enable debug mode and check the line `Submitted field keys:` in the logs to see what keys the plugin actually receives.

### Category field not mapping
A submitted value has no matching label in the category options. The warning `No option_id mapped for category value '...'` will appear in the logs. Add the missing label–ID pair in the Form Mappings page.

### Date field not mapping
The date string from Elementor could not be parsed by PHP's `strtotime()`. Check the format Elementor sends (visible in the `Submitted field keys` log line) and ensure it's a standard format like `YYYY-MM-DD` or `DD/MM/YYYY`.

### "Not authenticated with Podio" in logs
Go to **Podio Integration → Authentication** and re-authenticate. This usually means the token expired and there was no refresh token, or the refresh failed.

### HTTP 401 after a working setup
The access token expired. The plugin will attempt a background refresh on the next submission. You can also manually refresh on the Authentication page.

### HTTP 422 from Podio
The field payload is malformed. Check the `Payload:` line in the logs and compare the External IDs to what Podio shows in its Developer panel.

---

## Changelog

### 2.0 — Current
- **Restructured** into multiple files for maintainability (classes: Logger, Auth, API, FormHandler, AdminPage, SettingsPage)
- **Multi-form support** — configure as many Elementor forms as needed, each mapping to its own Podio app
- **Dynamic field mapping** — add, remove, and reorder field mappings from the admin UI with no code changes
- **New top-level admin menu** — Form Mappings, Authentication, and Debug Logs as separate sub-pages
- **Collapse / expand** form mapping blocks in the UI
- **Live form name preview** in block header as you type
- **Test Connection** button on the Authentication page (AJAX, non-destructive)
- **Transient-based notices** — success/error messages survive POST→redirect without losing context
- **Improved debug log** — per-type colour coding, filter buttons, download, wrap toggle
- **All POST actions** go through `admin-post.php` with proper nonces and redirects
- Category field type now shows option mapping sub-table inline
- Auth class returns structured error strings instead of using `add_settings_error`

### 1.4
- Solved datetime formatting issue
- Working perfectly with Podio date fields

### 1.3.1
- Changed authentication method to user credentials (password grant)
- Podio API now responding correctly

### 1.2
- Updated field map

### 1.1
- Fixed token expiry issue
- Fixed incorrect field mapping

### 1.0
- Initial release
