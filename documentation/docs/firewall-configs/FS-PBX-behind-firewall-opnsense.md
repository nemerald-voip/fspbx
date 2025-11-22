# OPNsense Port Forwarding for FSPBX

This guide walks you through configuring **port forwarding** on OPNsense for a FSPBX (FSPBX) server.

# FS PBX Behind Firewall (example: opnsense)

To get FS PBX working correctly behind a firewall, you must configure both FS PBX and your firewall to handle network address translation (NAT) and allow the necessary SIP and RTP traffic. The specific settings you need to adjust depend on your network environment, especially whether you have a static public IP address.

## Adjust firewall settings

Your firewall must have specific ports forwarded to the internal IP address of your FS PBX server. For security, it is highly recommended to restrict this port forwarding to only the IP addresses of your SIP trunk provider and remote extensions.

## Required ports to forward:

	* SIP: UDP/TCP - `5060` (or `5060-5091`): For SIP signaling traffic. Note that FS PBX may use port 5080 for the external SIP profile, depending on the configuration.

	* RTP: UDP: `16384-32768`: For the voice and media traffic. Some providers or setups might use a different range, so it's best to confirm with them.

	* Web GUI (Optional): TCP: `443` and `80`: If you need to access the FS PBX web interface from outside your network.

## Update FS PBX settings

These adjustments within the FS PBX interface tell the FreeSWITCH core how to handle traffic when it is behind NAT.

Configure SIP profiles for NAT

For more granular control, you can adjust the SIP profiles in FS PBX.

	* Internal Profile: In Advanced > SIP Profiles, go to the settings for the internal profile (`5060`).

			-Set the `aggressive-nat-detection` to `true`.

			-Set the `apply-nat-acl` to `nat.auto`.

	* External Profile: Review the external profile (`5080`) settings to ensure they are configured for your environment. The external profile is designed for handling devices or trunks outside your local network.


---

## 1. Prerequisites

- OPNsense admin access
- Public IP assigned to WAN interface
- FSPBX server with static LAN IP (e.g., `192.168.1.100`)
- Knowledge of FSPBX ports

---

## 2. Typical FSPBX Ports to Forward

| Service                     | Protocol | Port(s)       |
|------------------------------|---------|---------------|
| SIP                          | UDP     | 5060          |
| SIP TLS (optional)           | TCP     | 5061          |
| RTP (Audio)                  | UDP     | 16384-32768   |
| HTTP (Admin GUI, optional)   | TCP     | 80            |
| HTTPS (Admin GUI, recommended)| TCP    | 443           |
| IAX2 (if used)               | UDP     | 4569          |

---

## 3. Log in to OPNsense

1. Open a web browser.
2. Navigate to your OPNsense GUI (e.g., `https://192.168.1.1`).
3. Log in with admin credentials.

---

## 4. Add NAT Port Forward Rules

1. Go to **Firewall → NAT → Port Forward**.
2. Click **+ Add**.

**Rule Settings Example (SIP UDP 5060):**

- **Interface:** WAN
- **Protocol:** UDP
- **Source:** any (or restrict if desired)
- **Source port range:** any
- **Destination:** WAN address
- **Destination port range:** 5060
- **Redirect target IP:** your FSPBX server LAN IP (e.g., `192.168.1.100`)
- **Redirect target port:** 5060
- **Description:** Forward SIP UDP 5060 to FSPBX
- **Filter rule association:** Add associated filter rule (usually check this box)

3. Click **Save**.
4. Repeat for each port in the table above.

---

## 5. Apply Changes

- Click **Apply Changes** at the top of the NAT page to activate the rules.

---

## 6. Verify

- Test from an external network (not your LAN).  
- Use SIP testing tools or a softphone outside your network.  
- Ensure your FSPBX server firewall allows incoming connections on these ports.

---

## 7. Optional: Firewall Rules

- OPNsense usually creates associated firewall rules automatically when you check “Add associated filter rule.”
- Go to **Firewall → Rules → WAN** to review.

---

## 8. Security Notes

- Only forward ports that are necessary.  
- Use SIP TLS (5061) and HTTPS (443) for secure connections.  
- Use strong extension passwords.  
- Consider VPN for remote SIP instead of exposing 5060/16384-32768 to the internet.  
