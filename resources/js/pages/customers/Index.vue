<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Search, UsersRound } from '@lucide/vue';
import { ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type CustomerRow = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    visits: number;
    no_shows: number;
    last_seen_at: string | null;
    next_at: string | null;
};

const props = defineProps<{
    customers: {
        data: CustomerRow[];
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { search: string; sort: 'recent' | 'name' | 'visits' };
    /** Everyone on file, before any search. */
    total: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Customers', href: route('customers.index') }],
    },
});

const search = ref(props.filters.search);
const sort = ref(props.filters.sort);
let timer = 0;

function load(): void {
    router.get(
        route('customers.index'),
        {
            ...(search.value.trim() ? { search: search.value.trim() } : {}),
            ...(sort.value !== 'recent' ? { sort: sort.value } : {}),
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

// Searching as you type, a beat after the last key.
watch(search, () => {
    window.clearTimeout(timer);
    timer = window.setTimeout(load, 300);
});
watch(sort, load);

const day = new Intl.DateTimeFormat(undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

const dayShort = new Intl.DateTimeFormat(undefined, {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
});
</script>

<template>
    <Head title="Customers" />

    <div class="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-6">
        <Heading
            title="Customers"
            :description="
                total === 1
                    ? 'One person has booked with you.'
                    : `${total} people have booked with you.`
            "
        />

        <div class="flex flex-wrap items-center gap-2">
            <div class="relative min-w-0 flex-1">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <label for="customer-search" class="sr-only"
                    >Find a customer</label
                >
                <Input
                    id="customer-search"
                    v-model="search"
                    type="search"
                    placeholder="Name, email or phone number"
                    class="pl-9"
                    autocomplete="off"
                    data-test="customer-search"
                />
            </div>
            <label class="flex items-center gap-2 text-sm">
                <span class="text-muted-foreground">Sort by</span>
                <select
                    v-model="sort"
                    class="h-9 rounded-md border border-input bg-transparent px-2.5 text-sm shadow-xs"
                    data-test="customer-sort"
                >
                    <option value="recent">Last seen</option>
                    <option value="name">Name</option>
                    <option value="visits">Most visits</option>
                </select>
            </label>
        </div>

        <div
            v-if="customers.data.length === 0"
            class="flex flex-col items-center rounded-xl border border-dashed px-6 py-16 text-center"
        >
            <span class="grid size-12 place-items-center rounded-full bg-muted">
                <UsersRound class="size-5 text-muted-foreground" />
            </span>
            <template v-if="filters.search">
                <h2 class="mt-4 text-lg font-semibold">No one matches</h2>
                <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                    Nobody on file has a name, email or phone number like “{{
                        filters.search
                    }}”.
                </p>
            </template>
            <template v-else>
                <h2 class="mt-4 text-lg font-semibold">No customers yet</h2>
                <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                    Everyone who books, online or by phone, is added here with
                    their booking history.
                </p>
            </template>
        </div>

        <ul v-else class="divide-y rounded-xl border" data-test="customers">
            <li v-for="customer in customers.data" :key="customer.id">
                <Link
                    :href="route('customers.show', customer.id)"
                    class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3.5 transition-colors hover:bg-muted/50 md:px-5"
                >
                    <span class="min-w-0 flex-1">
                        <span
                            class="flex flex-wrap items-center gap-2 font-semibold"
                        >
                            {{ customer.name }}
                            <Badge
                                v-if="customer.no_shows > 0"
                                variant="outline"
                                class="border-destructive/40 text-destructive"
                                >{{
                                    customer.no_shows === 1
                                        ? '1 no-show'
                                        : `${customer.no_shows} no-shows`
                                }}</Badge
                            >
                        </span>
                        <span
                            class="block truncate text-sm text-muted-foreground"
                        >
                            {{
                                [customer.phone, customer.email]
                                    .filter(Boolean)
                                    .join(' · ') || 'No contact details'
                            }}
                        </span>
                    </span>
                    <span
                        class="flex shrink-0 flex-col items-end text-right text-sm"
                    >
                        <span class="tabular-nums">
                            {{
                                customer.visits === 1
                                    ? '1 visit'
                                    : `${customer.visits} visits`
                            }}
                        </span>
                        <span class="text-xs text-muted-foreground">
                            <template v-if="customer.next_at"
                                >Next
                                {{
                                    dayShort.format(new Date(customer.next_at))
                                }}</template
                            >
                            <template v-else-if="customer.last_seen_at"
                                >Last in
                                {{
                                    day.format(new Date(customer.last_seen_at))
                                }}</template
                            >
                            <template v-else>Not been in yet</template>
                        </span>
                    </span>
                </Link>
            </li>
        </ul>

        <nav
            v-if="customers.last_page > 1"
            class="flex items-center justify-between gap-3 text-sm"
            aria-label="Pages"
        >
            <p class="text-muted-foreground">
                {{ customers.from }}–{{ customers.to }} of
                {{ customers.total }}
            </p>
            <div class="flex gap-2">
                <Button
                    v-if="customers.prev_page_url"
                    as-child
                    variant="outline"
                    size="sm"
                >
                    <Link
                        :href="customers.prev_page_url"
                        preserve-scroll
                        data-test="prev-page"
                        ><ChevronLeft class="size-4" />Previous</Link
                    >
                </Button>
                <Button
                    v-if="customers.next_page_url"
                    as-child
                    variant="outline"
                    size="sm"
                >
                    <Link :href="customers.next_page_url" data-test="next-page"
                        >Next<ChevronRight class="size-4"
                    /></Link>
                </Button>
            </div>
        </nav>
    </div>
</template>
