// Mirrors fspbx_format_extension_label() in app/helpers.php, which
// provisioning applies to device lines with a blank Display Name
// (line_display_name_format) and to BLF/speed dial keys with a blank Label
// (blf_label_format). `extension` is an editor extension option.
export function formatExtensionLabel(format, extension, fallbackFormat = '{extension}') {
    if (!extension) {
        return '';
    }

    const number = String(extension.value ?? '').trim();
    const first = String(extension.first_name ?? '').trim();
    const last = String(extension.last_name ?? '').trim();
    const name = String(extension.caller_id_name ?? '').trim() || `${first} ${last}`.trim();

    const pattern = String(format ?? '').trim() || fallbackFormat;
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

function findExtension(value, options) {
    return (options?.extensions ?? []).find((item) => String(item.value) === String(value ?? ''));
}

// The name a device line will show: its typed Display Name, or the automatic one.
export function effectiveLineDisplayName(displayName, authId, options) {
    const typed = String(displayName ?? '').trim();
    if (typed !== '') {
        return typed;
    }

    return formatExtensionLabel(options?.line_display_name_format, findExtension(authId, options));
}

// The automatic label for a BLF/speed dial key targeting an extension, or null
// when the value is not an extension in this account.
export function automaticKeyLabel(value, options) {
    const extension = findExtension(value, options);

    return extension ? formatExtensionLabel(options?.blf_label_format, extension, '{name}') : null;
}
