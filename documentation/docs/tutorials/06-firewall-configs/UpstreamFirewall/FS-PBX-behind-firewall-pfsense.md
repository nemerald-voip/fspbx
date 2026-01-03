# pfSense Port Forwarding for FSPBX

# FS PBX Behind Firewall (example: pfSense)

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

This guide walks you through configuring **port forwarding** on pfSense for a FSPBX server.

---

## 1. Prerequisites

- pfSense admin access
- Public IP address assigned to your WAN interface
- FSPBX server with static LAN IP (e.g., `192.168.1.100`)
- Basic knowledge of the ports FreePBX requires

---

## 2. Typical FSPBX Ports to Forward

| Service                     | Protocol | Port(s)   |
|------------------------------|---------|-----------|
| SIP (UDP)                    | UDP     | 5060      |
| SIP TLS (optional)           | TCP     | 5061      |
| RTP (Audio)                  | UDP     | 16384-32768 |
| HTTP (Admin GUI, optional)   | TCP     | 80        |
| HTTPS (Admin GUI, recommended)| TCP     | 443       |

> Note: If using IAX2, forward UDP 4569.

---

## 3. Log in to pfSense

1. Open a web browser.
2. Navigate to your pfSense GUI (e.g., `https://192.168.1.1`).
3. Log in with admin credentials.

---

## 4. Add NAT Port Forward Rules

1. Go to **Firewall → NAT → Port Forward**.
2. Click **+ Add** (or “Add new rule”).

**Basic NAT Rule Settings:**

- **Interface:** WAN
- **Protocol:** TCP/UDP (or select specific UDP/TCP as needed)
- **Destination:** WAN Address
- **Destination Port Range:** enter the port number (e.g., 5060)
- **Redirect Target IP:** your FSPBX server LAN IP (e.g., `192.168.1.100`)
- **Redirect Target Port:** same as destination port
- **Description:** e.g., `Forward SIP UDP 5060 to FSPBX`

3. Check **NAT Reflection** if internal devices need to access the WAN IP.
4. Click **Save**.
5. Repeat for each port in the table above.

---

## 5. Apply Changes

1. Click **Apply Changes** at the top.
2. pfSense will update the NAT and firewall rules.

---

## 6. Verify

- Test from an external network (not your LAN).
- Use tools like `sipcalc`, `sipcli`, or your VoIP provider testing tool.
- Make sure your FSPBX server firewall allows incoming connections on these ports.

---

## 7. Optional: Firewall Rules

- pfSense should automatically create firewall rules when you add port forwards.
- Double-check under **Firewall → Rules → WAN** to ensure they exist and are enabled.

---

## 8. Security Notes

- Only open ports you need.
- Consider using TLS (5061) for SIP and HTTPS (443) for GUI.
- Use strong passwords for extensions and admin accounts.
- Optionally, use a VPN for remote SIP traffic instead of opening UDP 5060/16384-32768 to the internet.
