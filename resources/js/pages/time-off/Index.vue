<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarOff, Store, Trash2 } from '@lucide/vue';
import { computed, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import MemberAvatar from '@/components/staff/MemberAvatar.vue';
import RangeCalendar from '@/components/time-off/RangeCalendar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { daysBetween, eachDate, formatDate } from '@/lib/dates';
import { clockLabel, toMinutes } from '@/lib/week';
import type { StaffOption } from '@/types/staff';
import type { CalendarMark, TimeOffEntry } from '@/types/time-off';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Time off', href: route('time-off.index') }],
    },
});

const props = defineProps<{
    entries: TimeOffEntry[];
    staff: StaffOption[];
    today: string;
    timezone: string;
    preselectedStaffId: number | null;
}>();

const REASONS = ['Holiday', 'Training', 'Sick day', 'Public holiday'];

const form = useForm({
    staff_id: props.staff.some((m) => m.id === props.preselectedStaffId)
        ? props.preselectedStaffId
        : null,
    all_day: true,
    starts_on: null as string | null,
    ends_on: null as string | null,
    starts_time: '09:00',
    ends_time: '17:00',
    reason: '',
});

const zone = props.timezone.replaceAll('_', ' ');

// The date fields and the calendar edit the same two dates; '' means not picked.
const startsOn = computed({
    get: () => form.starts_on ?? '',
    set: (value: string | number) => (form.starts_on = String(value) || null),
});
const endsOn = computed({
    get: () => form.ends_on ?? '',
    set: (value: string | number) => (form.ends_on = String(value) || null),
});

watch(
    () => form.starts_on,
    (value) => {
        if (value && (!form.ends_on || form.ends_on < value)) {
            form.ends_on = value;
        }
    },
);

const who = computed(
    () =>
        props.staff.find((m) => m.id === form.staff_id)?.name ?? 'The business',
);

function whenLabel(
    startsOn: string,
    endsOn: string,
    times: [string, string] | null,
): string {
    if (times) {
        const [start, end] = times.map((t) => clockLabel(toMinutes(t)));

        return startsOn === endsOn
            ? `${formatDate(startsOn)}, ${start} – ${end}`
            : `${formatDate(startsOn)} ${start} – ${formatDate(endsOn)} ${end}`;
    }

    const days = daysBetween(startsOn, endsOn) + 1;

    return startsOn === endsOn
        ? formatDate(startsOn)
        : `${formatDate(startsOn)} – ${formatDate(endsOn)} · ${days} days`;
}

/** The sentence under the form, so it's clear what's about to be blocked out. */
const summary = computed(() => {
    if (!form.starts_on || !form.ends_on) {
        return null;
    }

    const when = whenLabel(
        form.starts_on,
        form.ends_on,
        form.all_day ? null : [form.starts_time, form.ends_time],
    );
    const what = form.staff_id === null ? 'closed' : 'off';

    return `${who.value} will be ${what}: ${when}.`;
});

const marks = computed(() => {
    const byDate = new Map<string, CalendarMark[]>();

    for (const entry of props.entries) {
        const name = entry.staff_name ?? 'Closed';
        const label = entry.reason ? `${name}: ${entry.reason}` : name;

        for (const date of eachDate(entry.starts_on, entry.ends_on)) {
            byDate.set(date, [
                ...(byDate.get(date) ?? []),
                { tone: entry.staff_id === null ? 'closed' : 'staff', label },
            ]);
        }
    }

    return byDate;
});

function submit(): void {
    form.post(route('time-off.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('starts_on', 'ends_on', 'reason'),
    });
}

function remove(entry: TimeOffEntry): void {
    const name = entry.staff_name ?? 'the closure';

    if (window.confirm(`Remove this time off for ${name}?`)) {
        router.delete(route('time-off.destroy', entry.id), {
            preserveScroll: true,
        });
    }
}

const relative = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });

/** "tomorrow", "in 18 days" */
function startsIn(entry: TimeOffEntry): string {
    return relative.format(daysBetween(props.today, entry.starts_on), 'day');
}

function entryWhen(entry: TimeOffEntry): string {
    return whenLabel(
        entry.starts_on,
        entry.ends_on,
        entry.all_day ? null : [entry.starts_time!, entry.ends_time!],
    );
}
</script>

<template>
    <Head title="Time off" />

    <div class="mx-auto w-full max-w-6xl space-y-8 p-4 md:p-6">
        <Heading
            title="Time off"
            description="Holidays, training days and closures. No one can book a person while they're off, or anyone while the business is closed."
        />

        <div
            class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:grid-rows-[auto_1fr] lg:items-start"
        >
            <section class="min-w-0 rounded-xl border bg-card p-4 md:p-6">
                <RangeCalendar
                    v-model:from="form.starts_on"
                    v-model:to="form.ends_on"
                    :today="today"
                    :marks="marks"
                />
            </section>

            <form
                class="space-y-5 rounded-xl border bg-card p-4 md:p-6 lg:sticky lg:top-4 lg:col-start-2 lg:row-span-2 lg:row-start-1"
                @submit.prevent="submit"
            >
                <h2 class="font-semibold">Block out time</h2>

                <div class="grid gap-2">
                    <Label for="who">Who</Label>
                    <select
                        id="who"
                        v-model="form.staff_id"
                        class="h-9 w-full rounded-md border border-input bg-transparent px-2.5 text-sm shadow-xs"
                    >
                        <option :value="null">
                            Everyone (the business is closed)
                        </option>
                        <option
                            v-for="member in staff"
                            :key="member.id"
                            :value="member.id"
                        >
                            {{ member.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.staff_id" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="grid min-w-0 gap-2">
                        <Label for="starts-on">First day</Label>
                        <Input
                            id="starts-on"
                            v-model="startsOn"
                            type="date"
                            :min="today"
                            required
                        />
                    </div>
                    <div class="grid min-w-0 gap-2">
                        <Label for="ends-on">Last day</Label>
                        <Input
                            id="ends-on"
                            v-model="endsOn"
                            type="date"
                            :min="form.starts_on ?? today"
                            required
                        />
                    </div>
                </div>
                <InputError
                    :message="form.errors.starts_on || form.errors.ends_on"
                />

                <label class="flex items-center gap-3 text-sm">
                    <input
                        v-model="form.all_day"
                        type="checkbox"
                        class="size-4 accent-[var(--sun-deep)]"
                    />
                    The whole day
                </label>

                <div v-if="!form.all_day" class="grid grid-cols-2 gap-3">
                    <div class="grid min-w-0 gap-2">
                        <Label for="starts-time">From</Label>
                        <Input
                            id="starts-time"
                            v-model="form.starts_time"
                            type="time"
                            step="300"
                            required
                        />
                    </div>
                    <div class="grid min-w-0 gap-2">
                        <Label for="ends-time">Until</Label>
                        <Input
                            id="ends-time"
                            v-model="form.ends_time"
                            type="time"
                            step="300"
                            required
                        />
                    </div>
                    <InputError
                        class="col-span-2"
                        :message="
                            form.errors.starts_time || form.errors.ends_time
                        "
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="reason"
                        >Reason
                        <span class="font-normal text-muted-foreground"
                            >(optional, only you see it)</span
                        ></Label
                    >
                    <Input
                        id="reason"
                        v-model="form.reason"
                        maxlength="120"
                        placeholder="Holiday"
                    />
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="reason in REASONS"
                            :key="reason"
                            type="button"
                            class="rounded-full border px-2.5 py-0.5 text-xs transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            :class="
                                form.reason === reason &&
                                'border-sun-deep/50 bg-sun/10'
                            "
                            @click="form.reason = reason"
                        >
                            {{ reason }}
                        </button>
                    </div>
                    <InputError :message="form.errors.reason" />
                </div>

                <p
                    class="min-h-10 rounded-lg bg-muted px-3 py-2 text-sm"
                    :class="!summary && 'text-muted-foreground'"
                    aria-live="polite"
                    data-test="time-off-summary"
                >
                    {{ summary ?? 'Pick the days on the calendar.' }}
                </p>

                <p class="text-xs text-muted-foreground">
                    Dates and times are {{ zone }} time.
                </p>

                <Button
                    type="submit"
                    class="w-full"
                    :disabled="form.processing || !summary"
                    data-test="save-time-off"
                >
                    <Spinner v-if="form.processing" />
                    Block out this time
                </Button>
            </form>

            <section class="min-w-0 space-y-3 lg:col-start-1">
                <h2 class="font-semibold">Coming up</h2>

                <div
                    v-if="entries.length === 0"
                    class="flex items-center gap-3 rounded-xl border border-dashed px-4 py-6 text-sm text-muted-foreground"
                >
                    <CalendarOff class="size-5 shrink-0" />
                    Nothing booked off. Everyone's working their usual hours.
                </div>

                <ul v-else class="divide-y rounded-xl border bg-card">
                    <li
                        v-for="entry in entries"
                        :key="entry.id"
                        class="flex animate-in items-center gap-4 px-4 py-3 fade-in md:px-5"
                        data-test="time-off-entry"
                    >
                        <MemberAvatar
                            v-if="entry.staff_name"
                            :name="entry.staff_name"
                            :photo="entry.staff_photo_url"
                        />
                        <span
                            v-else
                            class="grid size-10 shrink-0 place-items-center rounded-full bg-dusk text-background dark:bg-muted dark:text-foreground"
                            aria-hidden="true"
                        >
                            <Store class="size-4" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p
                                class="flex flex-wrap items-center gap-2 font-medium"
                            >
                                {{ entry.staff_name ?? 'Business closed' }}
                                <Badge v-if="entry.started" variant="secondary"
                                    >Now</Badge
                                >
                            </p>
                            <p class="text-sm text-muted-foreground">
                                <template v-if="entry.reason"
                                    >{{ entry.reason }} ·
                                </template>
                                <span class="font-time">{{
                                    entryWhen(entry)
                                }}</span>
                            </p>
                        </div>
                        <span
                            v-if="!entry.started"
                            class="hidden text-xs whitespace-nowrap text-muted-foreground sm:block"
                            >{{ startsIn(entry) }}</span
                        >
                        <Button
                            variant="ghost"
                            size="icon"
                            :aria-label="`Remove time off: ${entry.staff_name ?? 'Business closed'}, ${entryWhen(entry)}`"
                            @click="remove(entry)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>
