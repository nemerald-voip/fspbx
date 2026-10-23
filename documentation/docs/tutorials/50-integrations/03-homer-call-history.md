---
sidebar_position: 18
title: HOMER SIP Traces in Call History
---

# HOMER SIP Traces in Call History

Connect FS PBX to HOMER 11 to inspect SIP messages and download a Wireshark-compatible PCAP from Call History.

## Connect HOMER

1. Sign in as a superadmin and open **System Settings → SIP Capture → Remote HOMER API**.
2. Enter the HOMER HTTPS base URL, without `/api/v4`. The certificate must be trusted by the PBX. Redirects are not followed.
3. Select **Auth-Token** for a HOMER API token, or **Bearer JWT** for a session token. HOMER must have API token access enabled for Auth-Token authentication. JWTs expire according to HOMER's configuration.
4. Enter the token, select **Enable Call History integration**, then use **Test Connection** and **Save**. Testing does not save the form.

Saved tokens are encrypted and never shown again. Leave the token blank to retain it; changing the URL or authentication type requires a new token. On redundant installations sharing these settings, every application node needs the same Laravel encryption key and connectivity to HOMER.

These API settings are independent of the HEP collector settings above them. Saving the API connection does not change FreeSWITCH capture settings or restart profiles.

## View or download a call

Open the call using the magnifying glass in **Call History**, then select **SIP Trace** beside the call heading.

- The dedicated workspace loads the SIP ladder automatically. Select a message to read its headers and body. Adjust **Flow width** or **Hide message** to give the ladder more room. Endpoint columns stay consistent across pages and scroll horizontally when necessary.
- **Expand** opens the selected message over the ladder. **Close** or Escape returns to the flow. On narrow screens, selecting a message opens this overlay directly. Capture timestamps are displayed in UTC, with elapsed seconds beside each message.
- **Download PCAP** downloads the captured SIP signaling for Wireshark. RTP audio is not included.
- **Download TXT** downloads every returned message, including timestamps, endpoints, Call-IDs, and raw SIP text. Both exports include all captured correlated legs within the lookup window, regardless of the selected message or page. AI analysis is not available or displayed.

HOMER resolves related legs using its configured correlation script. The download resolves those legs before exporting them. Searches use the CDR's SIP Call-ID and its start/end times with a one-minute margin. Messages outside that window, including later parts of a transferred call, may require opening the corresponding CDR. Missing or expired captures cannot be recovered by this integration.

## Access and limits

Configuration requires superadmin membership and `homer_api_manage`. Viewing and downloading require superadmin membership, `xml_cdr_view`, `xml_cdr_view_all_records`, and `xml_cdr_pcap`. The selected CDR is also account-scoped unless the user has `xml_cdr_domain`.

Assigning the PCAP permission to an account administrator does not grant access. Correlation headers such as `X-CID` can be supplied by callers and cannot establish account ownership; a correlated trace may span accounts.

Requests have time, response-size, and rate limits. Traces with 10,000 or more messages are rejected rather than knowingly exporting a truncated trace; exports support at most 50 related Call-IDs. SIP contents are displayed as text, and trace responses are marked private and non-cacheable.

## Deployment

Run the `2026_10_19_000001_create_homer_api_settings_table.php` migration and the normal database seeder to install the permissions. This is the feature's only migration and uses a UUID primary key. Rebuild frontend assets and refresh a cached route list. Sign out and back in to refresh session permissions. The connection starts disabled and requires configuration before the SIP Trace button appears.

Before rollout, check a recent inbound, outbound, and transferred call against HOMER, including the viewer and both PCAP and TXT downloads.
