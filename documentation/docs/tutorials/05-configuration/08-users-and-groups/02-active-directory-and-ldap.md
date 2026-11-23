---
id: active-directory-and-ldap
title: Microsoft Active Directory and LDAP
slug: /configuration/users-and-groups/active-directory-and-ldap/
sidebar_position: 2
---

# Microsoft Active Directory and LDAP

Directory Services connects FS PBX to Microsoft Active Directory or another LDAP directory. It imports users and groups into the selected account, lets imported users sign in with their directory passwords, and can assign FS PBX roles from directory group membership.

Open **Account Settings → Directory Services** to manage connections. This guide starts with Microsoft Active Directory, then covers other LDAP servers and a [public test directory](#try-a-public-ldap-test-directory).

Directory authentication applies to FS PBX web sign-in. Phones continue to use their extension credentials.

## Before you begin

Have the following ready:

- An FS PBX administrator account with permission to manage Directory Services and group mappings.
- A directory service account that can read the users, groups, and attributes you want to import. FS PBX does not need permission to change objects in the directory.
- A reachable directory server hostname, its LDAP port, and the search paths for your users and groups.
- An initial set of users to import, preferably a dedicated organizational unit (OU) or group.

Keep a separate local superadmin account outside the import scope. Synchronization can link an existing FS PBX user by matching email or account username, after which directory-managed identity fields are maintained by synchronization.

For redundant installations, first select the [scheduled job server](../12-scheduled-jobs/01-server-ownership.md) that will run synchronization. Single-server installations do not require an owner selection.

## Connect Microsoft Active Directory

The built-in attribute defaults are intended for Microsoft Active Directory Domain Services. Use a domain controller that serves the directory you want to import.

### 1. Prepare the secure connection

Use **LDAPS** on port **636**, or **StartTLS** on port **389** when supported by your directory. The PBX must be able to resolve and reach the domain controller.

For LDAPS, the domain controller needs a server certificate matching its hostname, and the PBX must trust the issuing certificate authority. Use the hostname covered by that certificate in FS PBX. Your AD administrator can follow [Microsoft's LDAPS configuration guide](https://learn.microsoft.com/en-us/troubleshoot/windows-server/active-directory/enable-ldap-over-ssl-3rd-certification-authority).

### 2. Add the directory

Switch to the FS PBX account that should receive the users. Open **Account Settings → Directory Services → Add Directory**.

On the **Directory** tab, enter your own values using this example:

| Setting | Example or starting choice |
| --- | --- |
| **Directory Type** | Microsoft Active Directory |
| **Directory Name** | Company AD |
| **Enable Directory** | No while preparing the configuration |
| **Synchronize** | 1 Hour |
| **Priority** | 100 |
| **Secure Connection Type** | LDAPS |
| **Port** | `636` |
| **Host(s)** | `dc01.example.com` |
| **Username** | `ldap-sync@example.com` |
| **Password** | The service account password |
| **Domain** | `example.com` |
| **Base DN** | `DC=example,DC=com` |

Enter hostnames without `ldap://` or `ldaps://`. You can list multiple domain controllers, separated by commas or spaces; they are tried in order.

For **Username**, use the service account's full sign-in name or full DN, such as `CN=LDAP Sync,OU=Service Accounts,DC=example,DC=com`. A bare name such as `ldap-sync` is combined with **Domain** to form `ldap-sync@example.com`.

### 3. Choose which users and groups to import

On **User Configuration**, set **User DN** to the location of the intended users. On **Group Configuration**, set **Group DN** to the location of their groups.

For an AD directory containing these OUs:

| Setting | Example |
| --- | --- |
| **Base DN** | `DC=example,DC=com` |
| **User DN** | `OU=People` |
| **Group DN** | `OU=Groups` |

FS PBX searches below `OU=People,DC=example,DC=com` for users and below `OU=Groups,DC=example,DC=com` for groups, including their subcontainers. Replace the example paths with paths that exist in your directory. Ask your AD administrator for each container's exact distinguished name; a container named Users may use `CN=Users` rather than `OU=Users`.

The default **User object filter** is:

```text
(&(objectCategory=Person)(sAMAccountName=*))
```

To import only direct members of a specific AD group, include that group's full DN. For example:

```text
(&(objectCategory=Person)(sAMAccountName=*)(memberOf=CN=FS-PBX-Users,OU=Groups,DC=example,DC=com))
```

Use a group that exists and contains your intended users directly. This example does not include membership inherited through nested groups.

The default **Group object filter** is `(objectCategory=Group)`. Make sure the group search includes the groups you intend to map to FS PBX roles.

**Group mappings control permissions, not the user import scope.** Use **User DN** and the user filter to select who is imported.

### 4. Review attributes and extension options

The following defaults normally suit AD:

| Tab | Setting | AD attribute or value |
| --- | --- | --- |
| **Operational Settings** | **Common Name attribute** | `cn` |
| **Operational Settings** | **Unique identifier attribute** | `objectGUID` |
| **User Configuration** | **User object class** | `user` |
| **User Configuration** | **User name attribute** | `sAMAccountName` |
| **User Configuration** | **User first name attribute** | `givenName` |
| **User Configuration** | **User last name attribute** | `sn` |
| **User Configuration** | **User display name attribute** | `displayName` |
| **User Configuration** | **User group attribute** | `memberOf` |
| **User Configuration** | **User email attribute** | `mail` |
| **User Configuration** | **User extension Link attribute** | `ipPhone` |
| **Group Configuration** | **Group object class** | `group` |
| **Group Configuration** | **Group members attribute** | `member` |

Keep `objectGUID` as the AD unique identifier. It identifies the same object when its name or location changes. See Microsoft's [objectGUID reference](https://learn.microsoft.com/en-us/windows/win32/adschema/a-objectguid).

FS PBX sign-in uses an email address. If AD supplies `mail`, ensure it contains the address each person should use to sign in. If you want to manage login emails in FS PBX instead, leave **User email attribute** blank and enter each user's email on the **Users** page after import.

For extension linking, the selected attribute should contain an extension number such as `1001`. On **Operational Settings**, choose:

| **Create Missing Extensions** | Behavior |
| --- | --- |
| **Don't Create** | Link a matching existing extension in the account; do not create a missing one. |
| **Create with Defaults** | Create a missing extension with default settings and voicemail, then link it to the user. |

Start with **Don't Create**. Leave **User extension Link attribute** blank if you do not want directory-based extension linking. A different extension assigned manually is preserved.

### 5. Save, test, and synchronize

1. Click **Save**. Saving also tests the connection.
2. Reopen the directory and use **Test Connection** to repeat the check when needed. It checks the service account credentials and both user and group search locations without importing records.
3. Review the search scope, filters, and attributes. Turn on **Enable Directory**, choose the synchronization interval, and save.
4. Reopen the directory and click **Sync Now** to request an import.
5. Click **Refresh** in Directory Services. Check **Last Sync** and the imported user and group counts, then review the users on the **Users** page.

Enabling the directory also permits scheduled imports. A successful connection test does not confirm that the filters select the intended records or that the attribute mappings are correct; review the synchronization result too.

### 6. Assign access through group mappings

Importing a user does not automatically grant an FS PBX role.

1. Run synchronization once so directory groups appear.
2. Open the directory's **Operational Settings**. Set **Manage groups locally** to **No** and save.
3. Reopen it and select **Group Mappings**.
4. Beside each intended directory group, select the FS PBX local role its members should receive. Leave unrelated groups at **No local role**.
5. Use **View members** to check the imported membership, then click **Save Group Mappings**.

Saving applies the mappings to users already imported. Later synchronizations update the roles assigned by those mappings. Roles assigned separately in FS PBX are preserved.

FS PBX uses direct group membership and can recognize an AD user's primary group when that group is included in the group search. It does not recursively expand nested directory groups. Add users directly to the group you map when you need predictable role assignment.

If you prefer to assign roles individually on the **Users** page, set **Manage groups locally** to **Yes**. Directory group mappings are inactive in that mode.

### 7. Test a user's sign-in

Use a separate browser session so your administrator session stays open. Sign in with the user's **FS PBX email address** and their **AD password**. The user must be enabled and have at least one local role; the directory and FS PBX account must also be enabled.

Normal FS PBX verification requirements still apply. If email verification codes are enabled, use a mailbox the user can access.

## Shared and independent search DNs

### With a Base DN

Existing AD and LDAP connections can keep their current settings. When **Base DN** is filled in:

- A relative path such as `OU=People` is added before the Base DN.
- A complete path ending with that Base DN is used directly.
- A blank **User DN** or **Group DN** searches from the Base DN for that object type.

For example, with Base DN `DC=example,DC=com`, both `OU=People` and `OU=People,DC=example,DC=com` select the same user search location.

### Without a Base DN

Leave **Base DN** blank to use independent search locations. **Both User DN and Group DN are then required**, and each must contain its complete path. FS PBX uses those paths directly without adding a shared base.

The paths do not need a readable common parent and may belong to different naming contexts.

### Cloudron search paths

For Cloudron's separate user and group branches, use:

| Tab | Setting | Value |
| --- | --- | --- |
| **Directory** | **Base DN** | Leave blank |
| **User Configuration** | **User DN** | `ou=users,dc=cloudron` |
| **Group Configuration** | **Group DN** | `ou=groups,dc=cloudron` |

This avoids requiring `dc=cloudron` itself to be readable. These are search-path settings only; configure the credentials, object filters, and attributes for the Cloudron directory as well.

## Other LDAP directories

Choose **Generic LDAP** and enter your provider's settings. The initial attribute values are AD-oriented, so review them explicitly when connecting another LDAP schema.

Check these items in particular:

- **Unique identifier attribute:** choose a stable attribute present on both users and groups. This setting is shared by both object types. OpenLDAP directories may provide `entryUUID`.
- **User name attribute:** often `uid` instead of `sAMAccountName`.
- **Object classes and filters:** replace AD-specific values such as `user`, `group`, and `objectCategory` when the provider uses different ones.
- **Group members attribute:** use an attribute containing full user DNs, such as `member` or `uniqueMember`. Alternatively, users can supply full group DNs through their configured **User group attribute**. Bare usernames in `memberUid` are not equivalent to full DNs.
- **Email and names:** select attributes the provider supplies, or leave the email mapping blank to manage login emails locally.

AD's disabled-account status is recognized during synchronization. Other providers may use different account-status attributes; do not assume an AD-specific status mapping applies to every LDAP server.

## Try a public LDAP test directory

[Forum Systems provides a public, read-only LDAP test server](https://www.forumsys.com/2022/05/10/online-ldap-test-server/) with sample users and published passwords. The configuration below imports only `einstein` and `newton`, plus their **Scientists** group.

:::warning Use a disposable test PBX

These passwords are public. Use an isolated FS PBX test instance with dummy data, not a production PBX. Do not give the sample users administrative roles. The public service is operated by a third party and may be unavailable or change its sample data.

:::

### Connection settings

Create an empty test account in FS PBX, switch to it, and click **Add Directory**. On **Directory**, enter:

| Setting | Value |
| --- | --- |
| **Directory Type** | Generic LDAP |
| **Directory Name** | Forum Systems Test |
| **Enable Directory** | No until the remaining tabs are configured |
| **Synchronize** | 1 Hour |
| **Priority** | 100 |
| **Secure Connection Type** | None |
| **Port** | `389` |
| **Host(s)** | `ldap.forumsys.com` |
| **Username** | `cn=read-only-admin,dc=example,dc=com` |
| **Password** | `password` |
| **Domain** | `example.com` |
| **Base DN** | `dc=example,dc=com` |

This example uses the provider's public test credentials over plain LDAP. Use TLS for your own directory credentials.

### Attribute and search settings

Apply these values on the remaining tabs. Settings not listed can retain their defaults.

| Tab | Setting | Value |
| --- | --- | --- |
| **Operational Settings** | **Create Missing Extensions** | Don't Create |
| **Operational Settings** | **Manage groups locally** | No |
| **Operational Settings** | **Common Name attribute** | `cn` |
| **Operational Settings** | **Unique identifier attribute** | `entryUUID` |
| **User Configuration** | **User DN** | Leave blank |
| **User Configuration** | **User object class** | `inetOrgPerson` |
| **User Configuration** | **User object filter** | Use the filter below. |
| **User Configuration** | **User name attribute** | `uid` |
| **User Configuration** | **User first name attribute** | `cn` |
| **User Configuration** | **User last name attribute** | `sn` |
| **User Configuration** | **User display name attribute** | `cn` |
| **User Configuration** | **User group attribute** | `memberOf` |
| **User Configuration** | **User email attribute** | Leave blank |
| **User Configuration** | **User extension Link attribute** | Leave blank |
| **Group Configuration** | **Group DN** | Leave blank |
| **Group Configuration** | **Group object class** | `groupOfUniqueNames` |
| **Group Configuration** | **Group object filter** | `(ou=scientists)` |
| **Group Configuration** | **Group members attribute** | `uniqueMember` |

Enter this **User object filter** to select the two sample users:

```text
(|(uid=einstein)(uid=newton))
```

Both DN fields are blank because the users and groups are searched from the shared Base DN. Do not put `ou=scientists` in **User DN**: the sample user entries are directly beneath `dc=example,dc=com`.

The sample directory supplies group membership through `uniqueMember`; leaving the user-side mapping at `memberOf` is fine. The email mapping is intentionally blank so you can assign an email you control for FS PBX sign-in and verification codes.

### Import and sign in

1. Save and confirm **Test Connection** succeeds.
2. Turn on **Enable Directory**, save, and click **Sync Now**. Refresh until synchronization completes. Expect two imported users and one group with this example's filters.
3. Open **Group Mappings**, map **Scientists** to a non-administrative local role, and click **Save Group Mappings**. **View members** should show the two imported users.
4. On the **Users** page, edit `einstein` and enter a unique email address you control. Save it.
5. In a separate browser session, sign in with that email address and the public test password `password`. Complete any configured FS PBX verification step.
6. When finished, delete the test directory from the disposable PBX. Review the confirmation: deleting a directory also deletes the users it created in FS PBX.

This exercise checks generic LDAP connectivity, imports, group mapping, and sign-in. It does not validate your AD server's TLS configuration or directory permissions.

## Ongoing administration

- Make directory-managed identity changes in AD or LDAP, then synchronize. If the email mapping is blank, manage the login email in FS PBX.
- Update the saved service-account password when it changes, then run **Test Connection**.
- Disabled AD users are disabled locally on synchronization. Users who leave the selected search results are also disabled locally after a successful synchronization. Review scope changes before saving them.
- Turning off **Enable Directory** pauses synchronization and authentication through that directory; it does not delete imported records.
- Deleting a connection removes its imported directory groups and users created by that connection. Local users that existed before being linked are retained. Read the deletion confirmation before proceeding.

## Troubleshooting

| Symptom | What to check |
| --- | --- |
| Cannot bind or connect | Hostname resolution, network access, port, secure connection type, service-account username/password, and certificate trust. Use a full service-account sign-in name or DN. |
| **User DN** or **Group DN** search fails | The path must exist and be searchable by the service account. For independent complete paths, clear Base DN and supply both DNs. |
| Connection succeeds but sync fails or imports no users | Check the user search path, object class, filter, unique identifier, and username attribute. Connection testing does not validate the full mapping. |
| Groups are missing | Check the group search path, object class, filter, common name, and shared unique identifier attribute. |
| Users have no mapped roles | Synchronize first, check **View members**, set **Manage groups locally** to **No**, and save the mappings. Confirm membership attributes contain DNs and users are direct members. |
| Imported user cannot sign in | Use the email saved in FS PBX with the directory password. Check the user, directory, and account are enabled, at least one local role is assigned, and verification requirements can be completed. |
| A user is skipped because of an email or directory conflict | Resolve the reported conflict. One email cannot identify users in different accounts, and an existing user cannot be managed by two different directory connections. |
| Synchronization reports an unexpectedly large reduction in users | Recheck the Base DN, User DN, and filter before proceeding. A large scope change can disable previously imported users. |
| **Sync Now** does not run | Enable the directory, refresh its status, and check the scheduled job server selection on redundant installations. |
