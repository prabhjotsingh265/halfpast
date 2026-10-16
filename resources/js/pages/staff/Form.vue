<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import MemberAvatar from '@/components/staff/MemberAvatar.vue';
import WeeklyHoursEditor from '@/components/staff/WeeklyHoursEditor.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { duration } from '@/lib/time';
import type { Week } from '@/lib/week';
import { hoursFromWeek, PRESETS, weekFromHours } from '@/lib/week';
import type { ServiceOption, StaffMember } from '@/types/staff';

const props = defineProps<{
    member: StaffMember | null;
    services: ServiceOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Team', href: route('staff.index') }],
    },
});

const editing = props.member !== null;
const zone = (usePage().props.business?.timezone ?? '').replaceAll('_', ' ');

const form = useForm({
    name: props.member?.name ?? '',
    bio: props.member?.bio ?? '',
    is_active: props.member?.is_active ?? true,
    // Someone new usually does everything on the menu; untick what they don't.
    service_ids:
        props.member?.service_ids ??
        props.services.filter((s) => s.is_active).map((s) => s.id),
});

const week = ref<Week>(
    props.member ? weekFromHours(props.member.hours) : PRESETS[0].week(),
);

const errors = computed(() => form.errors as Record<string, string>);

const hoursError = computed(
    () =>
        Object.entries(errors.value).find(([key]) =>
            key.startsWith('hours'),
        )?.[1],
);

const allTicked = computed(
    () => form.service_ids.length === props.services.length,
);

function toggleService(id: number): void {
    form.service_ids = form.service_ids.includes(id)
        ? form.service_ids.filter((existing) => existing !== id)
        : [...form.service_ids, id];
}

function toggleAll(): void {
    form.service_ids = allTicked.value ? [] : props.services.map((s) => s.id);
}

function submit(): void {
    form.transform((data) => ({
        ...data,
        hours: hoursFromWeek(week.value),
    }));

    if (editing) {
        form.put(route('staff.update', props.member!.id));
    } else {
        form.post(route('staff.store'));
    }
}
</script>

<template>
    <Head :title="editing ? `Edit ${member?.name}` : 'Add someone'" />

    <form
        class="mx-auto w-full max-w-4xl space-y-10 p-4 md:p-6"
        @submit.prevent="submit"
    >
        <Heading
            :title="
                editing ? `Edit ${member?.name}` : 'Add someone to the team'
            "
            description="Customers pick from the people who do the service they want, at times those people work."
        />

        <section class="grid gap-6 md:grid-cols-[14rem_minmax(0,1fr)]">
            <div>
                <h2 class="font-semibold">About them</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Shown on the booking page.
                </p>
            </div>
            <div class="space-y-5">
                <div class="flex items-center gap-4">
                    <MemberAvatar :name="form.name" size="lg" />
                    <div class="grid flex-1 gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            required
                            maxlength="80"
                            placeholder="Priya"
                            v-focus
                        />
                        <InputError :message="form.errors.name" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="bio"
                        >A line about them
                        <span class="font-normal text-muted-foreground"
                            >(optional)</span
                        ></Label
                    >
                    <textarea
                        id="bio"
                        v-model="form.bio"
                        rows="2"
                        maxlength="300"
                        placeholder="Colour specialist, ten years in the chair."
                        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError :message="form.errors.bio" />
                </div>

                <label class="flex items-center gap-3 text-sm">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="size-4 accent-[var(--sun-deep)]"
                    />
                    Taking bookings
                </label>
            </div>
        </section>

        <section
            class="grid gap-6 border-t pt-10 md:grid-cols-[14rem_minmax(0,1fr)]"
        >
            <div>
                <h2 class="font-semibold">What they do</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Customers only see them for these services.
                </p>
            </div>
            <div class="space-y-3">
                <div
                    v-if="services.length === 0"
                    class="rounded-xl border border-dashed p-5 text-sm text-muted-foreground"
                >
                    There aren't any services yet.
                    <Link
                        :href="route('services.create')"
                        class="font-medium text-foreground underline underline-offset-4"
                        >Add one</Link
                    >
                    and come back to choose who does it.
                </div>
                <template v-else>
                    <button
                        type="button"
                        class="text-sm font-medium text-sun-deep underline-offset-4 hover:underline"
                        @click="toggleAll"
                    >
                        {{ allTicked ? 'Untick all' : 'Tick all' }}
                    </button>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <button
                            v-for="service in services"
                            :key="service.id"
                            type="button"
                            role="checkbox"
                            :aria-checked="
                                form.service_ids.includes(service.id)
                            "
                            class="flex items-center gap-3 rounded-lg border px-3 py-2.5 text-left transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            :class="
                                form.service_ids.includes(service.id)
                                    ? 'border-sun-deep/50 bg-sun/10'
                                    : 'hover:bg-accent'
                            "
                            @click="toggleService(service.id)"
                        >
                            <span
                                class="grid size-5 shrink-0 place-items-center rounded-md border transition-colors"
                                :class="
                                    form.service_ids.includes(service.id)
                                        ? 'border-sun-deep bg-sun-deep text-background'
                                        : 'bg-background'
                                "
                            >
                                <Check
                                    v-if="form.service_ids.includes(service.id)"
                                    class="size-3.5"
                                />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span
                                    class="block truncate text-sm font-medium"
                                >
                                    {{ service.name }}
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    {{ duration(service.duration_minutes) }}
                                    <template v-if="!service.is_active">
                                        · hidden</template
                                    >
                                </span>
                            </span>
                        </button>
                    </div>
                    <InputError :message="form.errors.service_ids" />
                </template>
            </div>
        </section>

        <section class="space-y-4 border-t pt-10">
            <div>
                <h2 class="font-semibold">When they work</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Drag across a day to add hours, drag the ends to change
                    them. Leave gaps for breaks. Times are {{ zone }} time.
                </p>
            </div>
            <WeeklyHoursEditor v-model="week" />
            <InputError :message="hoursError" />
        </section>

        <div
            class="sticky bottom-0 -mx-4 flex items-center gap-3 border-t bg-background/90 px-4 py-4 backdrop-blur md:-mx-6 md:px-6"
        >
            <Button
                type="submit"
                :disabled="form.processing"
                data-test="save-member"
            >
                <Spinner v-if="form.processing" />
                {{ editing ? 'Save changes' : 'Add to the team' }}
            </Button>
            <Button as-child variant="ghost">
                <Link :href="route('staff.index')">Cancel</Link>
            </Button>
        </div>
    </form>
</template>
