<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { MapPin, Phone } from '@lucide/vue';
import { computed } from 'vue';
import MemberAvatar from '@/components/staff/MemberAvatar.vue';
import type { WeekHours } from '@/lib/openingHours';
import { clockLabel, weekdayNames } from '@/lib/openingHours';
import type { BookableStaff } from '@/types/booking';

/**
 * The foot of the booking page: who works there, and where to find them,
 * with the week's hours written out.
 */
const props = defineProps<{
    staff: BookableStaff[];
    hours: WeekHours;
}>();

const business = computed(() => usePage().props.business!);

const week = computed(() =>
    weekdayNames().map((name, index) => {
        const stretches = props.hours[index + 1] ?? [];

        return {
            name,
            text: stretches.length
                ? stretches
                      .map(
                          ([from, to]) =>
                              `${clockLabel(from)}–${clockLabel(to)}`,
                      )
                      .join(', ')
                : 'Closed',
        };
    }),
);

const directions = computed(() =>
    business.value.address
        ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(business.value.address)}`
        : null,
);
</script>

<template>
    <div
        class="mx-auto grid max-w-5xl gap-10 border-t px-4 py-12 md:grid-cols-[minmax(0,1fr)_18rem]"
    >
        <section v-if="staff.length" aria-labelledby="team">
            <h2 id="team" class="text-lg font-semibold">
                The team at {{ business.name }}
            </h2>
            <ul class="mt-5 grid gap-4 sm:grid-cols-2">
                <li
                    v-for="member in staff"
                    :key="member.id"
                    class="flex items-start gap-3"
                >
                    <MemberAvatar
                        :name="member.name"
                        :photo="member.photo_url"
                        size="lg"
                    />
                    <div class="min-w-0 pt-1">
                        <p class="font-medium">{{ member.name }}</p>
                        <p
                            v-if="member.bio"
                            class="mt-0.5 text-sm text-muted-foreground"
                        >
                            {{ member.bio }}
                        </p>
                    </div>
                </li>
            </ul>
        </section>

        <section aria-labelledby="find-us" class="text-sm">
            <h2 id="find-us" class="text-lg font-semibold">Find us</h2>
            <div class="mt-5 space-y-3">
                <a
                    v-if="business.address"
                    :href="directions!"
                    target="_blank"
                    rel="noopener"
                    class="flex gap-2 underline-offset-4 hover:underline"
                >
                    <MapPin class="mt-0.5 size-4 shrink-0" />
                    <span
                        >{{ business.address }}
                        <span class="block text-muted-foreground"
                            >Get directions</span
                        ></span
                    >
                </a>
                <a
                    v-if="business.phone"
                    :href="`tel:${business.phone.replace(/[^0-9+]/g, '')}`"
                    class="flex gap-2 underline-offset-4 hover:underline"
                >
                    <Phone class="mt-0.5 size-4 shrink-0" />
                    {{ business.phone }}
                </a>
            </div>
            <dl class="mt-5 space-y-1">
                <div
                    v-for="day in week"
                    :key="day.name"
                    class="flex justify-between gap-4"
                >
                    <dt class="text-muted-foreground">{{ day.name }}</dt>
                    <dd
                        class="text-right font-time"
                        :class="
                            day.text === 'Closed' && 'text-muted-foreground'
                        "
                    >
                        {{ day.text }}
                    </dd>
                </div>
            </dl>
        </section>
    </div>
</template>
