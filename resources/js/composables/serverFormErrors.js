// VueForm editors use Laravel validation, including errors on nested elements.
export function clearServerFormErrors(form) {
    const clear = (element) => {
        element.messageBag?.clear();
        Object.values(element.children$ ?? {}).forEach(clear);
    };

    form.messageBag?.clear();
    Object.values(form.elements$ ?? {}).forEach(clear);
}

export function showServerFormErrors(response, form) {
    clearServerFormErrors(form);
    Object.entries(response?.data?.errors ?? {}).forEach(([name, messages]) => {
        const field = name.replace(/^settings\./, '');
        const element = form.el$(field) ?? form.el$(field.split('.')[0]);
        const message = Array.isArray(messages) ? messages[0] : messages;
        (element?.messageBag ?? form.messageBag)?.append(message);
    });
}
