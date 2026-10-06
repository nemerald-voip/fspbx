
# Archive Call Recordings to S3 Storage

This feature lets FS PBX move call recordings from the server to **Amazon S3** or another **S3-compatible storage provider**.

The main goal is to **reduce local disk usage on your PBX servers**. During the archive process, recordings are also converted from **WAV to MP3**, which helps reduce **cloud storage usage** as well.

After a recording is successfully archived:

* it is **removed from the server**
* it can still be **played and downloaded from the Call History page**

## Default settings vs domain-specific settings

FS PBX supports two ways to configure storage:

### Default settings

Default settings are system-wide storage settings used by all tenants unless a tenant has its own override.

Use default settings when:

* all tenants should archive to the same cloud storage account
* you want the easiest setup
* you want recordings organized by tenant domain inside one shared bucket

When default settings are used, recordings are stored in paths that include the tenant’s domain name.

Example:

```text id="0rt7sa"
customer.example.com/2026/03/03/143011_inbound_12135551212_1001.mp3
```

### Domain-specific settings

Domain-specific settings let an individual tenant override the default storage configuration.

Use domain-specific settings when:

* a tenant wants to store recordings in **their own AWS account**
* a tenant wants to use **their own S3-compatible provider**
* different tenants need different storage destinations

When domain-specific settings are used, recordings are stored under a shared `recordings/` path.

Example:

```text id="ajab3z"
recordings/2026/03/03/143011_outbound_1001_18005551212.mp3
```

This is especially useful in multi-tenant environments where each tenant may want full control over their own cloud storage.

## Important note about setup

All **default storage settings are already created** in the system.

You only need to manually add **domain-level overrides** if a tenant should use their own storage settings instead of the system defaults.

# Storage settings

Storage settings use the category:

```text id="df06wu"
s3_storage
```

Below is a rundown of each setting.

| Setting                     | Description                                                                                                                                                |
| --------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `access_key`                | The access key for the AWS bucket or S3-compatible storage account.                                                                                        |
| `secret_key`                | The secret key for the AWS bucket or S3-compatible storage account.                                                                                        |
| `bucket_name`               | The bucket where archived recordings will be stored.                                                                                                       |
| `region`                    | The storage region. For AWS, this should match the bucket region. For compatible providers, use the region value required by that provider.                |
| `endpoint`                  | Leave blank for AWS. For other S3-compatible providers, enter the provider’s S3 endpoint URL.                                                              |
| `use_path_style_endpoint`   | Enables path-style S3 requests for providers that require it. Usually not needed for AWS.                                                                  |
| `signature_version`         | Signature version used when authenticating requests. In most cases this can be left blank unless your provider specifically requires a value such as `v4`. |
| `upload_notification_email` | Email address that will receive the upload report after the archive job runs.                                                                              |

## AWS example

For AWS, the `endpoint` field should usually be left blank.

| Setting                     | Example              |
| --------------------------- | -------------------- |
| `access_key`                | `AWS_ACCESS_KEY`     |
| `secret_key`                | `AWS_SECRET_KEY`     |
| `bucket_name`               | `fspbx-recordings`   |
| `region`                    | `us-west-2`          |
| `endpoint`                  | *(blank)*            |
| `use_path_style_endpoint`   | `false`              |
| `signature_version`         | `v4`                 |
| `upload_notification_email` | `alerts@example.com` |

## Generic S3-compatible example

For a non-AWS provider, enter the provider’s S3-compatible endpoint URL.

| Setting                     | Example                       |
| --------------------------- | ----------------------------- |
| `access_key`                | `PROVIDER_ACCESS_KEY`         |
| `secret_key`                | `PROVIDER_SECRET_KEY`         |
| `bucket_name`               | `recordings`                  |
| `region`                    | `us-east-1`                   |
| `endpoint`                  | `https://objects.example.com` |
| `use_path_style_endpoint`   | `true`                        |
| `signature_version`         | `v4`                          |
| `upload_notification_email` | `alerts@example.com`          |

# Scheduled job settings

Scheduled job settings use the category:

```text id="f8xp5k"
scheduled_jobs
```

## Upload limit

This setting controls how many pending recordings can be archived during one run.

| Setting           | Description                                                       |
| ----------------- | ----------------------------------------------------------------- |
| `s3_upload_limit` | Maximum number of recordings to process during one scheduled run. |

Example:

| Setting           | Example |
| ----------------- | ------- |
| `s3_upload_limit` | `2000`  |

## Enable uploads

Open **Advanced > Default Settings**, find category `scheduled_jobs`, and enable the `s3_upload_calls` row with value `true`.

Keep the row enabled and set its value to `false` to stop scheduled uploads on every server.

Storage credentials alone do not enable uploads. Account-specific storage settings still determine where each account's recordings are uploaded.

## Choose the upload server

Use **Scheduled job server** under **System Settings > Scheduled Jobs** to choose which server runs uploads. See [Scheduled Job Server Setup](/docs/configuration/scheduled-jobs/server-ownership/) for setup instructions.

| Installation state | Which server uploads? |
| --- | --- |
| Standalone installation | The standalone server is selected automatically. Enable uploads with `s3_upload_calls`. |
| Redundant installation | Approve the servers and select the Scheduled job server before enabling uploads. Only the selected server runs scheduled uploads. |
| Ownership transfer in progress | The current recording may finish. No new recording starts until the transfer completes. |

These rules apply to scheduled runs. Set the daily upload time with `s3_upload_calls_time` (default `01:00`), in `scheduled_jobs_timezone` or the application's timezone. Recordings become eligible six hours after their start time.

On redundant installations, ensure the selected server has the same storage settings and access to synchronized recording files. A recording that has not arrived locally is deferred; its Call History reference is preserved.

Use the Scheduled job server control for later transfers. If the old server is unreachable, follow its forced-takeover procedure before starting uploads on a replacement.

## Run uploads manually

Run uploads immediately on the server where you execute this command, regardless of upload enablement or server selection:

```bash
php artisan fs:upload-call-recordings-to-s3-storage
```

This is an operator override and does not participate in coordinated ownership or draining. Run it on only one server at a time, outside a scheduled upload or ownership transfer. Storage configuration, recording age, upload limit, verification, and cleanup safeguards still apply.

To run manually with the same enablement and selection checks as the scheduler, add `--scheduled`:

```bash
php artisan fs:upload-call-recordings-to-s3-storage --scheduled
```

# What happens after upload

After a recording is successfully uploaded:

* the server updates the call record to point to the archived file
* the local file is removed from the server
* the recording remains available from the **Call History** page for playback and download

Failed uploads or verification leave the local recording intact for a later retry. Interrupted local cleanup is retried when uploads next run on that server. Cleanup records are stored locally in `storage/app/s3-upload-cleanup`; keep that directory writable by the scheduler user and do not remove it during updates.

This allows you to keep local storage usage low without losing access to archived recordings.

# Summary

Use **default settings** when all tenants should archive to the same storage account.

Use **domain-specific settings** when a tenant should archive to their own AWS bucket or their own S3-compatible provider.

The archive job helps by:

* reducing local storage usage on PBX servers
* converting recordings to MP3 to reduce cloud storage usage
* keeping archived recordings accessible from the Call History page
