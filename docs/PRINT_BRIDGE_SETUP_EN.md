# Windows Print Bridge — Setup & Integration Guide (English)

Version: 1.0

This document describes how to install, configure, and operate the Windows Print Bridge application that integrates with the cafe web application to automatically print barista receipts on thermal printers.

---

1. Project Overview

- What is the Print Bridge?
  - The Print Bridge is a small Windows application (service/daemon or foreground app) that polls the website for pending print jobs and sends them to a local thermal printer.

- Why it is required
  - The website must not be connected directly to physical printers for security and reliability reasons. The Print Bridge runs next to the printer, has local printer drivers installed and performs the actual printing.

- How it communicates with the website
  - HTTP(S) API using a device token for authentication. The bridge requests the next pending job, prints the provided plaintext payload, and confirms completion (or failure) back to the server.

- High level architecture
  - Website (manages orders & print job queue) <--> Print Bridge (polls API, prints locally) <--> Thermal Printer

---

2. Installation Guide

Minimum Recommendations
- Windows 10 or later (Windows Server is also suitable)
- .NET 6.0 / .NET 7.0 runtime (if app built with .NET). If a native Win32 app is used, require matching runtime.
- Printer drivers installed for the target thermal printer.

Install Steps (example for a .NET app):
1. Install appropriate .NET runtime (if needed): https://dotnet.microsoft.com/download
2. Copy the Print Bridge application files to a folder, e.g. `C:\PrintBridge`.
3. Create a configuration file (JSON) next to the executable, or provide command-line args, with server URL and device token.
4. Run the app once interactively to verify it can reach the server and list printers.
5. Optionally register it as a Windows service or add to Startup to run at login.

Launching
- Run using the provided EXE, or register as a service using sc.exe or NSSM.

---

3. Configuration

Basic settings the Bridge must have:
- Server URL: Base URL of the website (example: https://example-cafe.local/api/print_bridge.php)
- Device Token: secret token (string) that authenticates the Bridge to the website
- Polling Interval: how often (seconds) to check for new jobs (recommended 2–10 seconds)
- Default Printer: local Windows printer name to use if not selecting per-job
- Test Print: a test function to validate printing without consuming a job
- Start With Windows: optional configuration to run at startup or register as service

Recommended config format (JSON example)
{
  "server": "https://my-cafe.local/api/print_bridge.php",
  "token": "YOUR_DEVICE_TOKEN",
  "pollIntervalSec": 5,
  "printer": "EPSON TM-T20",
  "autoStart": true,
  "logPath": "C:\\PrintBridge\\logs"
}

Notes:
- The server URL should be the full path to the PHP endpoint (api/print_bridge.php). The app will append action and use GET/POST as needed.
- Keep the device token secret and store it securely (Windows credential manager is recommended for production).

---

4. Printer Setup

Selecting a printer
- The Bridge should list installed printers and allow selection. Use the printer that has the thermal paper loaded.

Supported printer types
- 58mm and 80mm thermal printers tested (ESC/POS compatible or Windows-driver supported printers).
- USB, LAN, or WiFi printers are supported if Windows has a printer driver and the printer is present in the Devices & Printers list.

Tips
- For ESC/POS printers, printing plaintext works in many drivers; for better control you may need to send ESC/POS commands if the printer supports it.
- Test print from the app before enabling automatic printing.

---

5. Website Integration (How jobs are created and consumed)

- When an admin marks an order status to `approved`, the website creates a single print job in the `print_jobs` table and sets status `pending`.
- The Print Bridge polls the API and claims the next pending job (status -> processing) to avoid duplicates.
- The Bridge reads `payload` (UTF-8 plaintext) and prints it on the configured thermal printer.
- After a successful print, the Bridge confirms the job by calling the API to mark the job `completed`.
- In case of printing failure, the Bridge reports `failed` and includes an error message; retry_count is incremented so failed jobs can be inspected and reprocessed.

---

6. API Documentation

All endpoints are provided by the website at `api/print_bridge.php` and require the device token for authentication.

Authentication
- Provide the device token either in HTTP header `X-Device-Token: <token>` or as query param `?token=<token>`.
- The server checks the token against the `settings` table value `print_bridge_token`.

Endpoints

1) Claim next pending job
- URL: GET /api/print_bridge.php?action=next
- Method: GET
- Auth: X-Device-Token or ?token
- Purpose: Atomically claim the next pending job and return its payload for printing.
- Response (200):
{
  "success": true,
  "job": {
    "id": 123,
    "order_id": 987,
    "order_number": "DNJ-20260804-055C5D",
    "job_type": "preparation",
    "print_text": "...plain UTF-8 text...",
    "payload": {
      "order_id": 987,
      "order_number": "DNJ-20260804-055C5D",
      "job_type": "preparation",
      "print_text": "...plain UTF-8 text...",
      ...
    }
  }
}
- Response when no job: { "success": false, "message": "No pending jobs" }

Example curl:
curl -H "X-Device-Token: YOUR_TOKEN" "https://your-site.local/api/print_bridge.php?action=next"

2) Confirm print result
- URL: POST /api/print_bridge.php?action=confirm
- Method: POST (JSON body recommended)
- Auth: X-Device-Token or ?token
- Body params:
  - job_id (int) — the print job id returned earlier
- Successful response: { "success": true }
- Example body:
{ "job_id": 123 }

Example curl:
curl -X POST -H "Content-Type: application/json" -H "X-Device-Token: YOUR_TOKEN" -d '{"job_id":123}' "https://your-site.local/api/print_bridge.php?action=confirm"

3) Report failed print
- URL: POST /api/print_bridge.php?action=failed
- Method: POST (JSON body recommended)
- Auth: X-Device-Token or ?token
- Body params:
  - job_id (int) — the print job id returned earlier
  - error (string) — short error message explaining the failure
- Successful response: { "success": true }
- Example body:
{ "job_id": 123, "error": "Printer offline" }

Error responses
- 401 Unauthorized — missing or invalid token
- 400 Bad Request — malformed request body or missing parameters
- 500 Server error — transient problem; check server logs

---

7. Troubleshooting

Common issues and suggested fixes:
- Printer not detected
  - Ensure Windows detects the printer and drivers are installed. Test printing from Notepad or Devices & Printers.
- Printer offline / paper out
  - Check printer status indicators; verify paper roll and connectivity.
- Invalid device token
  - Verify `print_bridge_token` stored in the website settings (database `settings` table). Ensure the Bridge uses the exact value.
- Network issues
  - Verify the Bridge can reach the server URL. If the site is HTTPS with a self-signed certificate, install the certificate on the client or use a trusted certificate.
- Print job not received
  - Verify the order was approved in the admin panel; check the `print_jobs` table for a `pending` job.
- Duplicate printing prevention
  - The server marks a job as `processing` when claimed (SELECT ... FOR UPDATE). If duplicate prints occur, verify the Bridge is confirming completed jobs.
- Failed print recovery
  - Failed jobs remain in DB with status 'failed' and retry_count > 0. Admin can inspect and requeue (not part of core server: a requeue admin action can be added later).

Logs
- Keep Bridge logs enabled. Logs should capture HTTP requests/responses, print output status and local printer errors.

---

8. Security

- Device Token usage
  - A single secret token is used to authenticate the Bridge. Keep the token secret and rotate periodically.
  - Store the token in the website `settings` table under `print_bridge_token`.
- Transport security
  - Use HTTPS for the server to prevent token interception.
- Recommended deployment practices
  - Run Bridge on a secure local network.
  - Limit server exposure; use firewalls and SSL.
  - Rotate tokens if a device is compromised.

---

9. Updating

- Server-side schema changes
  - The Bridge relies on stable fields in the job payload (id, payload, paper, order_number). Avoid changing these field names without updating the Bridge.
- Application updates
  - For the Bridge, release new versions and update the executable. For services, stop the service, replace the binary and restart.
- Backwards compatibility
  - When adding features, keep the existing response keys to avoid breaking older Bridge versions.

---

10. FAQ

Q: What language is the print payload in?
A: UTF-8 Persian (Farsi), plain text. Designed for RTL printing on thermal printers.

Q: Does the payload include prices?
A: No. The payload intentionally excludes prices, totals, discounts, coupons and payment details — it is only for barista preparation.

Q: How do I change printer width usage (58mm vs 80mm)?
A: The job includes a `paper` field ('58' or '80'). The Bridge can adjust font size or margins based on that value.

Q: What if a job fails repeatedly?
A: Failed jobs are marked with `status='failed'` and retry_count increments. An admin can inspect the DB and decide to requeue or manual print.

---

Appendix: How to set the device token on the server

- Use the site settings interface if available. Otherwise, run a SQL update:

UPDATE settings SET setting_value = 'YOUR_TOKEN' WHERE setting_key = 'print_bridge_token';

Or via PHP using the Setting class in a one-off script:

<?php
require_once __DIR__ . "/classes/Setting.php";
Setting::set('print_bridge_token', 'YOUR_TOKEN');

End of document.



