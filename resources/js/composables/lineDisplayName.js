// Mirrors fspbx_format_line_display_name() in app/helpers.php, which
// provisioning applies to device lines with a blank Display Name.
// `extension` is a device form extension option.
export function formatLineDisplayName(format, extension) {
    if (!extension) {
        return '';
    }

    const number = String(extension.value ?? '').trim();
    const first = String(extension.first_name ?? '').trim();
    const last = String(extension.last_name ?? '').trim();
    const name = String(extension.caller_id_name ?? '').trim() || `${first} ${last}`.trim();

    const pattern = String(format ?? '').trim() || '{extension}';
    const names = { '{name}': name, '{first_name}': first, '{last_name}': last };
    const usedNames = Object.keys(names).filter((token) => pattern.includes(token));
    if (usedNames.length && usedNames.every((token) => names[token] === '')) {
        return number;
    }

    const label = Object.entries({ '{extension}': number, ...names })
        .reduce((text, [token, value]) => text.split(token).join(value), pattern)
        .replace(/\s+/g, ' ')
        .trim();

    return label || number;
}

// The name a device line will show: its typed Display Name, or the automatic one.
export function effectiveLineDisplayName(displayName, authId, options) {
    const typed = String(displayName ?? '').trim();
    if (typed !== '') {
        return typed;
    }

    const extension = (options?.extensions ?? []).find((item) => String(item.value) === String(authId ?? ''));

    return formatLineDisplayName(options?.line_display_name_format, extension);
}
