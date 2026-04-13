---
id: s3-config-for-messages
title: Set Up S3 Storage for Messages
slug: /configuration/messaging/s3-config-for-messages
description: Configure Amazon S3 or S3-compatible storage for MMS media in FS PBX.
sidebar_position: 99
---

# Set Up S3 Storage for Messages

FS PBX can store MMS media files in Amazon S3 or another S3-compatible storage provider.

The main goal is to keep message media off the PBX server while still allowing users to access attachments through the messaging interface.

After media storage is configured:

- inbound MMS attachments can be stored in cloud storage
- outbound MMS attachments can be stored in cloud storage
- message media remains accessible through FS PBX

---


## Storage settings

Storage settings use the category:

```text
S3 Storage
```

Below is a rundown of each setting.

| Setting                   | Description                                                                                                                                                |
| ------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `access_key`              | The access key for the AWS bucket or S3-compatible storage account.                                                                                        |
| `secret_key`              | The secret key for the AWS bucket or S3-compatible storage account.                                                                                        |
| `bucket_name`             | The bucket where message media files will be stored.                                                                                                       |
| `region`                  | The storage region. For AWS, this should match the bucket region. For compatible providers, use the region value required by that provider.                |
| `endpoint`                | Leave blank for AWS. For other S3-compatible providers, enter the provider’s S3 endpoint URL.                                                              |
| `use_path_style_endpoint` | Enables path-style S3 requests for providers that require it. Usually not needed for AWS.                                                                  |
| `signature_version`       | Signature version used when authenticating requests. In most cases this can be left blank unless your provider specifically requires a value such as `v4`. |

---

## AWS example

For AWS, the `endpoint` field should usually be left blank.

| Setting                   | Example               |
| ------------------------- | --------------------- |
| `access_key`              | `AWS_ACCESS_KEY`      |
| `secret_key`              | `AWS_SECRET_KEY`      |
| `bucket_name`             | `fspbx-message-media` |
| `region`                  | `us-west-2`           |
| `endpoint`                | (blank)               |

---

## Generic S3-compatible example

For a non-AWS provider, enter the provider’s S3-compatible endpoint URL.

| Setting                   | Example                       |
| ------------------------- | ----------------------------- |
| `access_key`              | `PROVIDER_ACCESS_KEY`         |
| `secret_key`              | `PROVIDER_SECRET_KEY`         |
| `bucket_name`             | `message-media`               |
| `region`                  | `us-east-1`                   |
| `endpoint`                | `https://objects.example.com` |
| `use_path_style_endpoint` | `true`                        |
| `signature_version`       | `v4`                          |

---

## What happens after setup

After storage is configured and MMS media is processed:

* media files are uploaded to the configured S3 bucket or S3-compatible provider
* FS PBX keeps track of the stored object location
* users can continue accessing message attachments through the messaging interface

This allows you to offload media storage from the PBX server without losing access to MMS attachments.

---

## Summary

This setup helps by:

* keeping message media out of local server storage
* supporting Amazon S3 and compatible providers
* keeping MMS attachments accessible from FS PBX

