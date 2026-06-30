<template>
    <div class="flex items-center justify-between border-b border-t border-default bg-surface px-4 py-3 sm:px-6">
        <div class="mr-4">
             <BulkActions v-if="hasSelectedItems && hasBulkActions" :actions="bulkActions" @bulk-action="$emit('bulk-action', $event)"
                        :has-selected-items="hasSelectedItems" />
        </div>
       

        <div class="flex flex-1 items-center justify-end sm:hidden">
            <div v-if="showPageSizeSelector && pageSizeOptions.length" class="relative">
                <button type="button" aria-haspopup="listbox" :aria-expanded="pageSizeMenuOpen"
                    class="inline-flex min-w-20 items-center justify-between rounded-md bg-surface py-1.5 pl-2 pr-2 text-sm text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-focus"
                    @click.stop="togglePageSizeMenu">
                    <span>{{ pageSize }}</span>
                    <ChevronDownIcon class="ml-2 h-4 w-4 text-muted" aria-hidden="true" />
                </button>
                <div v-if="pageSizeMenuOpen"
                    class="absolute bottom-full right-0 z-50 mb-1 w-24 overflow-hidden rounded-md bg-surface py-1 shadow-lg ring-1 ring-black/5 dark:ring-white/10 focus:outline-none"
                    role="listbox" @click.stop>
                    <button v-for="option in pageSizeOptions" :key="option" type="button" role="option"
                        :aria-selected="Number(option) === Number(pageSize)"
                        :class="pageSizeOptionClass(option)"
                        @click="selectPageSize(option)">
                        {{ option }}
                    </button>
                </div>
            </div>
            <button type="button" @click="$emit('pagination-change-page', previous)"
                class="relative inline-flex items-center rounded-md bg-surface px-2.5 py-1.5 ml-2 sm:ml-4 text-sm text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2">
                Previous
            </button>
            <button type="button" @click="$emit('pagination-change-page', next)"
                class="relative ml-3 inline-flex items-center rounded-md bg-surface px-2.5 py-1.5 ml-2 sm:ml-4 text-sm text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2">
                Next
            </button>
        </div>
        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <p class="text-sm text-body">
                    Showing
                    {{ ' ' }}
                    <span class="font-medium">{{ from ?? 0 }}</span>
                    {{ ' ' }}
                    to
                    {{ ' ' }}
                    <span class="font-medium">{{ to ?? 0 }}</span>
                    {{ ' ' }}
                    of
                    {{ ' ' }}
                    <span class="font-medium">{{ total ?? 0 }}</span>
                    {{ ' ' }}
                    results
                </p>
                <div v-if="showPageSizeSelector && pageSizeOptions.length" class="relative">
                    <button type="button" aria-haspopup="listbox" :aria-expanded="pageSizeMenuOpen"
                        class="inline-flex min-w-20 items-center justify-between rounded-md bg-surface py-1.5 pl-2 pr-2 text-sm text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-focus"
                        @click.stop="togglePageSizeMenu">
                        <span>{{ pageSize }}</span>
                        <ChevronDownIcon class="ml-2 h-4 w-4 text-muted" aria-hidden="true" />
                    </button>
                    <div v-if="pageSizeMenuOpen"
                        class="absolute bottom-full left-0 z-50 mb-1 w-24 overflow-hidden rounded-md bg-surface py-1 shadow-lg ring-1 ring-black/5 dark:ring-white/10 focus:outline-none"
                        role="listbox" @click.stop>
                        <button v-for="option in pageSizeOptions" :key="option" type="button" role="option"
                            :aria-selected="Number(option) === Number(pageSize)"
                            :class="pageSizeOptionClass(option)"
                            @click="selectPageSize(option)">
                            {{ option }}
                        </button>
                    </div>
                </div>
            </div>
            <div>
                <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                    <button v-for="(link, index) in visibleLinks" :key="index" type="button"
                        @click="link.url ? $emit('pagination-change-page', link.url) : null"
                        :class="linkClass(index, link.active)">
                        <ChevronLeftIcon v-if="index === 0" class="h-5 w-5" aria-hidden="true" />
                        <ChevronRightIcon v-if="index === visibleLinks.length - 1" class="h-5 w-5" aria-hidden="true" />
                        <span v-if="index != 0 && index != visibleLinks.length - 1">{{ link.label }}</span>
                    </button>
                </nav>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { ChevronDownIcon, ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/20/solid';
import BulkActions from "./BulkActions.vue";

const props = defineProps({
    previous: String,
    next: String,
    from: Number,
    to: Number,
    total: Number,
    currentPage: Number,
    lastPage: Number,
    links: Array,
    filterData: Object,
    bulkActions: Array,
    hasSelectedItems: Boolean,
    pageSize: {
        type: Number,
        default: 50,
    },
    pageSizeOptions: {
        type: Array,
        default: () => [],
    },
    showPageSizeSelector: {
        type: Boolean,
        default: false,
    },
});
const emit = defineEmits(["pagination-change-page", "bulk-action", "page-size-change"]);

const hasBulkActions = computed(() => props.bulkActions && props.bulkActions.length > 0);
const pageSizeMenuOpen = ref(false);

const closePageSizeMenu = () => {
    pageSizeMenuOpen.value = false;
};

const togglePageSizeMenu = () => {
    pageSizeMenuOpen.value = !pageSizeMenuOpen.value;
};

const selectPageSize = (option) => {
    pageSizeMenuOpen.value = false;
    emit("page-size-change", Number(option));
};

const pageSizeOptionClass = (option) => [
    "block w-full px-3 py-1.5 text-left text-sm",
    Number(option) === Number(props.pageSize)
        ? "bg-accent text-on-accent"
        : "text-heading hover:bg-surface-3",
];

onMounted(() => {
    document.addEventListener("click", closePageSizeMenu);
});

onBeforeUnmount(() => {
    document.removeEventListener("click", closePageSizeMenu);
});

const getVisibleLinks = (links) => {
    const visiblePages = 5;
    // If the total number of pages is less than or equal to visiblePages, show all
    if (links.length <= visiblePages) {
        return links;
    }

    let pageSubset = [];

    // Always include the 'Previous' link
    pageSubset.push(links[0]);

    // Always include the first page link
    pageSubset.push(links[1]);

    // Determine the range of page numbers to display
    let startPageNumber = Math.max(props.currentPage - 2, 1);
    let endPageNumber = startPageNumber + visiblePages - 1;
    const lastPageNumber = parseInt(links[links.length - 2].label);

    // Adjust if the range extends beyond the last page number
    if (endPageNumber > lastPageNumber) {
        endPageNumber = lastPageNumber;
        startPageNumber = Math.max(lastPageNumber - visiblePages + 1, 1);
    }

    // Adjust startPageNumber to ensure that the first page is not repeated
    if (props.currentPage <= 4) {
        startPageNumber = 2;
    }

    // Slice the range of page number links to be displayed
    links.slice(2, -2).forEach((link, index) => {
        const pageNumber = parseInt(link.label);
        if (!isNaN(pageNumber)) {
            if (pageNumber >= startPageNumber && pageNumber <= endPageNumber) {
                pageSubset.push(link);
            }
        }
    });

    // Add ellipses if there are hidden pages on either side of the subset
    if (startPageNumber > 2) {
        pageSubset.splice(2, 0, { url: null, label: '...', active: false }); // position 2 to account for 'Previous' and first page
    }
    if (endPageNumber < lastPageNumber) {
        pageSubset.push({ url: null, label: '...', active: false });
    }

    // Always include the last page link
    pageSubset.push(links[links.length - 2]);

    // Always include the 'Next' link
    pageSubset.push(links[links.length - 1]);

    // console.log(pageSubset);

    return pageSubset;
};

// Compute the subset of links to display
const visibleLinks = computed(() => getVisibleLinks(props.links));

// This method returns the appropriate class for a link based on its type (previous, next, page number)
const linkClass = (index, isActive) => {
    if (index === 0) {
        return "relative inline-flex items-center rounded-l-md px-2 py-2 text-subtle ring-1 ring-inset ring-strong hover:bg-surface-2 focus:z-20 focus:outline-offset-0";
    } else if (index === visibleLinks.value.length - 1) {
        return "relative inline-flex items-center rounded-r-md px-2 py-2 text-subtle ring-1 ring-inset ring-strong hover:bg-surface-2 focus:z-20 focus:outline-offset-0";
    } else {
        return {
            'relative inline-flex items-center px-4 py-2 text-sm font-semibold focus:z-20 focus:outline-offset-0': true,
            'bg-surface hover:bg-surface-2': !isActive,
            'bg-accent text-on-accent': isActive,
            'text-heading ring-1 ring-inset ring-strong': !isActive
        };
    }
};


</script>
