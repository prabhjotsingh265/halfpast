<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { dateIn, localZone, timeIn, zonedTime } from '@/lib/zones';

const BUSINESS_ZONE = 'Europe/London';

const places = [
    { zone: 'Europe/London', city: 'London' },
    { zone: 'America/New_York', city: 'New York' },
    { zone: 'Asia/Kolkata', city: 'Mumbai' },
    { zone: 'Asia/Dubai', city: 'Dubai' },
    { zone: 'Australia/Sydney', city: 'Sydney' },
];

const customerZone = ref('Asia/Kolkata');

onMounted(() => {
    const mine = localZone();

    if (places.some((p) => p.zone === mine)) {
        customerZone.value = mine;
    }
});

// Next Tuesday, as the business's calendar reads it.
const tuesday = (() => {
    const date = new Date();
    date.setDate(date.getDate() + ((2 - date.getDay() + 7) % 7 || 7));

    return { y: date.getFullYear(), m: date.getMonth() + 1, d: date.getDate() };
})();

const times = [
    [9, 0],
    [11, 30],
    [15, 30],
    [18, 0],
];

const rows = computed(() =>
    times.map(([h, min]) => {
        const instant = zonedTime(
            tuesday.y,
            tuesday.m,
            tuesday.d,
            h,
            min,
            BUSINESS_ZONE,
        );
        const businessDate = dateIn(instant, BUSINESS_ZONE);
        const customerDate = dateIn(instant, customerZone.value);

        return {
            key: `${h}:${min}`,
            business: timeIn(instant, BUSINESS_ZONE),
            customer: timeIn(instant, customerZone.value),
            dayShift:
                customerDate === businessDate
                    ? ''
                    : customerDate > businessDate
                      ? 'next day'
                      : 'day before',
        };
    }),
);

const city = computed(
    () => places.find((p) => p.zone === customerZone.value)?.city ?? '',
);
</script>

<template>
    <div>
        <label for="customer-zone" class="text-sm font-semibold"
            >Where's the customer?</label
        >
        <select
            id="customer-zone"
            v-model="customerZone"
            class="mt-2 h-10 w-full rounded-lg border bg-background px-3 text-sm sm:w-56"
        >
            <option
                v-for="place in places"
                :key="place.zone"
                :value="place.zone"
            >
                {{ place.city }}
            </option>
        </select>

        <table class="mt-5 w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted-foreground">
                    <th class="pb-2 font-semibold">At the salon in London</th>
                    <th class="pb-2 font-semibold">
                        For the customer in {{ city }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in rows" :key="row.key" class="border-t">
                    <td class="py-2.5 font-time">{{ row.business }}</td>
                    <td class="py-2.5">
                        <span class="font-time">{{ row.customer }}</span>
                        <span
                            v-if="row.dayShift"
                            class="ml-2 rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                        >
                            {{ row.dayShift }}
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
        <p class="mt-4 text-xs text-muted-foreground">
            Every booking is stored in UTC and shown in each person's own time,
            daylight saving included.
        </p>
    </div>
</template>
