<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    CalendarClock,
    Check,
    Mail,
    Phone,
    StickyNote,
    UserX,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
 * paid and what's due on the day. Once it has started, it can be marked
 * completed or a no-show; before then, an owner can cancel it.
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

const busy = ref(false);
const confirming = ref(false);
const refund = ref(true);
const tellCustomer = ref(true);

// A different booking starts with the cancel form closed and the defaults back.
watch(
    () => props.booking?.id,
    () => {
        confirming.value = false;
        refund.value = true;
        tellCustomer.value = true;
    },
);

const firstName = computed(
    () => props.booking?.customer?.name.split(' ')[0] ?? 'the customer',
);

function act(
    name: 'bookings.complete' | 'bookings.no-show' | 'bookings.cancel',
    data: Record<string, boolean> = {},
): void {
    if (!props.booking) {
        return;
    }

    router.post(route(name, props.booking.code), data, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (busy.value = true),
        onFinish: () => (busy.value = false),
    });
}

function cancel(): void {
    act('bookings.cancel', {
        refund: refund.value && props.booking?.paid !== null,
        tell_customer:
            tellCustomer.value && Boolean(props.booking?.customer?.email),
    });
}
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

                    <!-- How it went, once it has started. -->
                    <section
                        v-if="booking.can.mark"
                        class="space-y-2 border-t pt-4"
                        data-test="visit-actions"
                    >
                        <h3 class="text-sm font-semibold">How did it go?</h3>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                :variant="
                                    booking.status === 'completed'
                                        ? 'default'
                                        : 'outline'
                                "
                                :aria-pressed="booking.status === 'completed'"
                                :disabled="busy"
                                data-test="mark-completed"
                                @click="
                                    booking.status !== 'completed' &&
                                    act('bookings.complete')
                                "
                            >
                                <Check class="size-4" />Completed
                            </Button>
                            <Button
                                :variant="
                                    booking.status === 'no_show'
                                        ? 'destructive'
                                        : 'outline'
                                "
                                :aria-pressed="booking.status === 'no_show'"
                                :disabled="busy"
                                data-test="mark-no-show"
                                @click="
                                    booking.status !== 'no_show' &&
                                    act('bookings.no-show')
                                "
                            >
                                <UserX class="size-4" />No-show
                            </Button>
                        </div>
                        <p
                            v-if="booking.status === 'no_show' && booking.paid"
                            class="text-xs text-muted-foreground"
                        >
                            The deposit is kept.
                        </p>
                    </section>

                    <section v-if="booking.can.cancel" class="border-t pt-4">
                        <Button
                            v-if="!confirming"
                            variant="outline"
                            class="text-destructive hover:text-destructive"
                            data-test="cancel-booking"
                            @click="confirming = true"
                        >
                            Cancel booking
                        </Button>
                        <form
                            v-else
                            class="space-y-3 rounded-xl border border-destructive/40 p-3"
                            data-test="cancel-form"
                            @submit.prevent="cancel"
                        >
                            <div>
                                <p class="text-sm font-semibold">
                                    Cancel this booking?
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    The time becomes free for someone else.
                                </p>
                            </div>
                            <fieldset
                                v-if="booking.paid"
                                class="space-y-1.5 text-sm"
                            >
                                <legend class="sr-only">The deposit</legend>
                                <label class="flex items-center gap-2">
                                    <input
                                        v-model="refund"
                                        type="radio"
                                        name="refund"
                                        :value="true"
                                        class="size-4 accent-sun-deep"
                                        data-test="refund-yes"
                                    />
                                    Refund the
                                    {{
                                        formatMoney(
                                            booking.paid.amount,
                                            booking.currency,
                                        )
                                    }}
                                    deposit
                                </label>
                                <label class="flex items-center gap-2">
                                    <input
                                        v-model="refund"
                                        type="radio"
                                        name="refund"
                                        :value="false"
                                        class="size-4 accent-sun-deep"
                                        data-test="refund-no"
                                    />
                                    Keep the deposit
                                </label>
                            </fieldset>
                            <label
                                v-if="booking.customer?.email"
                                class="flex items-center gap-2 text-sm"
                            >
                                <Checkbox
                                    v-model="tellCustomer"
                                    data-test="tell-customer"
                                />
                                Email {{ firstName }} to say it's cancelled
                            </label>
                            <div class="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    @click="confirming = false"
                                    >Keep it</Button
                                >
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    :disabled="busy"
                                    data-test="confirm-cancel"
                                    >Cancel booking</Button
                                >
                            </div>
                        </form>
                    </section>
                </div>
            </template>
        </SheetContent>
    </Sheet>
</template>
