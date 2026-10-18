<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { TriangleAlert, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { timeIn, zoneCity } from '@/lib/booking';
import { formatDate } from '@/lib/dates';
import { formatMoney } from '@/lib/money';
import type { NewBookingOptions, NewBookingPreset } from '@/types/calendar';

/**
 * Adding a booking by hand, for someone who phoned or walked in. The free
 * times are suggested, but any time can be typed: the server refuses one
 * that overlaps another booking or time off.
 */
const props = defineProps<{
    options: NewBookingOptions;
    preset: NewBookingPreset;
    timezone: string;
    today: string;
}>();

const emit = defineEmits<{ done: [] }>();

type Customer = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
};

// Mounted afresh each time the panel opens, filled in from where it was
// opened (a day, a column, a time).
const form = useForm({
    service: null as number | null,
    staff: props.options.staff.some((m) => m.id === props.preset.staffId)
        ? props.preset.staffId
        : props.options.staff.length === 1
          ? props.options.staff[0].id
          : null,
    date: props.preset.date < props.today ? props.today : props.preset.date,
    time: props.preset.time ?? '',
    customer_id: null as number | null,
    name: '',
    email: '',
    phone: '',
    notes: '',
    tell_customer: true,
});

const picked = ref<Customer | null>(null);
const suggestions = ref<Customer[]>([]);
const active = ref(-1);

const service = computed(
    () => props.options.services.find((s) => s.id === form.service) ?? null,
);

/** The people this person can book in who do the chosen service. */
const staffOptions = computed(() =>
    service.value
        ? props.options.staff.filter((m) =>
              service.value!.staff_ids.includes(m.id),
          )
        : props.options.staff,
);

watch(
    () => form.service,
    () => {
        if (!staffOptions.value.some((m) => m.id === form.staff)) {
            form.staff =
                staffOptions.value.length === 1
                    ? staffOptions.value[0].id
                    : null;
        }
    },
);

const staffName = computed(
    () => props.options.staff.find((m) => m.id === form.staff)?.name ?? '',
);

// Free times and working hours for the chosen service, person and day.
const times = ref<string[]>([]);
const hours = ref<[number, number][] | null>(null);
const loadingTimes = ref(false);
let timesRequest: AbortController | null = null;

const clock = computed(
    () =>
        new Intl.DateTimeFormat('en-GB', {
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
            timeZone: props.timezone,
        }),
);

watch(
    () => [form.service, form.staff, form.date] as const,
    async ([serviceId, staffId, date]) => {
        timesRequest?.abort();
        times.value = [];
        hours.value = null;

        if (!serviceId || !staffId || !date) {
            return;
        }

        timesRequest = new AbortController();
        loadingTimes.value = true;

        try {
            const response = await fetch(
                route('bookings.times', {
                    service: serviceId,
                    staff: staffId,
                    date,
                }),
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal: timesRequest.signal,
                },
            );

            if (response.ok) {
                const data = (await response.json()) as {
                    times: string[];
                    hours: [number, number][];
                };
                times.value = data.times;
                hours.value = data.hours;
            }
        } catch {
            // A newer choice replaced this request, or the network dropped:
            // the time can still be typed.
        } finally {
            loadingTimes.value = false;
        }
    },
);

const slots = computed(() =>
    times.value.map((instant) => ({
        value: clock.value.format(new Date(instant)),
        label: timeIn(instant, props.timezone),
    })),
);

/** Booking outside working hours is allowed, but worth a second look. */
const outsideHours = computed(() => {
    if (!form.time || !service.value || hours.value === null) {
        return null;
    }

    const [h, m] = form.time.split(':').map(Number);
    const start = h * 60 + m;
    const end = start + service.value.duration;

    if (hours.value.length === 0) {
        return `${staffName.value} doesn't usually work on ${formatDate(form.date, { weekday: 'long' })}s.`;
    }

    return hours.value.some(([from, to]) => from <= start && end <= to)
        ? null
        : `That's outside ${staffName.value}'s working hours.`;
});

// Customers already on file, found as the name is typed.
let searchTimer = 0;
let searchRequest: AbortController | null = null;

watch(
    () => form.name,
    (name) => {
        window.clearTimeout(searchTimer);

        if (picked.value || name.trim().length < 2) {
            suggestions.value = [];

            return;
        }

        searchTimer = window.setTimeout(async () => {
            searchRequest?.abort();
            searchRequest = new AbortController();

            try {
                const response = await fetch(
                    route('customers.search', { search: name.trim() }),
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: searchRequest.signal,
                    },
                );
                suggestions.value = response.ok ? await response.json() : [];
                active.value = -1;
            } catch {
                // Superseded by a newer search.
            }
        }, 250);
    },
);

function pick(customer: Customer): void {
    picked.value = customer;
    form.customer_id = customer.id;
    suggestions.value = [];
}

function unpick(): void {
    picked.value = null;
    form.customer_id = null;
    form.name = '';
    form.email = '';
    form.phone = '';
}

function move(step: number): void {
    if (suggestions.value.length > 0) {
        active.value =
            (active.value + step + suggestions.value.length) %
            suggestions.value.length;
    }
}

function chooseActive(event: KeyboardEvent): void {
    if (active.value >= 0 && suggestions.value[active.value]) {
        event.preventDefault();
        pick(suggestions.value[active.value]);
    }
}

const emailTo = computed(() => picked.value?.email ?? form.email.trim());
const firstName = computed(
    () =>
        (picked.value?.name ?? form.name).trim().split(' ')[0] ||
        'the customer',
);

function submit(): void {
    form.transform((data) => ({
        ...data,
        tell_customer: data.tell_customer && emailTo.value !== '',
    })).post(route('bookings.store'), {
        preserveScroll: true,
        onSuccess: () => emit('done'),
    });
}

const dayLabel = computed(() =>
    form.date ? formatDate(form.date, { weekday: 'long' }) : '',
);
</script>

<template>
    <form
        class="space-y-5 px-4 pb-6"
        data-test="new-booking"
        @submit.prevent="submit"
    >
        <div class="grid gap-2">
            <Label for="nb-service">Service</Label>
            <select
                id="nb-service"
                v-model="form.service"
                class="h-9 w-full rounded-md border border-input bg-transparent px-2.5 text-sm shadow-xs"
                data-test="nb-service"
            >
                <option :value="null" disabled>Pick a service</option>
                <option
                    v-for="option in options.services"
                    :key="option.id"
                    :value="option.id"
                >
                    {{ option.name }} · {{ option.duration }} min
                </option>
            </select>
            <InputError :message="form.errors.service" />
        </div>

        <div class="grid gap-2">
            <Label for="nb-staff">With</Label>
            <select
                id="nb-staff"
                v-model="form.staff"
                class="h-9 w-full rounded-md border border-input bg-transparent px-2.5 text-sm shadow-xs"
                data-test="nb-staff"
            >
                <option :value="null" disabled>Pick someone</option>
                <option
                    v-for="member in staffOptions"
                    :key="member.id"
                    :value="member.id"
                >
                    {{ member.name }}
                </option>
            </select>
            <p
                v-if="service && staffOptions.length === 0"
                class="text-sm text-muted-foreground"
            >
                Nobody you can book for does {{ service.name }}.
            </p>
            <InputError :message="form.errors.staff" />
        </div>

        <div class="grid gap-2">
            <Label for="nb-date">Day</Label>
            <Input
                id="nb-date"
                v-model="form.date"
                type="date"
                :min="today"
                data-test="nb-date"
            />
            <InputError :message="form.errors.date" />
        </div>

        <fieldset class="grid gap-2">
            <legend class="mb-2 text-sm font-medium">
                Time
                <span class="font-normal text-muted-foreground"
                    >· {{ zoneCity(timezone) }}</span
                >
            </legend>
            <div v-if="service && form.staff">
                <p v-if="loadingTimes" class="text-sm text-muted-foreground">
                    Finding free times…
                </p>
                <p
                    v-else-if="slots.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No free times left on {{ dayLabel }}. You can still type
                    one.
                </p>
                <div v-else class="flex flex-wrap gap-1.5" data-test="nb-times">
                    <button
                        v-for="slot in slots"
                        :key="slot.value"
                        type="button"
                        class="rounded-full border px-2.5 py-1 text-xs tabular-nums transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        :class="
                            form.time === slot.value
                                ? 'border-foreground bg-foreground text-background'
                                : 'hover:bg-muted'
                        "
                        :aria-pressed="form.time === slot.value"
                        @click="form.time = slot.value"
                    >
                        {{ slot.label }}
                    </button>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Label
                    for="nb-time"
                    class="text-sm font-normal text-muted-foreground"
                    >Or any time</Label
                >
                <Input
                    id="nb-time"
                    v-model="form.time"
                    type="time"
                    step="300"
                    class="w-32"
                    data-test="nb-time"
                />
            </div>
            <p
                v-if="outsideHours"
                class="flex items-start gap-1.5 text-sm text-muted-foreground"
                data-test="nb-outside-hours"
            >
                <TriangleAlert class="mt-0.5 size-4 shrink-0 text-sun-deep" />
                {{ outsideHours }} You can still book it.
            </p>
            <InputError :message="form.errors.time" />
        </fieldset>

        <section class="grid gap-3 border-t pt-4">
            <h3 class="text-sm font-semibold">
                Customer
                <span class="font-normal text-muted-foreground"
                    >· optional for a walk-in</span
                >
            </h3>

            <div
                v-if="picked"
                class="flex items-start justify-between gap-3 rounded-xl bg-muted px-3 py-2.5 text-sm"
                data-test="nb-picked"
            >
                <div class="min-w-0">
                    <p class="font-medium">{{ picked.name }}</p>
                    <p class="truncate text-muted-foreground">
                        {{
                            [picked.phone, picked.email]
                                .filter(Boolean)
                                .join(' · ') || 'No contact details'
                        }}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-7 shrink-0"
                    aria-label="Choose a different customer"
                    @click="unpick"
                >
                    <X class="size-4" />
                </Button>
            </div>

            <template v-else>
                <div class="relative grid gap-2">
                    <Label for="nb-name">Name</Label>
                    <Input
                        id="nb-name"
                        v-model="form.name"
                        autocomplete="off"
                        role="combobox"
                        aria-autocomplete="list"
                        aria-controls="nb-suggestions"
                        :aria-expanded="suggestions.length > 0"
                        :aria-activedescendant="
                            active >= 0
                                ? `nb-customer-${suggestions[active]?.id}`
                                : undefined
                        "
                        placeholder="Start typing to find someone on file"
                        data-test="nb-name"
                        @keydown.down.prevent="move(1)"
                        @keydown.up.prevent="move(-1)"
                        @keydown.enter="chooseActive"
                        @keydown.escape="suggestions = []"
                        @blur="suggestions = []"
                    />
                    <ul
                        v-if="suggestions.length > 0"
                        id="nb-suggestions"
                        role="listbox"
                        class="absolute top-full z-10 mt-1 w-full overflow-hidden rounded-md border bg-popover p-1 shadow-md"
                        data-test="nb-suggestions"
                    >
                        <li
                            v-for="(customer, index) in suggestions"
                            :id="`nb-customer-${customer.id}`"
                            :key="customer.id"
                            role="option"
                            :aria-selected="index === active"
                            class="flex cursor-pointer items-baseline justify-between gap-3 rounded px-2 py-1.5 text-sm"
                            :class="index === active && 'bg-accent'"
                            @mousedown.prevent="pick(customer)"
                        >
                            <span class="truncate font-medium">{{
                                customer.name
                            }}</span>
                            <span
                                class="shrink-0 truncate text-xs text-muted-foreground"
                                >{{ customer.phone ?? customer.email }}</span
                            >
                        </li>
                    </ul>
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="nb-phone">Phone</Label>
                        <Input
                            id="nb-phone"
                            v-model="form.phone"
                            type="tel"
                            autocomplete="off"
                            data-test="nb-phone"
                        />
                        <InputError :message="form.errors.phone" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="nb-email">Email</Label>
                        <Input
                            id="nb-email"
                            v-model="form.email"
                            type="email"
                            autocomplete="off"
                            data-test="nb-email"
                        />
                        <InputError :message="form.errors.email" />
                    </div>
                </div>
            </template>
            <InputError :message="form.errors.customer_id" />

            <label v-if="emailTo" class="flex items-center gap-2 text-sm">
                <Checkbox v-model="form.tell_customer" data-test="nb-tell" />
                Email {{ firstName }} a confirmation
            </label>
        </section>

        <div class="grid gap-2">
            <Label for="nb-notes"
                >Notes
                <span class="font-normal text-muted-foreground"
                    >· optional</span
                ></Label
            >
            <textarea
                id="nb-notes"
                v-model="form.notes"
                rows="2"
                maxlength="500"
                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
            />
            <InputError :message="form.errors.notes" />
        </div>

        <div class="flex items-center justify-between gap-3 border-t pt-4">
            <p class="text-sm text-muted-foreground">
                <template v-if="service">
                    {{ formatMoney(service.price, options.currency) }}
                    on the day
                    <template v-if="form.time && form.date">
                        ·
                        {{
                            formatDate(form.date, {
                                weekday: 'short',
                                day: 'numeric',
                                month: 'short',
                            })
                        }}</template
                    >
                </template>
            </p>
            <Button
                type="submit"
                :disabled="form.processing"
                data-test="nb-submit"
                >Add booking</Button
            >
        </div>
    </form>
</template>
