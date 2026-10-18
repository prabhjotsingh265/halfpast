<script setup lang="ts">
import { CalendarClock, Mail, Phone, StickyNote } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { timeIn, zoneCity } from '@/lib/booking';
import { formatMoney } from '@/lib/money';
import type { CalendarBooking } from '@/types/calendar';

/**
 * Everything about one booking, from the calendar: when, who, what's been
 * paid and what's due on the day.
 */
const props = defineProps<{
    booking: CalendarBooking | null;
    timezone: string;
    staffName: string | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const date = computed(() =>
    props.booking
        ? new Intl.DateTimeFormat(undefined, {
              weekday: 'long',
              day: 'numeric',
              month: 'long',
              timeZone: props.timezone,
          }).format(new Date(props.booking.starts_at))
        : '',
);

const statusText: Record<CalendarBooking['status'], string> = {
    held: 'Held while the customer pays',
    confirmed: 'Confirmed',
    completed: 'Completed',
    no_show: 'No-show',
};

const gatewayText: Record<string, string> = {
    stripe: 'card',
    razorpay: 'Razorpay',
    test: 'test payment',
};
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent
            class="w-full overflow-y-auto sm:max-w-md"
            data-test="booking-sheet"
        >
            <template v-if="booking">
                <SheetHeader>
                    <SheetTitle class="text-xl">{{
                        booking.service_name
                    }}</SheetTitle>
                    <SheetDescription>
                        <template v-if="staffName"
                            >With {{ staffName }} ·
                        </template>
                        Reference {{ booking.reference }}
                    </SheetDescription>
                </SheetHeader>

                <div class="space-y-5 px-4 pb-6">
                    <div class="flex flex-wrap gap-2">
                        <Badge
                            :variant="
                                booking.status === 'no_show'
                                    ? 'destructive'
                                    : 'secondary'
                            "
                            >{{ statusText[booking.status] }}</Badge
                        >
                        <Badge variant="outline">{{
                            booking.source === 'manual'
                                ? 'Booked by the business'
                                : 'Booked online'
                        }}</Badge>
                    </div>

                    <div
                        class="flex gap-3 rounded-xl bg-muted px-3 py-2.5 text-sm"
                    >
                        <CalendarClock
                            class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                        />
                        <div>
                            <p class="font-medium">{{ date }}</p>
                            <p class="tabular-nums">
                                {{ timeIn(booking.starts_at, timezone) }} –
                                {{ timeIn(booking.ends_at, timezone) }}
                                <span class="text-muted-foreground"
                                    >· {{ zoneCity(timezone) }}</span
                                >
                            </p>
                        </div>
                    </div>

                    <section
                        v-if="booking.customer"
                        class="space-y-1.5 text-sm"
                    >
                        <h3 class="font-semibold">
                            {{ booking.customer.name }}
                        </h3>
                        <a
                            v-if="booking.customer.email"
                            :href="`mailto:${booking.customer.email}`"
                            class="flex items-center gap-2 text-muted-foreground hover:text-foreground"
                        >
                            <Mail class="size-4" />{{ booking.customer.email }}
                        </a>
                        <a
                            v-if="booking.customer.phone"
                            :href="`tel:${booking.customer.phone.replace(/\s/g, '')}`"
                            class="flex items-center gap-2 text-muted-foreground hover:text-foreground"
                        >
                            <Phone class="size-4" />{{ booking.customer.phone }}
                        </a>
                    </section>
                    <p v-else class="text-sm text-muted-foreground">
                        No customer details.
                    </p>

                    <dl class="space-y-1.5 border-t pt-4 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Price</dt>
                            <dd class="tabular-nums">
                                {{
                                    formatMoney(booking.price, booking.currency)
                                }}
                            </dd>
                        </div>
                        <div
                            v-if="booking.deposit"
                            class="flex justify-between"
                        >
                            <dt class="text-muted-foreground">Deposit</dt>
                            <dd class="tabular-nums">
                                <template v-if="booking.paid">
                                    {{
                                        formatMoney(
                                            booking.paid.amount,
                                            booking.currency,
                                        )
                                    }}
                                    paid by
                                    {{
                                        gatewayText[booking.paid.gateway] ??
                                        booking.paid.gateway
                                    }}
                                </template>
                                <template v-else>Not paid yet</template>
                            </dd>
                        </div>
                        <div class="flex justify-between font-medium">
                            <dt>To pay on the day</dt>
                            <dd class="tabular-nums">
                                {{
                                    formatMoney(
                                        booking.price -
                                            (booking.paid?.amount ?? 0),
                                        booking.currency,
                                    )
                                }}
                            </dd>
                        </div>
                    </dl>

                    <p
                        v-if="booking.notes"
                        class="flex gap-2 rounded-xl border px-3 py-2.5 text-sm"
                    >
                        <StickyNote
                            class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                        />
                        {{ booking.notes }}
                    </p>
                </div>
            </template>
        </SheetContent>
    </Sheet>
</template>
