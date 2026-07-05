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

const BOOL = ["true", "false"];
const YESNO = ["no", "yes"];

export const SOFIA_SIP_SETTINGS = [
    // ── General ─────────────────────────────────────────────────────────────
    { name: "context", group: "General", type: "text", description: "Dialplan context for inbound calls (e.g. public)." },
    { name: "dialplan", group: "General", type: "text", description: "Dialplan interpreter, normally XML." },
    { name: "sip-port", group: "General", type: "number", description: "UDP/TCP port Sofia binds to (default 5060)." },
    { name: "sip-ip", group: "General", type: "text", description: "IP Sofia binds SIP to (e.g. $${local_ip_v4})." },
    { name: "user-agent-string", group: "General", type: "text", description: "User-Agent header value sent by FreeSWITCH." },
    { name: "debug", group: "General", type: "number", description: "Sofia debug level (0 = off)." },
    { name: "sip-trace", group: "General", type: "enum", options: YESNO, description: "Log raw SIP messages to console." },
    { name: "sip-capture", group: "General", type: "enum", options: YESNO, description: "Send SIP messages to a HEP/Homer capture server." },
    { name: "log-auth-failures", group: "General", type: "boolean", options: BOOL, description: "Write a log line on every failed auth." },
    { name: "log-level", group: "General", type: "number", description: "Sofia log level." },
    { name: "dump-candidates-table", group: "General", type: "boolean", options: BOOL, description: "Dump ICE candidate table for debugging." },

    // ── Authentication & Registration ───────────────────────────────────────
    { name: "auth-calls", group: "Authentication & Registration", type: "boolean", options: BOOL, description: "Require authentication for inbound INVITEs." },
    { name: "auth-all-packets", group: "Authentication & Registration", type: "boolean", options: BOOL, description: "Authenticate every packet, not just registration/INVITE." },
    { name: "accept-blind-reg", group: "Authentication & Registration", type: "boolean", options: BOOL, description: "Allow registrations without authentication (insecure)." },
    { name: "accept-blind-auth", group: "Authentication & Registration", type: "boolean", options: BOOL, description: "Accept any credentials without checking (insecure)." },
    { name: "challenge-realm", group: "Authentication & Registration", type: "enum", options: ["auto_from", "auto_to"], description: "Realm used in auth challenges." },
    { name: "nonce-ttl", group: "Authentication & Registration", type: "number", description: "Lifetime of auth nonces in seconds." },
    { name: "multiple-registrations", group: "Authentication & Registration", type: "text", description: "How to handle multiple contacts (e.g. contact)." },
    { name: "apply-inbound-acl", group: "Authentication & Registration", type: "text", description: "ACL applied to inbound requests (e.g. domains)." },
    { name: "apply-register-acl", group: "Authentication & Registration", type: "text", description: "ACL applied to REGISTER requests." },
    { name: "force-register-domain", group: "Authentication & Registration", type: "text", description: "Force all registrations into this domain." },
    { name: "force-subscription-domain", group: "Authentication & Registration", type: "text", description: "Force subscriptions into this domain." },

    // ── Media & Codecs ──────────────────────────────────────────────────────
    { name: "rtp-ip", group: "Media & Codecs", type: "text", description: "Local IP used for RTP media." },
    { name: "ext-rtp-ip", group: "Media & Codecs", type: "text", description: "External/public RTP IP (autonat, stun:, or IP)." },
    { name: "ext-sip-ip", group: "Media & Codecs", type: "text", description: "External/public SIP IP advertised in SDP/Contact." },
    { name: "rtp-timer-name", group: "Media & Codecs", type: "enum", options: ["soft", "none"], description: "RTP timer source." },
    { name: "inbound-codec-prefs", group: "Media & Codecs", type: "text", description: "Preferred codecs for inbound calls." },
    { name: "outbound-codec-prefs", group: "Media & Codecs", type: "text", description: "Preferred codecs for outbound calls." },
    { name: "inbound-codec-negotiation", group: "Media & Codecs", type: "enum", options: ["generous", "greedy", "scrooge"], description: "Codec negotiation strategy." },
    { name: "inbound-late-negotiation", group: "Media & Codecs", type: "boolean", options: BOOL, description: "Delay SDP negotiation until the call is answered." },
    { name: "inbound-bypass-media", group: "Media & Codecs", type: "boolean", options: BOOL, description: "Bypass media (RTP flows endpoint to endpoint)." },
    { name: "inbound-proxy-media", group: "Media & Codecs", type: "boolean", options: BOOL, description: "Proxy media without transcoding." },
    { name: "disable-transcoding", group: "Media & Codecs", type: "boolean", options: BOOL, description: "Refuse calls that would require transcoding." },
    { name: "dtmf-duration", group: "Media & Codecs", type: "number", description: "RFC2833 DTMF duration in samples." },
    { name: "rfc2833-pt", group: "Media & Codecs", type: "number", description: "RFC2833 telephone-event payload type." },
    { name: "hold-music", group: "Media & Codecs", type: "text", description: "Music-on-hold source." },

    // ── NAT ─────────────────────────────────────────────────────────────────
    { name: "apply-nat-acl", group: "NAT", type: "text", description: "ACL used to detect NATed endpoints (e.g. nat.auto)." },
    { name: "aggressive-nat-detection", group: "NAT", type: "boolean", options: BOOL, description: "More aggressive NAT detection heuristics." },
    { name: "nat-options-ping", group: "NAT", type: "boolean", options: BOOL, description: "Send OPTIONS keep-alives to NATed registrations." },
    { name: "all-reg-options-ping", group: "NAT", type: "boolean", options: BOOL, description: "Send OPTIONS keep-alives to all registrations." },
    { name: "NDLB-force-rport", group: "NAT", type: "text", description: "Work around broken NAT clients by forcing rport." },
    { name: "NDLB-broken-auth-hash", group: "NAT", type: "boolean", options: BOOL, description: "Tolerate broken auth hashes from some devices." },

    // ── TLS ─────────────────────────────────────────────────────────────────
    { name: "tls", group: "TLS", type: "boolean", options: BOOL, description: "Enable SIP over TLS." },
    { name: "tls-only", group: "TLS", type: "boolean", options: BOOL, description: "Disable plain UDP/TCP, accept TLS only." },
    { name: "tls-bind-params", group: "TLS", type: "text", description: "Bind parameters for the TLS listener (e.g. transport=tls)." },
    { name: "tls-sip-port", group: "TLS", type: "number", description: "Port for the TLS listener (default 5061)." },
    { name: "tls-passphrase", group: "TLS", type: "text", description: "Passphrase for the TLS private key." },
    { name: "tls-verify-date", group: "TLS", type: "boolean", options: BOOL, description: "Verify certificate validity dates." },
    { name: "tls-verify-policy", group: "TLS", type: "enum", options: ["none", "peer", "all", "subjects_all", "subjects_in"], description: "Certificate verification policy." },
    { name: "tls-verify-depth", group: "TLS", type: "number", description: "Maximum certificate chain depth to verify." },
    { name: "tls-version", group: "TLS", type: "text", description: "Allowed TLS versions (e.g. tlsv1.2)." },

    // ── Presence ────────────────────────────────────────────────────────────
    { name: "manage-presence", group: "Presence", type: "boolean", options: BOOL, description: "Enable presence/BLF handling on this profile." },
    { name: "presence-hosts", group: "Presence", type: "text", description: "Hosts presence is published for (e.g. $${domain})." },
    { name: "presence-privacy", group: "Presence", type: "boolean", options: BOOL, description: "Hide presence details from unauthorized watchers." },
    { name: "send-presence-on-register", group: "Presence", type: "text", description: "Send presence probe on register (true/false/first-only)." },

    // ── Timers & Advanced ───────────────────────────────────────────────────
    { name: "rtp-timeout-sec", group: "Timers & Advanced", type: "number", description: "Drop a call after this many seconds without RTP." },
    { name: "rtp-hold-timeout-sec", group: "Timers & Advanced", type: "number", description: "RTP timeout while a call is on hold." },
    { name: "enable-timer", group: "Timers & Advanced", type: "boolean", options: BOOL, description: "Enable RFC4028 session timers." },
    { name: "minimum-session-expires", group: "Timers & Advanced", type: "number", description: "Minimum Session-Expires value to accept." },
    { name: "session-timeout", group: "Timers & Advanced", type: "number", description: "Default session timeout in seconds." },
    { name: "enable-100rel", group: "Timers & Advanced", type: "boolean", options: BOOL, description: "Enable PRACK / 100rel reliable provisional responses." },
    { name: "enable-compact-headers", group: "Timers & Advanced", type: "boolean", options: BOOL, description: "Use compact SIP header names." },
    { name: "watchdog-enabled", group: "Timers & Advanced", type: "enum", options: YESNO, description: "Restart the profile if the SIP thread stalls." },
    { name: "watchdog-step-timeout", group: "Timers & Advanced", type: "number", description: "Watchdog step timeout in milliseconds." },
    { name: "watchdog-event-timeout", group: "Timers & Advanced", type: "number", description: "Watchdog event timeout in milliseconds." },
    { name: "suppress-cng", group: "Timers & Advanced", type: "boolean", options: BOOL, description: "Suppress comfort-noise generation packets." },
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
        label: "Internal (phones)",
        description: "Authenticated profile for registered phones, with presence and NAT handling.",
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
        label: "External (trunks)",
        description: "Unauthenticated profile for carrier/SIP trunks in the public context.",
        settings: [
            ["context", "public"],
            ["dialplan", "XML"],
            ["sip-port", "5080"],
            ["sip-ip", "$${local_ip_v4}"],
            ["rtp-ip", "$${local_ip_v4}"],
            ["ext-rtp-ip", "$${external_rtp_ip}"],
            ["ext-sip-ip", "$${external_sip_ip}"],
            ["auth-calls", "false"],
            ["manage-presence", "false"],
            ["inbound-codec-prefs", "$${global_codec_prefs}"],
            ["outbound-codec-prefs", "$${global_codec_prefs}"],
            ["inbound-codec-negotiation", "generous"],
            ["rtp-timeout-sec", "300"],
            ["rtp-hold-timeout-sec", "1800"],
            ["tls", "false"],
        ],
    },
};

/** Build editor rows from a template id. */
export function templateSettings(templateId) {
    const tpl = SIP_PROFILE_TEMPLATES[templateId];
    if (!tpl) return [];

    return tpl.settings.map(([name, value]) => ({
        sip_profile_setting_uuid: null,
        sip_profile_setting_name: name,
        sip_profile_setting_value: value,
        sip_profile_setting_enabled: "true",
        sip_profile_setting_description: getSettingDefinition(name)?.description ?? "",
    }));
}
