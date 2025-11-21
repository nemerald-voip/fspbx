---
id: fspbx-tls-setup
title: Enabling TLS Support for FS PBX
slug: /configuration/fspbx-tls-setup
sidebar_position: 100
---

# Enabling TLS Support for FS PBX (Using a commercial SSL Certificate)

This guide provides a step-by-step tutorial for configuring TLS for FS PBX with a purchased SSL certificate. It covers prerequisites, certificate installation, FreeSWITCH/FX PBX settings, and testing.

1\. Prerequisites
=================

[](https://github.com/nemerald-voip/fspbx/wiki/Enabling-TLS-Support-for-FS-PBX-(Using-a-Purchased-SSL-Certificate)#1-prerequisites)

-   SSL Certificate Files: Obtain your certificate files from the CA. Typically you will have:

    -   The private key file (e.g. yourdomain.key).
    -   The public certificate file (e.g. yourdomain.crt).
    -   The CA intermediate bundle (sometimes provided separately or already included in a "full chain" file).
    -   Ensure you have the full certificate chain (your certificate + intermediates). The CA may provide a single *fullchain* file or separate files. For example, Let's Encrypt provides `fullchain.pem` which includes your cert and the CA chain. A purchased cert may come with a separate CA bundle file -- you will need to concatenate it with your cert to form the full chain.

2\. SSL Certificate Installation
================================

[](https://github.com/nemerald-voip/fspbx/wiki/Enabling-TLS-Support-for-FS-PBX-(Using-a-Purchased-SSL-Certificate)#2-ssl-certificate-installation)

In this step, you will install your purchased SSL certificate

2.1 Upload and Prepare the Certificate
--------------------------------------

[](https://github.com/nemerald-voip/fspbx/wiki/Enabling-TLS-Support-for-FS-PBX-(Using-a-Purchased-SSL-Certificate)#21-upload-and-prepare-the-certificate)

Transfer your certificate files (.crt and any CA bundle) and your private key (.key) to the server (use SCP, SFTP, or paste via SSH). Place them in a secure directory (e.g. your home folder) temporarily. Then set the proper permissions on these files for security:

-   Set File Ownership and Permissions: The private key should be readable only by root (and services that need it). For example, set the key file's ownership to `root` and group `ssl-cert` (Debian's group for TLS materials), and permissions to 640. The certificate (and chain) can be world-readable (permission 644). For example:

    ```
    chown root:ssl-cert yourdomain.key
    chmod 640 yourdomain.key
    chmod 644 yourdomain.crt  [and yourdomain_bundle.crt if separate]

    ```

This ensures the key is not publicly readable​. Adjust file names as needed for your actual files.

2.2 Install Certificate for FreeSWITCH (SIP TLS and WebRTC)
-----------------------------------------------------------

[](https://github.com/nemerald-voip/fspbx/wiki/Enabling-TLS-Support-for-FS-PBX-(Using-a-Purchased-SSL-Certificate)#22-install-certificate-for-freeswitch-sip-tls-and-webrtc)

Next, configure FreeSWITCH to use the certificate for SIP over TLS and WebRTC. FreeSWITCH expects its certificate files in a specific directory (usually `/etc/freeswitch/tls` for FS PBX installations). We will create a combined certificate file as required by FreeSWITCH:

-   Create FreeSWITCH TLS Directory: Ensure the directory exists and is empty. By default, FS PBX uses `/etc/freeswitch/tls` (this is set by the `internal_ssl_dir` and `external_ssl_dir` variables). Create it if not present and remove any default self-signed files to avoid confusion:

    ```
    mkdir -p /etc/freeswitch/tls
    rm -f /etc/freeswitch/tls/*

    ```

    *(The above removes all files in the TLS dir. Make sure you really want to delete the old certs.)*

-   Combine Certificate and Key: FreeSWITCH uses a single "pem" file that contains both the certificate and the private key (and optionally the chain). We will create `all.pem` as this combined file. Concatenate your full chain certificate and the key:

    ```
    cat yourdomain.crt yourdomain_bundle.crt > /etc/freeswitch/tls/all.pem
    cat yourdomain.key >> /etc/freeswitch/tls/all.pem

    ```

    If your `.crt` file already includes the intermediate chain (some CAs send a fullchain file), you can use that directly (e.g. `cat fullchain.pem > /etc/freeswitch/tls/all.pem` then append the key). After this, `/etc/freeswitch/tls/all.pem` should contain, in order: your server certificate, any intermediate CA certs, and then the private key. (You can open it to verify the contents: it should have one `BEGIN CERTIFICATE` block for each cert in the chain and one `BEGIN PRIVATE KEY` block.)

-   Optional - Store Individual Files: (Optional) You may also copy the individual cert and chain files for reference (not strictly required by FreeSWITCH). For example:

    ```
    cp yourdomain.crt /etc/freeswitch/tls/cert.pem
    cp yourdomain_bundle.crt /etc/freeswitch/tls/chain.pem
    cp yourdomain.crt yourdomain_bundle.crt > /etc/freeswitch/tls/fullchain.pem
    cp yourdomain.key /etc/freeswitch/tls/privkey.pem

    ```

    These copies are just for convenience/documentation -- FreeSWITCH will use `all.pem` for TLS handshakes. The main file that must be correct is `all.pem`.

-   Create Symbolic Links: For compatibility, create symlinks that FreeSWITCH might look for (some profiles or modules reference specific filenames). Link the combined `all.pem` to the following names in the same directory:

    ```
    ln -s /etc/freeswitch/tls/all.pem /etc/freeswitch/tls/agent.pem
    ln -s /etc/freeswitch/tls/all.pem /etc/freeswitch/tls/tls.pem
    ln -s /etc/freeswitch/tls/all.pem /etc/freeswitch/tls/wss.pem
    ln -s /etc/freeswitch/tls/all.pem /etc/freeswitch/tls/dtls-srtp.pem

    ```

    These links ensure that whether FreeSWITCH looks for `agent.pem` (used for SIP TLS), `wss.pem` (used for secure WebSockets), or `dtls-srtp.pem` (used for WebRTC media encryption), it will use your combined certificate.

-   Set Permissions for FreeSWITCH: FreeSWITCH (under FS PBX) typically runs as the user `www-data. Ensure the` /etc/freeswitch/tls` directory and files are owned by the FreeSWITCH user and group so that it can read the key:

    ```
    chown -R www-data:www-data /etc/freeswitch/tls
    chmod -R 640 /etc/freeswitch/tls/*

    ```

    This makes all files readable by the FreeSWITCH process. Incorrect permissions will prevent the TLS listener from starting​, so double-check that the FreeSWITCH user has read access to `all.pem` file.

At this point, the certificate is installed in the expected locations. Next, we configure FreeSWITCH and FusionPBX to enable and use TLS.

3\. FreeSWITCH Configuration (Enable TLS and SRTP)
==================================================

[](https://github.com/nemerald-voip/fspbx/wiki/Enabling-TLS-Support-for-FS-PBX-(Using-a-Purchased-SSL-Certificate)#3-freeswitch-configuration-enable-tls-and-srtp)

Now we will enable TLS support in FreeSWITCH's SIP profiles and adjust settings for secure SIP and media. This can be done via FS PBX's GUI (Advanced -> Variables)

-   Advanced -> Variables: In the FS PBX GUI, navigate to Advanced -> Variables. Here, you can check SIP Profile: Internal and External sections for:

    -   internal_ssl_enable = true
    -   external_ssl_enable = true
-   Advanced -> SIP Profiles: Go to Advanced -> SIP Profiles -> Internal. This shows the internal profile parameters. Scroll and verify that ws-binding and wss-binding are present and enabled​.

    ```
    <param name="ws-binding" value=":5066"/>       <!-- optional: enable unencrypted WS -->
    <param name="wss-binding" value=":7443"/>     <!-- enable secure WebSocket on port 7443 -->

    ```

    Do the same for the external profile if you plan to allow WebSockets on external (commonly not needed unless you have external WS clients). After applying, restart or rescan the SIP profile. This tells FreeSWITCH to listen on those ports for SIP over WebSocket. WSS is required for browser-based calls (WebRTC) when the site is served over HTTPS.

-   Extensions Settings: By default, extensions in FS PBX can use UDP, TCP, or TLS for registration as long as the server supports them. There isn't a per-extension "enable TLS" toggle -- you simply configure the device (phone or softphone) to connect with TLS to port 5061. FS PBX will accept it because we enabled TLS on the profile. However, you should double-check extension password security since opening TLS will expose another entry point for registration attempts.

-   Dialplan (Optional SRTP enforcement): By default, SRTP enforcement is not strict (to maintain compatibility). If you require all calls from TLS endpoints to be encrypted, you could modify the dial-string or use FS PBX's Dialplan manager to require `rtp_secure_media=true`. This is an advanced configuration and should be tested carefully (many ITSP trunks don't support SRTP, so you may not want to enforce it globally). For most setups, enabling TLS on registration is enough, and endpoints will use SRTP if they are WebRTC or if configured to do so.

After updating FS PBX settings, flush the cache and rescan profiles if you made changes through the GUI. The FS PBX interface and FreeSWITCH should now be configured to use your SSL certificate for both HTTPS and SIP TLS/WSS. You may also need to restat FreeSWITCH service `systemctl restart freeswitch`

4\. TLS Security
================

[](https://github.com/nemerald-voip/fspbx/wiki/Enabling-TLS-Support-for-FS-PBX-(Using-a-Purchased-SSL-Certificate)#4-tls-security)

The TLS connection uses standard SSL protocols. Most modern phones and softphones will trust common commercial CAs. To avoid Man-in-the-Middle attacks, clients should verify the server certificate. This means the client must trust the CA that signed your cert. If you purchased from a well-known CA, most devices (and browsers for WebRTC) will implicitly trust it. Avoid using self-signed or private CA certs for client-facing TLS to prevent trust issues. It's also wise to disable old TLS versions if any of your clients allow (e.g., force TLS 1.2+ as mentioned). You can also configure FreeSWITCH to present the full certificate chain (which we did by using fullchain.pem in all.pem) so that clients can validate up to the root. There is an option to require mutual TLS (client certificates), but that's rarely used in SIP and is beyond the scope of this guide.