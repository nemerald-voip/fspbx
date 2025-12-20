# MikroTik Port Forwarding for FSPBX

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

## Adjust extension media settings

For each extension that is located behind a different NAT than the PBX, you may need to adjust the media handling settings.

	* Proxy Media: On the Extensions page, ensure that the media mode is set to "Proxy Media" instead of "Bypass Media." When proxy media is enabled, the FS PBX server acts as a proxy for the media stream, helping to resolve issues with NAT.

Example scenario: Remote extensions and external SIP trunks

If your setup includes both remote extensions and external SIP trunks, follow these steps:

	* Create firewall aliases: Create aliases on your firewall for the IP addresses of your SIP trunk provider and your remote extensions.
	* Set up port forwarding rules: Create rules on your firewall to forward SIP (`5060` or `5080`) and RTP (`16384-32768`) traffic only from the IP addresses in your aliases to the internal IP of the FS PBX server.
	* Use correct SIP profiles: Point your external gateways and remote phones to the `external` SIP profile, which by default uses port `5080` and is better equipped to handle external traffic.

# MikroTik Port Forwarding for FSPBX

Continuing through configuring **port forwarding** on a MikroTik router for a FSPBX (FSPBX) server.

---

## 1. Prerequisites

- Access to MikroTik RouterOS via **WinBox**, **WebFig**, or **CLI**
- Public IP on WAN interface
- FSPBX server with static LAN IP (e.g., `192.168.88.100`)
- Knowledge of the ports FSPBX needs

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

## 3. Log in to MikroTik

- Use **WinBox**, **WebFig**, or **SSH**.
- Make sure you are on the **admin account** or an account with NAT privileges.

---

## 4. Add NAT Port Forward Rules

### Via WinBox / WebFig:

1. Go to **IP → Firewall → NAT**.
2. Click **+ Add New**.
3. Set **Chain** to `dstnat`.
4. Set **Dst. Address** to your WAN IP (or leave blank for any).
5. Set **Protocol** to the port’s protocol (TCP or UDP).
6. Set **Dst. Port** to the port you want to forward (e.g., 5060).
7. In **Action**, select `dst-nat`.
8. Set **To Addresses** to your FSPBX LAN IP (e.g., `192.168.88.100`).
9. Set **To Ports** to the same port.
10. Add a **Description** (e.g., `Forward SIP UDP 5060 to FSPBX`).
11. Click **OK**.
12. Repeat for each port in the table above.

### Via CLI:

```bash
/ip firewall nat add chain=dstnat dst-port=5060 protocol=udp action=dst-nat to-addresses=192.168.88.100 to-ports=5060 comment="SIP UDP 5060 to FSPBX"
```

Repeat for other ports (5061 TCP, RTP 16384-32768 UDP, etc.).

---

## 5. Allow Firewall Traffic (Optional if not automatic)

- Ensure the **forward chain** in **IP → Firewall → Filter Rules** allows traffic to your FSPBX server.
- Example CLI rule for UDP:

```bash
/ip firewall filter add chain=forward dst-address=192.168.88.100 protocol=udp dst-port=5060 action=accept comment="Allow SIP UDP to FSPBX"
```

---

## 6. Verify

- Test from an external network (not your LAN).
- Use a softphone or SIP testing tool to confirm connectivity.
- Ensure the FSPBX server firewall allows incoming connections on forwarded ports.

---

## 7. Security Notes

- Only forward required ports.
- Prefer SIP TLS (5061) and HTTPS (443) for security.
- Use strong passwords for all extensions and admin accounts.
- Consider VPN for remote SIP access instead of exposing ports 5060/16384-32768 directly to the internet.
