<template>
    <div ref="root" class="relative">
        <input
            ref="input"
            :value="modelValue"
            :disabled="disabled"
            type="text"
            placeholder="parameter-name"
            spellcheck="false"
            autocomplete="off"
            class="block w-full rounded-md border-0 py-1.5 pr-8 font-mono text-sm text-heading ring-1 ring-inset focus:ring-2 focus:ring-inset focus:ring-focus disabled:bg-surface-2 disabled:text-muted"
            :class="error ? 'ring-danger' : 'ring-strong'"
            @input="onInput"
            @focus="onFocus"
            @blur="open = false"
            @keydown.down.prevent="move(1)"
            @keydown.up.prevent="move(-1)"
            @keydown.enter.prevent="chooseHighlighted"
            @keydown.esc="open = false"
        />
        <button
            type="button"
            :disabled="disabled"
            class="absolute inset-y-0 right-0 flex items-center pr-2 text-subtle hover:text-body disabled:opacity-50"
            tabindex="-1"
            @mousedown.prevent="toggle"
        >
            <ChevronUpDownIcon class="h-4 w-4" />
        </button>

        <Teleport to="body">
            <div
                v-if="open && groups.length"
                class="z-50 max-h-72 overflow-auto rounded-md bg-surface py-1 text-sm shadow-lg ring-1 ring-black/5"
                :style="menuStyle"
            >
                <template v-for="group in groups" :key="group.name">
                    <div class="px-3 pb-0.5 pt-2 text-xs font-semibold uppercase tracking-wide text-subtle">{{ group.name }}</div>
                    <button
                        v-for="opt in group.items"
                        :key="opt.name"
                        type="button"
                        class="flex w-full items-center justify-between gap-2 px-3 py-1.5 text-left"
                        :class="opt.name === highlighted ? 'bg-accent-subtle text-accent-fg' : 'text-heading hover:bg-surface-2'"
                        @mousemove="highlighted = opt.name"
                        @mousedown.prevent="select(opt.name)"
                    >
                        <span class="font-mono">{{ opt.name }}</span>
                        <CheckIcon v-if="opt.name === modelValue" class="h-4 w-4 shrink-0 text-accent-fg" />
                    </button>
                </template>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from "vue";
import { ChevronUpDownIcon, CheckIcon } from "@heroicons/vue/20/solid";
import { SOFIA_SIP_SETTINGS, SIP_SETTING_GROUPS } from "../../data/sofiaSipProfileSettings";

const props = defineProps({
    modelValue: { type: String, default: "" },
    disabled: { type: Boolean, default: false },
    error: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);

const root = ref(null);
const input = ref(null);
const open = ref(false);
const highlighted = ref("");
const menuStyle = ref({});

const groups = computed(() => {
    const needle = (props.modelValue || "").trim().toLowerCase();
    const matches = SOFIA_SIP_SETTINGS.filter((def) => !needle || def.name.toLowerCase().includes(needle));

    return SIP_SETTING_GROUPS
        .map((name) => ({ name, items: matches.filter((def) => def.group === name) }))
        .filter((group) => group.items.length > 0);
});

const flatItems = computed(() => groups.value.flatMap((group) => group.items.map((item) => item.name)));

function updatePosition() {
    const el = input.value;
    if (!el) return;

    const rect = el.getBoundingClientRect();
    const width = Math.max(rect.width, 240);
    const left = Math.min(rect.left, window.innerWidth - width - 8);
    const spaceBelow = window.innerHeight - rect.bottom;
    const maxHeight = 288; // matches max-h-72
    const openUp = spaceBelow < maxHeight && rect.top > spaceBelow;

    menuStyle.value = {
        position: "fixed",
        width: `${width}px`,
        left: `${Math.max(8, left)}px`,
        ...(openUp
            ? { bottom: `${window.innerHeight - rect.top + 4}px` }
            : { top: `${rect.bottom + 4}px` }),
    };
}

const reposition = () => updatePosition();

watch(open, (isOpen) => {
    if (isOpen) {
        nextTick(updatePosition);
        window.addEventListener("scroll", reposition, true);
        window.addEventListener("resize", reposition);
    } else {
        window.removeEventListener("scroll", reposition, true);
        window.removeEventListener("resize", reposition);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener("scroll", reposition, true);
    window.removeEventListener("resize", reposition);
});

function onInput(event) {
    emit("update:modelValue", event.target.value);
    open.value = true;
    highlighted.value = "";
    nextTick(updatePosition);
}

function onFocus() {
    open.value = true;
}

function select(name) {
    emit("update:modelValue", name);
    open.value = false;
}

function toggle() {
    open.value = !open.value;
    if (open.value) {
        nextTick(() => input.value?.focus());
    }
}

function move(step) {
    open.value = true;
    const items = flatItems.value;
    if (!items.length) return;

    const current = items.indexOf(highlighted.value);
    const next = current === -1 ? (step > 0 ? 0 : items.length - 1) : (current + step + items.length) % items.length;
    highlighted.value = items[next];
}

function chooseHighlighted() {
    if (highlighted.value) {
        select(highlighted.value);
    } else {
        open.value = false;
    }
}
</script>
