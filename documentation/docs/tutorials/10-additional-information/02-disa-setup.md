---
id: disa
title: DISA Setup
slug: /additional-information/disa-setup/
sidebar_position: 2
---

# DISA Setup

### What Is DISA?

**DISA (Direct Inward System Access)** allows authorized users to access the PBX system from an external phone line. Once authenticated with a PIN, users can place outbound calls or access internal features as if they were dialing from an internal extension.\
This is useful for employees or administrators who need to make calls through the PBX remotely while maintaining control and proper billing.

* * * * *

### Step 1: Enable the DISA Dialplan

1.  Navigate to **Dialplan → Dialplan Manager**.

2.  Find the entry named **DISA** (usually mapped to the star code `*3472`).

3.  Toggle **Enabled** to **Yes** and click **Save**.

* * * * *

### Step 2: Configure Caller ID and PIN

1.  Click **Edit** on the DISA dialplan entry.

2.  Under **Advanced Variables**, find and enable the following options:

    -   **outbound_caller_id_name**

    -   **outbound_caller_id_number**

3.  Set appropriate values for both fields --- these will define the caller ID shown when making calls via DISA.

4.  Optionally, update the **pin_number** field to set your desired access PIN.

5.  Save your changes.

* * * * *

### Step 3: Flush the Cache

1.  Go to **Status → SIP Status**.

2.  Click **Flash Cache** to reload dialplan data and apply your changes.

* * * * *

### Step 4: Create a DISA Ring Group

1.  Navigate to **Applications → Ring Groups**.

2.  Click **Add** and name the group something like `DISA Access`.

3.  **Do not add any members.**

4.  Set **No Answer Action** to **Hangup**.

5.  Click **Save**.

* * * * *

### Step 5: Configure Call Forwarding

1.  Open the newly created **DISA Ring Group**.

2.  Go to the **Call Forwarding** tab.

3.  Toggle **Call Forwarding** to **Enabled**.

4.  For **Action**, select **External Number** --- even though the destination is internal, this option allows entering a custom number.

5.  In the **Target** field, enter the DISA star code:

    `*3472`

6.  Click **Save**.

* * * * *

### Step 6: Route a Phone Number to DISA

1.  Navigate to **Destinations → Phone Numbers**.

2.  Edit any available phone number that you'd like to use for DISA access.

3.  Under **Call Routing**, select your newly created **DISA Ring Group**.

4.  Save your changes.

* * * * *

### ✅ Final Test

Call your configured phone number from an external line.

-   You'll be prompted to enter the **DISA PIN**.

-   Once authenticated, you can dial internal extensions or external numbers as if you were calling from inside the PBX.