// Deep Chat 2.4.2 starts an additional fetch for scrolling and never consumes
// its response body. Large photos can exhaust the browser's HTTP/1 socket pool.
// Wait for the rendered image instead. Keep this override guarded so a dependency
// update requires review rather than silently reintroducing the extra download.
export function fixDeepChatImageLoad(source) {
    const original = 'n[C]=e[C],t&&ye.scrollDownOnImageLoad(n[C],t)';
    const replacement = 't&&(n.addEventListener("load",t,{once:true}),n.addEventListener("error",t,{once:true})),n[C]=e[C]';
    if (source.split(original).length !== 2) {
        throw new Error('Deep Chat image-load override no longer matches. Review the dependency before building.');
    }
    source = source.replace(original, replacement);
    // HEIC and TIFF are converted on the server. Render their filenames in the
    // composer because many browsers cannot display previews for these formats.
    const heicChanges = [
        ['if(e.name.endsWith(o))return!0;', 'if(e.name.toLowerCase().endsWith(o.toLowerCase()))return!0;'],
        ['var t=e.type;return t.startsWith(V)?V:t.startsWith(j)?j:Zt;',
            'if(/\\.(heic|heif|tif|tiff)$/i.test(e.name||"")||/^image\\/(hei[cf](?:-sequence)?|(?:x-)?tiff)$/i.test(e.type||""))return Zt;var t=e.type;return t.startsWith(V)?V:t.startsWith(j)?j:Zt;'],
        // Websocket-mode callbacks clone message objects and lose File refs.
        // Include accepted photo bytes even when the browser has no MIME type.
        ['if(!n.type||n.type===Zt){var r=n[ee].name||ee;',
            'if((!n.type||n.type===Zt)&&!(/\\.(jpg|jpeg|jpe|jfif|png|gif|bmp|dib|heic|heif|webp|avif|tif|tiff)$/i.test(n[ee].name||"")||/^image\\/(hei[cf](?:-sequence)?|(?:x-)?tiff)$/i.test(n[ee].type||""))){var r=n[ee].name||ee;'],
    ];
    for (const [before, after] of heicChanges) {
        if (source.split(before).length !== 2) {
            throw new Error('Deep Chat HEIC attachment override no longer matches. Review the dependency before building.');
        }
        source = source.replace(before, after);
    }
    return source;
}

export default function deepChatImageLoadFix() {
    return {
        name: 'deep-chat-image-load-fix',
        enforce: 'pre',
        transform(source, id) {
            if (!id.replaceAll('\\', '/').split('?')[0].endsWith('/node_modules/deep-chat/dist/deepChat.js')) return null;
            return { code: fixDeepChatImageLoad(source), map: null };
        },
    };
}
