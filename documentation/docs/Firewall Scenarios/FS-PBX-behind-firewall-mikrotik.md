# FS PBX Behind Firewall (example: Mikrotik)

To get FS PBX working correctly behind a firewall, you must configure both FS PBX and your firewall to handle network address translation (NAT) and allow the necessary SIP and RTP traffic. The specific settings you need to adjust depend on your network environment, especially whether you have a static public IP address.

## Adjust firewall settings

Your firewall must have specific ports forwarded to the internal IP address of your FS PBX server. For security, it is highly recommended to restrict this port forwarding to only the IP addresses of your SIP trunk provider and remote extensions.

## Required ports to forward:

	-SIP:

		-UDP/TCP: `5060` (or `5060-5091`): For SIP signaling traffic. Note that FS PBX may use port 5080 for the external SIP profile, depending on the configuration.

	-RTP:

		-UDP: `16384-32768`: For the voice and media traffic. Some providers or setups might use a different range, so it's best to confirm with them.

	-Web GUI (Optional):

		-TCP: `443` and `80`: If you need to access the FS PBX web interface from outside your network.

## Update FS PBX settings

These adjustments within the FS PBX interface tell the FreeSWITCH core how to handle traffic when it is behind NAT.

Configure SIP profiles for NAT

For more granular control, you can adjust the SIP profiles in FS PBX.

    * 1. Internal Profile: In Advanced > SIP Profiles, go to the settings for the internal profile (`5060`).

			-Set the `aggressive-nat-detection` to `true`.

			-Set the `apply-nat-acl` to `nat.auto`.

	* 2. External Profile: Review the external profile (`5080`) settings to ensure they are configured for your environment. The external profile is designed for handling devices or trunks outside your local network.

## Adjust extension media settings

For each extension that is located behind a different NAT than the PBX, you may need to adjust the media handling settings.

	* 1. Proxy Media: On the Extensions page, ensure that the media mode is set to "Proxy Media" instead of "Bypass Media." When proxy media is enabled, the FS PBX server acts as a proxy for the media stream, helping to resolve issues with NAT.

Example scenario: Remote extensions and external SIP trunks

If your setup includes both remote extensions and external SIP trunks, follow these steps:

	* 1. Create firewall aliases: Create aliases on your firewall for the IP addresses of your SIP trunk provider and your remote extensions.

	* 2. Set up port forwarding rules: Create rules on your firewall to forward SIP (`5060` or `5080`) and RTP (`16384-32768`) traffic only from the IP addresses in your aliases to the internal IP of the FS PBX server.

	* 3. Use correct SIP profiles: Point your external gateways and remote phones to the `external` SIP profile, which by default uses port `5080` and is better equipped to handle external traffic.

