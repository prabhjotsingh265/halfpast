<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarPlus, Mail, Pencil, Phone } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import AddSheet from '@/components/calendar/AddSheet.vue';
import BookingSheet from '@/components/calendar/BookingSheet.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dateIn, hourIn, timeIn } from '@/lib/booking';
import { statusLabel } from '@/lib/calendar';
import { formatDate } from '@/lib/dates';
import { daylight } from '@/lib/daylight';
import { formatMoney } from '@/lib/money';
import type {
    CalendarBooking,
    NewBookingOptions,
    NewBookingPreset,
} from '@/types/calendar';

type HistoryBooking = CalendarBooking & { staff_name: string | null };

const props = defineProps<{
    customer: {
        id: number;
        name: string;
        email: string | null;
        phone: string | null;
        notes: string | null;
        since: string | null;
        /** Their details were removed at their request. */
        forgotten: boolean;
    };
    stats: {
        visits: number;
        upcoming: number;
        noShows: number;
        cancelled: number;
        depositsKept: number;
        currency: string;
    };
    bookings: HistoryBooking[];
    timezone: string;
    today: string;
    newBooking: NewBookingOptions;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Customers', href: route('customers.index') }],
    },
});

const form = useForm({
    name: props.customer.name,
    email: props.customer.email ?? '',
    phone: props.customer.phone ?? '',
    notes: props.customer.notes ?? '',
});

const editing = ref(false);

function save(): void {
    form.put(route('customers.update', props.customer.id), {
        preserveScroll: true,
        onSuccess: () => (editing.value = false),
    });
}

function cancelEdit(): void {
    form.name = props.customer.name;
    form.email = props.customer.email ?? '';
    form.phone = props.customer.phone ?? '';
    form.clearErrors();
    editing.value = false;
}

const notesChanged = computed(
    () => form.notes.trim() !== (props.customer.notes ?? '').trim(),
);

// Coming up first (soonest at the top), then everything before, newest first.
const upcoming = computed(() =>
    props.bookings
        .filter(
            (b) =>
                b.status === 'confirmed' &&
                new Date(b.starts_at).getTime() > Date.now(),
        )
        .reverse(),
);
const past = computed(() =>
    props.bookings.filter((b) => !upcoming.value.includes(b)),
);

const since = computed(() =>
    props.customer.since
        ? new Intl.DateTimeFormat(undefined, {
              month: 'long',
              year: 'numeric',
          }).format(new Date(props.customer.since))
        : null,
);

function tint(booking: CalendarBooking) {
    const hour = hourIn(booking.starts_at, props.timezone);

    return { '--tint': daylight(hour), '--tint-dark': daylight(hour, 'dark') };
}

// The booking panel, as on the calendar.
const selected = ref<CalendarBooking | null>(null);
const sheetOpen = ref(false);

function open(booking: CalendarBooking): void {
    selected.value = booking;
    sheetOpen.value = true;
}

watch(
    () => props.bookings,
    (bookings) => {
        const fresh = bookings.find((b) => b.id === selected.value?.id);

        if (fresh) {
            selected.value = fresh;
        }
    },
);

const staffName = computed(
    () =>
        props.bookings.find((b) => b.id === selected.value?.id)?.staff_name ??
        null,
);

// Booking them again, with their details already filled in.
const adding = ref(false);
const preset = computed<NewBookingPreset>(() => ({
    date: props.today,
    staffId: null,
    time: null,
    customer: {
        id: props.customer.id,
        name: props.customer.name,
        email: props.customer.email,
        phone: props.customer.phone,
    },
}));

const confirmingRemove = ref(false);

function remove(): void {
    router.delete(route('customers.destroy', props.customer.id));
}
</script>

<template>
    <Head :title="customer.name" />

    <div class="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-6">
        <!-- Who they are. -->
        <section class="flex flex-wrap items-start justify-between gap-4">
            <div v-if="!editing" class="min-w-0 space-y-1">
                <h1 class="text-2xl font-semibold" data-test="customer-name">
                    {{ customer.name }}
                </h1>
                <p
                    v-if="customer.forgotten"
                    class="text-sm text-muted-foreground"
                >
                    Their details were removed at their request.
                </p>
                <template v-else>
                    <p v-if="since" class="text-sm text-muted-foreground">
                        Customer since {{ since }}
                    </p>
                    <div
                        class="flex flex-wrap gap-x-4 gap-y-1 pt-1 text-sm text-muted-foreground"
                    >
                        <a
                            v-if="customer.phone"
                            :href="`tel:${customer.phone.replace(/\s/g, '')}`"
                            class="flex items-center gap-1.5 hover:text-foreground"
                            ><Phone class="size-4" />{{ customer.phone }}</a
                        >
                        <a
                            v-if="customer.email"
                            :href="`mailto:${customer.email}`"
                            class="flex items-center gap-1.5 hover:text-foreground"
                            ><Mail class="size-4" />{{ customer.email }}</a
                        >
                        <span v-if="!customer.phone && !customer.email"
                            >No contact details</span
                        >
                    </div>
                </template>
            </div>

            <form
                v-else
                class="grid w-full gap-3 rounded-xl border p-4 sm:grid-cols-3"
                data-test="edit-customer"
                @submit.prevent="save"
            >
                <div class="grid gap-2 sm:col-span-3">
                    <Label for="c-name">Name</Label>
                    <Input id="c-name" v-model="form.name" maxlength="120" />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="c-phone">Phone</Label>
                    <Input id="c-phone" v-model="form.phone" type="tel" />
                    <InputError :message="form.errors.phone" />
                </div>
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="c-email">Email</Label>
                    <Input id="c-email" v-model="form.email" type="email" />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="flex justify-end gap-2 sm:col-span-3">
                    <Button type="button" variant="ghost" @click="cancelEdit"
                        >Cancel</Button
                    >
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        data-test="save-customer"
                        >Save</Button
                    >
                </div>
            </form>

            <div
                v-if="!editing && !customer.forgotten"
                class="flex shrink-0 gap-2"
            >
                <Button
                    variant="outline"
                    data-test="edit-customer-button"
                    @click="editing = true"
                >
                    <Pencil class="size-4" />Edit
                </Button>
                <Button
                    v-if="newBooking.staff.length > 0"
                    data-test="book-again"
                    @click="adding = true"
                >
                    <CalendarPlus class="size-4" />Book again
                </Button>
            </div>
        </section>

        <!-- At a glance. -->
        <dl
            class="grid grid-cols-2 gap-3 sm:grid-cols-5"
            data-test="customer-stats"
        >
            <div class="rounded-xl border bg-card p-3">
                <dt class="text-xs text-muted-foreground">Visits</dt>
                <dd class="mt-0.5 text-2xl font-semibold tabular-nums">
                    {{ stats.visits }}
                </dd>
            </div>
            <div class="rounded-xl border bg-card p-3">
                <dt class="text-xs text-muted-foreground">Coming up</dt>
                <dd class="mt-0.5 text-2xl font-semibold tabular-nums">
                    {{ stats.upcoming }}
                </dd>
            </div>
            <div class="rounded-xl border bg-card p-3">
                <dt class="text-xs text-muted-foreground">No-shows</dt>
                <dd
                    class="mt-0.5 text-2xl font-semibold tabular-nums"
                    :class="stats.noShows > 0 && 'text-destructive'"
                >
                    {{ stats.noShows }}
                </dd>
            </div>
            <div class="rounded-xl border bg-card p-3">
                <dt class="text-xs text-muted-foreground">Cancelled</dt>
                <dd class="mt-0.5 text-2xl font-semibold tabular-nums">
                    {{ stats.cancelled }}
                </dd>
            </div>
            <div class="col-span-2 rounded-xl border bg-card p-3 sm:col-span-1">
                <dt class="text-xs text-muted-foreground">Deposits kept</dt>
                <dd class="mt-0.5 text-2xl font-semibold tabular-nums">
                    {{ formatMoney(stats.depositsKept, stats.currency) }}
                </dd>
            </div>
        </dl>

        <!-- Notes only the business sees. -->
        <form
            v-if="!customer.forgotten"
            class="space-y-2"
            data-test="customer-notes"
            @submit.prevent="save"
        >
            <Label for="c-notes"
                >Notes
                <span class="font-normal text-muted-foreground"
                    >· only your team sees these</span
                ></Label
            >
            <textarea
                id="c-notes"
                v-model="form.notes"
                rows="3"
                maxlength="2000"
                placeholder="Allergies, preferences, anything worth remembering"
                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
            />
            <InputError :message="form.errors.notes" />
            <div class="flex justify-end">
                <Button
                    type="submit"
                    size="sm"
                    variant="outline"
                    :disabled="!notesChanged || form.processing"
                    data-test="save-notes"
                    >Save notes</Button
                >
            </div>
        </form>

        <!-- Their bookings. -->
        <section
            v-for="group in [
                { title: 'Coming up', items: upcoming },
                { title: 'Before', items: past },
            ]"
            v-show="group.items.length > 0"
            :key="group.title"
            :data-test="`history-${group.title === 'Coming up' ? 'upcoming' : 'past'}`"
        >
            <h2 class="mb-2 text-sm font-semibold">{{ group.title }}</h2>
            <ul class="divide-y overflow-hidden rounded-xl border bg-card">
                <li v-for="booking in group.items" :key="booking.id">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 px-3 py-3 text-left transition-colors hover:bg-muted/60 focus-visible:bg-muted/60 focus-visible:outline-none sm:px-4"
                        @click="open(booking)"
                    >
                        <span
                            class="w-[6.5rem] shrink-0 rounded-md bg-[var(--tint)] px-2 py-1 text-xs tabular-nums dark:bg-[var(--tint-dark)]"
                            :style="tint(booking)"
                        >
                            <span class="block font-semibold">{{
                                formatDate(
                                    dateIn(booking.starts_at, timezone),
                                    {
                                        day: 'numeric',
                                        month: 'short',
                                        year: 'numeric',
                                    },
                                )
                            }}</span>
                            <span class="block text-foreground/70">{{
                                timeIn(booking.starts_at, timezone)
                            }}</span>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium">{{
                                booking.service_name
                            }}</span>
                            <span
                                class="block truncate text-sm text-muted-foreground"
                                >{{
                                    booking.staff_name
                                        ? `with ${booking.staff_name}`
                                        : 'With anyone'
                                }}<template v-if="booking.source === 'manual'">
                                    · booked by you</template
                                ></span
                            >
                        </span>
                        <span
                            class="flex shrink-0 flex-col items-end gap-1 text-right"
                        >
                            <Badge
                                v-if="booking.status !== 'confirmed'"
                                :variant="
                                    booking.status === 'no_show' ||
                                    booking.status === 'cancelled'
                                        ? 'outline'
                                        : 'secondary'
                                "
                                :class="
                                    booking.status === 'no_show' &&
                                    'border-destructive/40 text-destructive'
                                "
                                >{{ statusLabel[booking.status] }}</Badge
                            >
                            <span class="text-sm tabular-nums">{{
                                formatMoney(booking.price, booking.currency)
                            }}</span>
                        </span>
                    </button>
                </li>
            </ul>
        </section>

        <p
            v-if="bookings.length === 0"
            class="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground"
        >
            No bookings yet.
        </p>

        <!-- Privacy: removing them on request. -->
        <section
            v-if="!customer.forgotten"
            class="rounded-xl border border-destructive/30 p-4"
            data-test="forget"
        >
            <h2 class="text-sm font-semibold">Remove their details</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                If they ask you to forget them: their name, contact details and
                notes are removed. Their past bookings stay in your records,
                without anything personal. This can't be undone.
            </p>
            <div class="mt-3">
                <Button
                    v-if="!confirmingRemove"
                    variant="outline"
                    class="text-destructive hover:text-destructive"
                    data-test="forget-button"
                    @click="confirmingRemove = true"
                    >Remove their details</Button
                >
                <div v-else class="flex flex-wrap items-center gap-2">
                    <span class="text-sm">Remove {{ customer.name }}?</span>
                    <Button variant="ghost" @click="confirmingRemove = false"
                        >Keep them</Button
                    >
                    <Button
                        variant="destructive"
                        data-test="forget-confirm"
                        @click="remove"
                        >Remove</Button
                    >
                </div>
            </div>
        </section>

        <BookingSheet
            v-model:open="sheetOpen"
            :booking="selected"
            :timezone="timezone"
            :staff-name="staffName"
        />

        <AddSheet
            v-if="newBooking.staff.length > 0 && !customer.forgotten"
            v-model:open="adding"
            :options="newBooking"
            :preset="preset"
            :timezone="timezone"
            :today="today"
        />
    </div>
</template>
