<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import MemberAvatar from '@/components/staff/MemberAvatar.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { currencySymbol, formatMoney, toMajorInput } from '@/lib/money';
import { duration as durationLabel } from '@/lib/time';
import type { DepositType, Service } from '@/types/service';
import type { StaffOption } from '@/types/staff';

const props = defineProps<{
    service: Service | null;
    currency: string;
    staff: StaffOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Services', href: route('services.index') }],
    },
});

const editing = props.service !== null;
const symbol = currencySymbol(props.currency);

const description = ref(props.service?.description ?? '');
const price = ref(props.service ? toMajorInput(props.service.price) : '');
const durationMinutes = ref(props.service?.duration_minutes ?? 30);
const bufferMinutes = ref(props.service?.buffer_after_minutes ?? 0);
const depositType = ref<DepositType>(props.service?.deposit_type ?? 'percent');
const depositValue = ref(
    props.service === null
        ? '20'
        : props.service.deposit_type === 'fixed'
          ? toMajorInput(props.service.deposit_value)
          : props.service.deposit_type === 'percent'
            ? String(props.service.deposit_value)
            : '',
);
const isActive = ref(props.service?.is_active ?? true);
// A new service starts with everyone taking bookings ticked.
const staffIds = ref<number[]>(
    props.service?.staff_ids ??
        props.staff.filter((m) => m.is_active).map((m) => m.id),
);

const durations = [15, 20, 30, 45, 60, 75, 90, 120, 150, 180, 240];
const buffers = [0, 5, 10, 15, 20, 30, 45, 60];

const depositChoices: { value: DepositType; label: string }[] = [
    { value: 'percent', label: 'Percentage' },
    { value: 'fixed', label: 'Fixed amount' },
    { value: 'none', label: 'No deposit' },
];

// What the customer will see, worked out the same way the server does.
const depositPreview = computed(() => {
    const priceMinor = Math.round(Number(price.value || 0) * 100);
    const value = Number(depositValue.value || 0);

    if (depositType.value === 'none' || priceMinor === 0) {
        return null;
    }

    const amount =
        depositType.value === 'percent'
            ? Math.round((priceMinor * Math.min(value, 100)) / 100)
            : Math.min(Math.round(value * 100), priceMinor);

    return amount > 0 ? formatMoney(amount, props.currency) : null;
});
</script>

<template>
    <Head :title="editing ? `Edit ${service?.name}` : 'Add a service'" />

    <div class="mx-auto w-full max-w-2xl space-y-8 p-4 md:p-6">
        <Heading
            :title="editing ? `Edit ${service?.name}` : 'Add a service'"
            description="Customers see the name, length and price when they book."
        />

        <Form
            :action="
                editing
                    ? route('services.update', service!.id)
                    : route('services.store')
            "
            :method="editing ? 'put' : 'post'"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    name="name"
                    :default-value="service?.name"
                    required
                    maxlength="80"
                    placeholder="Haircut"
                    v-focus
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="description"
                    >Description
                    <span class="font-normal text-muted-foreground"
                        >(optional)</span
                    ></Label
                >
                <textarea
                    id="description"
                    name="description"
                    rows="3"
                    maxlength="500"
                    v-model="description"
                    placeholder="Wash, cut and finish."
                    class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <InputError :message="errors.description" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="duration">How long it takes</Label>
                    <select
                        id="duration"
                        v-model.number="durationMinutes"
                        name="duration_minutes"
                        class="h-9 w-full rounded-md border border-input bg-transparent px-2.5 text-sm shadow-xs"
                    >
                        <option
                            v-for="minutes in durations"
                            :key="minutes"
                            :value="minutes"
                        >
                            {{ durationLabel(minutes) }}
                        </option>
                    </select>
                    <InputError :message="errors.duration_minutes" />
                </div>
                <div class="grid gap-2">
                    <Label for="buffer">Free time after</Label>
                    <select
                        id="buffer"
                        v-model.number="bufferMinutes"
                        name="buffer_after_minutes"
                        class="h-9 w-full rounded-md border border-input bg-transparent px-2.5 text-sm shadow-xs"
                    >
                        <option
                            v-for="minutes in buffers"
                            :key="minutes"
                            :value="minutes"
                        >
                            {{ minutes === 0 ? 'None' : `${minutes} min` }}
                        </option>
                    </select>
                    <InputError :message="errors.buffer_after_minutes" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="price">Price</Label>
                <div
                    class="flex h-9 items-center overflow-hidden rounded-md border border-input shadow-xs focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50 sm:w-56"
                >
                    <span
                        class="flex h-full items-center border-r bg-muted px-3 text-sm text-muted-foreground"
                        >{{ symbol }}</span
                    >
                    <input
                        id="price"
                        v-model="price"
                        name="price"
                        inputmode="decimal"
                        required
                        placeholder="30"
                        class="h-full min-w-0 flex-1 bg-transparent px-3 text-sm font-time outline-none"
                    />
                </div>
                <InputError :message="errors.price" />
            </div>

            <fieldset class="grid gap-3">
                <legend class="text-sm font-medium">Deposit to book</legend>
                <div
                    class="inline-flex w-fit rounded-lg border p-1"
                    role="radiogroup"
                    aria-label="Deposit type"
                >
                    <label
                        v-for="choice in depositChoices"
                        :key="choice.value"
                        class="cursor-pointer rounded-md px-3 py-1.5 text-sm transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring"
                        :class="
                            depositType === choice.value
                                ? 'bg-foreground text-background'
                                : 'hover:bg-accent'
                        "
                    >
                        <input
                            v-model="depositType"
                            type="radio"
                            name="deposit_type"
                            :value="choice.value"
                            class="sr-only"
                        />
                        {{ choice.label }}
                    </label>
                </div>

                <div
                    v-if="depositType !== 'none'"
                    class="flex flex-wrap items-center gap-3"
                >
                    <div
                        class="flex h-9 w-40 items-center overflow-hidden rounded-md border border-input shadow-xs focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50"
                    >
                        <span
                            v-if="depositType === 'fixed'"
                            class="flex h-full items-center border-r bg-muted px-3 text-sm text-muted-foreground"
                            >{{ symbol }}</span
                        >
                        <input
                            id="deposit-value"
                            v-model="depositValue"
                            name="deposit_value"
                            inputmode="decimal"
                            aria-label="Deposit amount"
                            class="h-full min-w-0 flex-1 bg-transparent px-3 text-sm font-time outline-none"
                        />
                        <span
                            v-if="depositType === 'percent'"
                            class="flex h-full items-center border-l bg-muted px-3 text-sm text-muted-foreground"
                            >%</span
                        >
                    </div>
                    <p
                        v-if="depositPreview"
                        class="text-sm text-muted-foreground"
                        aria-live="polite"
                    >
                        Customers pay
                        <strong class="text-foreground">{{
                            depositPreview
                        }}</strong>
                        to book.
                    </p>
                </div>
                <input v-else type="hidden" name="deposit_value" value="" />
                <InputError
                    :message="errors.deposit_value || errors.deposit_type"
                />
            </fieldset>

            <fieldset class="grid gap-3">
                <legend class="text-sm font-medium">Who does this</legend>
                <p
                    v-if="staff.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No one is on the team yet.
                    <Link
                        :href="route('staff.create')"
                        class="font-medium text-foreground underline underline-offset-4"
                        >Add someone</Link
                    >
                    so customers have a person to book.
                </p>
                <div v-else class="flex flex-wrap gap-2">
                    <label
                        v-for="member in staff"
                        :key="member.id"
                        class="flex cursor-pointer items-center gap-2 rounded-full border py-1 pr-3 pl-1 text-sm transition-colors select-none hover:bg-accent has-[:checked]:border-sun-deep/50 has-[:checked]:bg-sun/10 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring"
                    >
                        <input
                            v-model="staffIds"
                            type="checkbox"
                            name="staff_ids[]"
                            :value="member.id"
                            class="sr-only"
                        />
                        <MemberAvatar :name="member.name" size="xs" />
                        {{ member.name }}
                    </label>
                </div>
                <InputError :message="errors.staff_ids" />
            </fieldset>

            <label class="flex items-center gap-3 text-sm">
                <input
                    type="hidden"
                    name="is_active"
                    :value="isActive ? 1 : 0"
                />
                <input
                    v-model="isActive"
                    type="checkbox"
                    class="size-4 accent-[var(--sun-deep)]"
                />
                Customers can book this
            </label>

            <div class="flex items-center gap-3 border-t pt-6">
                <Button
                    type="submit"
                    :disabled="processing"
                    data-test="save-service"
                >
                    <Spinner v-if="processing" />
                    {{ editing ? 'Save changes' : 'Add service' }}
                </Button>
                <Button as-child variant="ghost">
                    <Link :href="route('services.index')">Cancel</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
