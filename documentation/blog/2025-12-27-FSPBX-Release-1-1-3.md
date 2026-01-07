# 📡 FS PBX API v1 Availability & Overview

FS PBX provides a modern, RESTful API (v1) that enables programmatic access to core PBX resources such as domains, extensions, and voicemails. This API is designed for developers, integrators, and service providers who want to automate provisioning, integrate external systems, or build custom dashboards on top of FS PBX.

Official documentation:  
https://www.fspbx.com/docs/api/v1/fs-pbx-api/

---

## 🚀 API Availability

- **Status:** Available and documented  
- **Version:** v1  
- **Protocol:** HTTPS only  
- **Format:** JSON (request & response)  
- **Authentication:** Bearer token (API key)

The API is part of the standard FS PBX platform and is intended for production use. All endpoints are exposed securely over HTTPS; non-TLS requests are rejected.

---

## 🔗 Base URL

All API endpoints are prefixed with:

```
https://<your-fspbx-host>/api/v1
```

Example:

```
https://pbx.example.com/api/v1/domains
```

---

## 🔐 Authentication

FS PBX API v1 uses **Bearer token authentication**.

- API tokens are generated within the FS PBX web UI (Users section).
- Each request must include an `Authorization` header.

Example:

```
Authorization: Bearer YOUR_API_TOKEN
```

> ⚠️ API tokens should be treated like passwords and stored securely.

---

## 🛠 API Design & Behavior

The FS PBX API follows standard REST conventions:

- **HTTP verbs:** `GET`, `POST`, `PUT`, `DELETE`
- **Predictable, resource-based URLs**
- **JSON responses**
- **HTTP status codes** for success and errors

### Pagination

List endpoints support pagination to efficiently handle large datasets.

Common parameters include:

- `limit` – number of records to return
- `starting_after` – cursor-based pagination token

This makes the API suitable for automation, reporting, and data synchronization tasks.

---

## 📦 Common Resource Types

While the full endpoint list is defined in the official docs, the API is structured around common PBX resources such as:

- **Domains** – multi-tenant PBX environments
- **Extensions** – SIP users and endpoints
- **Voicemails** – voicemail boxes and messages

Each resource is exposed using consistent REST patterns for listing, querying, and management.

---

## 💡 Typical Use Cases

The FS PBX API v1 enables a wide range of integrations, including:

- Automated provisioning of domains and extensions
- Integration with billing, CRM, or OSS/BSS systems
- Custom reporting and analytics dashboards
- MSP and multi-tenant PBX management
- Workflow automation and DevOps-style PBX operations