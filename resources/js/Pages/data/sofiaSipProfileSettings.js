import { trans } from "@i18n";

/**
 * Catalog of well-known Sofia SIP profile settings (sofia.conf <param> entries),
 * used to drive autocomplete, type-aware value inputs, grouping and inline help
 * in the SIP Profile editor. Unknown/custom parameter names are still allowed —
 * anything not found here falls back to a free-text value input and the
 * "Custom" group.
 *
 * Each entry:
 *   name        - exact Sofia parameter name (matches sip_profile_setting_name)
 *   group       - category used for grouping/collapsing in the UI
 *   type        - "boolean" | "enum" | "number" | "text" (drives the value input)
 *   options     - allowed string values for boolean/enum types
 *   description - short inline help shown as placeholder/tooltip
 */

// Group order is significant — it controls the order sections render in.
export const SIP_SETTING_GROUPS = [
    "General",
    "Authentication & Registration",
    "Media & Codecs",
    "NAT",
    "TLS",
    "Presence",
    "Timers & Advanced",
];

export const CUSTOM_GROUP = "Custom";

// Translate display labels while keeping grouping keys stable.
export function sipSettingGroupLabel(group) {
    return {
        "General": trans("General"),
        "Authentication & Registration": trans("Authentication & Registration"),
        "Media & Codecs": trans("Media & Codecs"),
        "Presence": trans("Presence"),
        "Timers & Advanced": trans("Timers & Advanced"),
        "Custom": trans("Custom"),
    }[group] ?? group;
}

const BOOL = ["true", "false"];
const YESNO = ["no", "yes"];

export const SOFIA_SIP_SETTINGS = [
    // ── General ─────────────────────────────────────────────────────────────
    { name: "context", group: "General", type: "text", description: "Dialplan context for inbound calls (e.g. public).", get translatedDescription() { return trans("Dialplan context for inbound calls (e.g. public)."); } },
    { name: "dialplan", group: "General", type: "text", description: "Dialplan interpreter, normally XML.", get translatedDescription() { return trans("Dialplan interpreter, normally XML."); } },
    { name: "sip-port", group: "General", type: "number", description: "UDP/TCP port Sofia binds to (default 5060).", get translatedDescription() { return trans("UDP/TCP port Sofia binds to (default 5060)."); } },
    { name: "sip-ip", group: "General", type: "text", description: "IP Sofia binds SIP to (e.g. $${local_ip_v4}).", get translatedDescription() { return trans("IP Sofia binds SIP to (e.g. $${local_ip_v4})."); } },
    { name: "user-agent-string", group: "General", type: "text", description: "User-Agent header value sent by FreeSWITCH.", get translatedDescription() { return trans("User-Agent header value sent by FreeSWITCH."); } },
    { name: "odbc-dsn", group: "General", type: "text", description: "Database connection used for this Sofia profile.", get translatedDescription() { return trans("Database connection used for this Sofia profile."); } },
    { name: "debug", group: "General", type: "number", description: "Sofia debug level (0 = off).", get translatedDescription() { return trans("Sofia debug level (0 = off)."); } },
    { name: "sip-trace", group: "General", type: "enum", options: YESNO, description: "Log raw SIP messages to console.", get translatedDescription() { return trans("Log raw SIP messages to console."); } },
    { name: "sip-capture", group: "General", type: "enum", options: YESNO, description: "Send SIP messages to a HEP/Homer capture server.", get translatedDescription() { return trans("Send SIP messages to a HEP/Homer capture server."); } },
    { name: "log-auth-failures", group: "General", type: "boolean", options: BOOL, description: "Write a log line on every failed auth.", get translatedDescription() { return trans("Write a log line on every failed auth."); } },
    { name: "log-level", group: "General", type: "number", description: "Sofia log level.", get translatedDescription() { return trans("Sofia log level."); } },
    { name: "dump-candidates-table", group: "General", type: "boolean", options: BOOL, description: "Dump ICE candidate table for debugging.", get translatedDescription() { return trans("Dump ICE candidate table for debugging."); } },

    // ── Authentication & Registration ───────────────────────────────────────
    { name: "auth-calls", group: "Authentication & Registration", type: "boolean", options: BOOL, description: "Require authentication for inbound INVITEs.", get translatedDescription() { return trans("Require authentication for inbound INVITEs."); } },
    { name: "auth-all-packets", group: "Authentication & Registration", type: "boolean", options: BOOL, description: "Authenticate every packet, not just registration/INVITE.", get translatedDescription() { return trans("Authenticate every packet, not just registration/INVITE."); } },
    { name: "accept-blind-reg", group: "Authentication & Registration", type: "boolean", options: BOOL, description: "Allow registrations without authentication (insecure).", get translatedDescription() { return trans("Allow registrations without authentication (insecure)."); } },
    { name: "accept-blind-auth", group: "Authentication & Registration", type: "boolean", options: BOOL, description: "Accept any credentials without checking (insecure).", get translatedDescription() { return trans("Accept any credentials without checking (insecure)."); } },
    { name: "challenge-realm", group: "Authentication & Registration", type: "enum", options: ["auto_from", "auto_to"], description: "Realm used in auth challenges.", get translatedDescription() { return trans("Realm used in auth challenges."); } },
    { name: "nonce-ttl", group: "Authentication & Registration", type: "number", description: "Lifetime of auth nonces in seconds.", get translatedDescription() { return trans("Lifetime of auth nonces in seconds."); } },
    { name: "multiple-registrations", group: "Authentication & Registration", type: "text", description: "How to handle multiple contacts (e.g. contact).", get translatedDescription() { return trans("How to handle multiple contacts (e.g. contact)."); } },
    { name: "apply-inbound-acl", group: "Authentication & Registration", type: "text", description: "ACL applied to inbound requests (e.g. domains).", get translatedDescription() { return trans("ACL applied to inbound requests (e.g. domains)."); } },
    { name: "apply-register-acl", group: "Authentication & Registration", type: "text", description: "ACL applied to REGISTER requests.", get translatedDescription() { return trans("ACL applied to REGISTER requests."); } },
    { name: "force-register-domain", group: "Authentication & Registration", type: "text", description: "Force all registrations into this domain.", get translatedDescription() { return trans("Force all registrations into this domain."); } },
    { name: "force-subscription-domain", group: "Authentication & Registration", type: "text", description: "Force subscriptions into this domain.", get translatedDescription() { return trans("Force subscriptions into this domain."); } },

    // ── Media & Codecs ──────────────────────────────────────────────────────
    { name: "rtp-ip", group: "Media & Codecs", type: "text", description: "Local IP used for RTP media.", get translatedDescription() { return trans("Local IP used for RTP media."); } },
    { name: "ext-rtp-ip", group: "Media & Codecs", type: "text", description: "External/public RTP IP (autonat, stun:, or IP).", get translatedDescription() { return trans("External/public RTP IP (autonat, stun:, or IP)."); } },
    { name: "ext-sip-ip", group: "Media & Codecs", type: "text", description: "External/public SIP IP advertised in SDP/Contact.", get translatedDescription() { return trans("External/public SIP IP advertised in SDP/Contact."); } },
    { name: "rtp-timer-name", group: "Media & Codecs", type: "enum", options: ["soft", "none"], description: "RTP timer source.", get translatedDescription() { return trans("RTP timer source."); } },
    { name: "inbound-codec-prefs", group: "Media & Codecs", type: "text", description: "Preferred codecs for inbound calls.", get translatedDescription() { return trans("Preferred codecs for inbound calls."); } },
    { name: "outbound-codec-prefs", group: "Media & Codecs", type: "text", description: "Preferred codecs for outbound calls.", get translatedDescription() { return trans("Preferred codecs for outbound calls."); } },
    { name: "inbound-codec-negotiation", group: "Media & Codecs", type: "enum", options: ["generous", "greedy", "scrooge"], description: "Codec negotiation strategy.", get translatedDescription() { return trans("Codec negotiation strategy."); } },
    { name: "inbound-late-negotiation", group: "Media & Codecs", type: "boolean", options: BOOL, description: "Delay SDP negotiation until the call is answered.", get translatedDescription() { return trans("Delay SDP negotiation until the call is answered."); } },
    { name: "inbound-bypass-media", group: "Media & Codecs", type: "boolean", options: BOOL, description: "Bypass media (RTP flows endpoint to endpoint).", get translatedDescription() { return trans("Bypass media (RTP flows endpoint to endpoint)."); } },
    { name: "inbound-proxy-media", group: "Media & Codecs", type: "boolean", options: BOOL, description: "Proxy media without transcoding.", get translatedDescription() { return trans("Proxy media without transcoding."); } },
    { name: "disable-transcoding", group: "Media & Codecs", type: "boolean", options: BOOL, description: "Refuse calls that would require transcoding.", get translatedDescription() { return trans("Refuse calls that would require transcoding."); } },
    { name: "dtmf-duration", group: "Media & Codecs", type: "number", description: "RFC2833 DTMF duration in samples.", get translatedDescription() { return trans("RFC2833 DTMF duration in samples."); } },
    { name: "dtmf-type", group: "Media & Codecs", type: "enum", options: ["rfc2833", "info", "none"], description: "How DTMF events are transported.", get translatedDescription() { return trans("How DTMF events are transported."); } },
    { name: "rfc2833-pt", group: "Media & Codecs", type: "number", description: "RFC2833 telephone-event payload type.", get translatedDescription() { return trans("RFC2833 telephone-event payload type."); } },
    { name: "hold-music", group: "Media & Codecs", type: "text", description: "Music-on-hold source.", get translatedDescription() { return trans("Music-on-hold source."); } },

    // ── NAT ─────────────────────────────────────────────────────────────────
    { name: "apply-nat-acl", group: "NAT", type: "text", description: "ACL used to detect NATed endpoints (e.g. nat.auto).", get translatedDescription() { return trans("ACL used to detect NATed endpoints (e.g. nat.auto)."); } },
    { name: "local-network-acl", group: "NAT", type: "text", description: "ACL identifying local networks (e.g. localnet.auto).", get translatedDescription() { return trans("ACL identifying local networks (e.g. localnet.auto)."); } },
    { name: "aggressive-nat-detection", group: "NAT", type: "boolean", options: BOOL, description: "More aggressive NAT detection heuristics.", get translatedDescription() { return trans("More aggressive NAT detection heuristics."); } },
    { name: "nat-options-ping", group: "NAT", type: "boolean", options: BOOL, description: "Send OPTIONS keep-alives to NATed registrations.", get translatedDescription() { return trans("Send OPTIONS keep-alives to NATed registrations."); } },
    { name: "all-reg-options-ping", group: "NAT", type: "boolean", options: BOOL, description: "Send OPTIONS keep-alives to all registrations.", get translatedDescription() { return trans("Send OPTIONS keep-alives to all registrations."); } },
    { name: "NDLB-force-rport", group: "NAT", type: "text", description: "Work around broken NAT clients by forcing rport.", get translatedDescription() { return trans("Work around broken NAT clients by forcing rport."); } },
    { name: "NDLB-broken-auth-hash", group: "NAT", type: "boolean", options: BOOL, description: "Tolerate broken auth hashes from some devices.", get translatedDescription() { return trans("Tolerate broken auth hashes from some devices."); } },

    // ── TLS ─────────────────────────────────────────────────────────────────
    { name: "tls", group: "TLS", type: "boolean", options: BOOL, description: "Enable SIP over TLS.", get translatedDescription() { return trans("Enable SIP over TLS."); } },
    { name: "tls-only", group: "TLS", type: "boolean", options: BOOL, description: "Disable plain UDP/TCP, accept TLS only.", get translatedDescription() { return trans("Disable plain UDP/TCP, accept TLS only."); } },
    { name: "tls-bind-params", group: "TLS", type: "text", description: "Bind parameters for the TLS listener (e.g. transport=tls).", get translatedDescription() { return trans("Bind parameters for the TLS listener (e.g. transport=tls)."); } },
    { name: "tls-sip-port", group: "TLS", type: "number", description: "Port for the TLS listener (default 5061).", get translatedDescription() { return trans("Port for the TLS listener (default 5061)."); } },
    { name: "tls-passphrase", group: "TLS", type: "text", description: "Passphrase for the TLS private key.", get translatedDescription() { return trans("Passphrase for the TLS private key."); } },
    { name: "tls-verify-date", group: "TLS", type: "boolean", options: BOOL, description: "Verify certificate validity dates.", get translatedDescription() { return trans("Verify certificate validity dates."); } },
    { name: "tls-verify-policy", group: "TLS", type: "enum", options: ["none", "peer", "all", "subjects_all", "subjects_in"], description: "Certificate verification policy.", get translatedDescription() { return trans("Certificate verification policy."); } },
    { name: "tls-verify-depth", group: "TLS", type: "number", description: "Maximum certificate chain depth to verify.", get translatedDescription() { return trans("Maximum certificate chain depth to verify."); } },
    { name: "tls-version", group: "TLS", type: "text", description: "Allowed TLS versions (e.g. tlsv1.2).", get translatedDescription() { return trans("Allowed TLS versions (e.g. tlsv1.2)."); } },
    { name: "tls-verify-in-subjects", group: "TLS", type: "text", description: "If the tls-verify-policy is set to subjects_all or subjects_in this sets which subjects are allowed.", get translatedDescription() { return trans("If the tls-verify-policy is set to subjects_all or subjects_in this sets which subjects are allowed."); } },
    { name: "tls-cert-dir", group: "TLS", type: "text", description: "Directory containing the TLS certificate files.", get translatedDescription() { return trans("Directory containing the TLS certificate files."); } },

    // ── Presence ────────────────────────────────────────────────────────────
    { name: "manage-presence", group: "Presence", type: "boolean", options: BOOL, description: "Enable presence/BLF handling on this profile.", get translatedDescription() { return trans("Enable presence/BLF handling on this profile."); } },
    { name: "presence-hosts", group: "Presence", type: "text", description: "Hosts presence is published for (e.g. $${domain}).", get translatedDescription() { return trans("Hosts presence is published for (e.g. $${domain})."); } },
    { name: "presence-privacy", group: "Presence", type: "boolean", options: BOOL, description: "Hide presence details from unauthorized watchers.", get translatedDescription() { return trans("Hide presence details from unauthorized watchers."); } },
    { name: "send-presence-on-register", group: "Presence", type: "text", description: "Send presence probe on register (true/false/first-only).", get translatedDescription() { return trans("Send presence probe on register (true/false/first-only)."); } },

    // ── Timers & Advanced ───────────────────────────────────────────────────
    { name: "rtp-timeout-sec", group: "Timers & Advanced", type: "number", description: "Drop a call after this many seconds without RTP.", get translatedDescription() { return trans("Drop a call after this many seconds without RTP."); } },
    { name: "rtp-hold-timeout-sec", group: "Timers & Advanced", type: "number", description: "RTP timeout while a call is on hold.", get translatedDescription() { return trans("RTP timeout while a call is on hold."); } },
    { name: "media_timeout", group: "Timers & Advanced", type: "number", description: "Drop a call after this many seconds without media.", get translatedDescription() { return trans("Drop a call after this many seconds without media."); } },
    { name: "media_hold_timeout", group: "Timers & Advanced", type: "number", description: "Media timeout while a call is on hold.", get translatedDescription() { return trans("Media timeout while a call is on hold."); } },
    { name: "enable-timer", group: "Timers & Advanced", type: "boolean", options: BOOL, description: "Enable RFC4028 session timers.", get translatedDescription() { return trans("Enable RFC4028 session timers."); } },
    { name: "minimum-session-expires", group: "Timers & Advanced", type: "number", description: "Minimum Session-Expires value to accept.", get translatedDescription() { return trans("Minimum Session-Expires value to accept."); } },
    { name: "session-timeout", group: "Timers & Advanced", type: "text", description: "Default session timeout in seconds, or false to disable.", get translatedDescription() { return trans("Default session timeout in seconds, or false to disable."); } },
    { name: "enable-100rel", group: "Timers & Advanced", type: "boolean", options: BOOL, description: "Enable PRACK / 100rel reliable provisional responses.", get translatedDescription() { return trans("Enable PRACK / 100rel reliable provisional responses."); } },
    { name: "enable-compact-headers", group: "Timers & Advanced", type: "boolean", options: BOOL, description: "Use compact SIP header names.", get translatedDescription() { return trans("Use compact SIP header names."); } },
    { name: "watchdog-enabled", group: "Timers & Advanced", type: "enum", options: YESNO, description: "Restart the profile if the SIP thread stalls.", get translatedDescription() { return trans("Restart the profile if the SIP thread stalls."); } },
    { name: "watchdog-step-timeout", group: "Timers & Advanced", type: "number", description: "Watchdog step timeout in milliseconds.", get translatedDescription() { return trans("Watchdog step timeout in milliseconds."); } },
    { name: "watchdog-event-timeout", group: "Timers & Advanced", type: "number", description: "Watchdog event timeout in milliseconds.", get translatedDescription() { return trans("Watchdog event timeout in milliseconds."); } },
    { name: "suppress-cng", group: "Timers & Advanced", type: "boolean", options: BOOL, description: "Suppress comfort-noise generation packets.", get translatedDescription() { return trans("Suppress comfort-noise generation packets."); } },
    { name: "track-calls", group: "Timers & Advanced", type: "boolean", options: BOOL, description: "Store call state so SIP calls can be recovered.", get translatedDescription() { return trans("Store call state so SIP calls can be recovered."); } },
];

const SETTINGS_BY_NAME = SOFIA_SIP_SETTINGS.reduce((map, def) => {
    map[def.name] = def;
    return map;
}, {});

/** Look up the catalog definition for a parameter name (or undefined). */
export function getSettingDefinition(name) {
    return name ? SETTINGS_BY_NAME[name.trim()] : undefined;
}

/** Resolve which group a (possibly custom) setting name belongs to. */
export function resolveSettingGroup(name) {
    return getSettingDefinition(name)?.group ?? CUSTOM_GROUP;
}

/** Names already used, so the autocomplete can avoid suggesting duplicates. */
export function availableSettingNames(usedNames = []) {
    const used = new Set(usedNames.filter(Boolean));
    return SOFIA_SIP_SETTINGS.filter((def) => !used.has(def.name));
}

/**
 * Starter templates for new profiles, modelled on the stock FreeSWITCH
 * internal/external profiles. Values are sensible defaults an admin can tweak.
 */
export const SIP_PROFILE_TEMPLATES = {
    internal: {
        get label() { return trans("Internal (phones)"); },
        get description() { return trans("Authenticated profile for registered phones, with presence and NAT handling."); },
        settings: [
            ["context", "public"],
            ["dialplan", "XML"],
            ["sip-port", "5060"],
            ["sip-ip", "$${local_ip_v4}"],
            ["rtp-ip", "$${local_ip_v4}"],
            ["auth-calls", "true"],
            ["apply-inbound-acl", "domains"],
            ["apply-nat-acl", "nat.auto"],
            ["challenge-realm", "auto_from"],
            ["manage-presence", "true"],
            ["presence-hosts", "$${domain}"],
            ["inbound-codec-prefs", "$${global_codec_prefs}"],
            ["outbound-codec-prefs", "$${global_codec_prefs}"],
            ["inbound-codec-negotiation", "generous"],
            ["nat-options-ping", "true"],
            ["rtp-timeout-sec", "300"],
            ["rtp-hold-timeout-sec", "1800"],
            ["tls", "false"],
        ],
    },
    external: {
        get label() { return trans("External (trunks)"); },
        get description() { return trans("Provider-facing profile for carrier/SIP trunks in the public context."); },
        settings: [
            ["context", "public"],
            ["dialplan", "XML"],
            ["sip-port", "5080"],
            ["sip-ip", "$${local_ip_v4}"],
            ["rtp-ip", "$${local_ip_v4}"],
            ["ext-rtp-ip", "$${external_rtp_ip}"],
            ["ext-sip-ip", "$${external_sip_ip}"],
            ["user-agent-string", "FreeSWITCH"],
            ["apply-inbound-acl", "providers"],
            ["auth-calls", "true"],
            ["nonce-ttl", "60"],
            ["inbound-late-negotiation", "true"],
            ["apply-nat-acl", "nat.auto"],
            ["tls", "$${external_ssl_enable}"],
            ["tls-bind-params", "transport=tls"],
            ["tls-only", "false"],
            ["tls-sip-port", "5061"],
            ["tls-verify-date", "false"],
            ["tls-verify-depth", "2"],
            ["tls-verify-policy", "none"],
            ["tls-version", "$${sip_tls_version}"],
            ["tls-cert-dir", "$${external_ssl_dir}"],
            ["enable-timer", "false"],
            ["session-timeout", "false"],
            ["dtmf-type", "rfc2833"],
            ["local-network-acl", "localnet.auto"],
            ["media_hold_timeout", "1800"],
            ["media_timeout", "300"],
            ["track-calls", "false"],
            ["odbc-dsn", null],
            ["manage-presence", "false"],
            ["inbound-codec-prefs", "$${global_codec_prefs}"],
            ["outbound-codec-prefs", "$${global_codec_prefs}"],
            ["inbound-codec-negotiation", "generous"],
        ],
    },
};

/** Build the per-profile RAM SQLite DSN used by Sofia. */
export function sipProfileOdbcDsn(profileName) {
    const name = String(profileName ?? "").trim() || "external";

    return `sqlite:///dev/shm/sofia_reg_${name}.db`;
}

/** Build editor rows from a template id. */
export function templateSettings(templateId, profileName = "") {
    const tpl = SIP_PROFILE_TEMPLATES[templateId];
    if (!tpl) return [];

    return tpl.settings.map(([name, value]) => ({
        sip_profile_setting_uuid: null,
        sip_profile_setting_name: name,
        sip_profile_setting_value: name === "odbc-dsn" ? sipProfileOdbcDsn(profileName) : value,
        sip_profile_setting_enabled: "true",
        sip_profile_setting_description: getSettingDefinition(name)?.description ?? "",
    }));
}
