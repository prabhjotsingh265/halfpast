<script setup lang="ts">
import { Form, Head, useHttp } from '@inertiajs/vue3';
import { Check, X } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { guessCurrency } from '@/lib/marketing';
import { allZones, localZone } from '@/lib/zones';

defineOptions({
    layout: {
        title: 'Set up your business',
        description: 'It takes a minute, and you can change all of it later.',
    },
});

const props = defineProps<{
    suggestedName: string;
    currencies: string[];
    bookingBase: string;
}>();

/** "Glow Studio & Spa" → "glow-studio-and-spa" */
function slugify(text: string): string {
    return text
        .toLowerCase()
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/&/g, ' and ')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 40)
        .replace(/-+$/, '');
}

const name = ref(props.suggestedName);
const slug = ref(slugify(props.suggestedName));
const slugTouched = ref(false);
const timezone = ref('UTC');
const currency = ref(props.currencies[0] ?? 'GBP');

const zones = allZones();

onMounted(() => {
    timezone.value = localZone();
    const guess = guessCurrency();

    if (props.currencies.includes(guess)) {
        currency.value = guess;
    }
});

// The address follows the name until the owner edits it themselves.
watch(name, (value) => {
    if (!slugTouched.value) {
        slug.value = slugify(value);
    }
});

type Check = {
    state: 'idle' | 'checking' | 'available' | 'taken';
    message: string;
};

const http = useHttp();
const address = ref<Check>({ state: 'idle', message: '' });
let timer = 0;

watch(
    slug,
    (value) => {
        window.clearTimeout(timer);

        if (value === '') {
            address.value = { state: 'idle', message: '' };

            return;
        }

        address.value = { state: 'checking', message: 'Checking…' };
        timer = window.setTimeout(async () => {
            try {
                const result = (await http.get(
                    route('onboarding.address', { slug: value }),
                )) as { available: boolean; message: string };

                if (slug.value === value) {
                    address.value = {
                        state: result.available ? 'available' : 'taken',
                        message: result.message,
                    };
                }
            } catch {
                address.value = { state: 'idle', message: '' };
            }
        }, 300);
    },
    { immediate: true },
);

onBeforeUnmount(() => window.clearTimeout(timer));
</script>

<template>
    <Head title="Set up your business" />

    <Form
        :action="route('onboarding.store')"
        method="post"
        class="flex flex-col gap-6"
        v-slot="{ errors, processing }"
    >
        <div class="grid gap-2">
            <Label for="name">Business name</Label>
            <Input
                id="name"
                v-model="name"
                name="name"
                required
                maxlength="80"
                autocomplete="organization"
                placeholder="Glow Studio"
                v-focus
            />
            <InputError :message="errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="slug">Booking address</Label>
            <div
                class="flex h-9 items-center overflow-hidden rounded-md border border-input bg-transparent shadow-xs focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50"
            >
                <span
                    class="flex h-full items-center border-r bg-muted px-2.5 text-sm text-muted-foreground"
                >
                    {{ bookingBase }}
                </span>
                <input
                    id="slug"
                    v-model="slug"
                    name="slug"
                    required
                    maxlength="40"
                    autocapitalize="none"
                    spellcheck="false"
                    class="h-full min-w-0 flex-1 bg-transparent px-2.5 text-sm outline-none"
                    aria-describedby="slug-status"
                    @input="slugTouched = true"
                />
            </div>
            <p
                id="slug-status"
                class="flex min-h-5 items-center gap-1.5 text-sm"
                :class="{
                    'text-success': address.state === 'available',
                    'text-destructive': address.state === 'taken',
                    'text-muted-foreground': address.state === 'checking',
                }"
                aria-live="polite"
            >
                <Check v-if="address.state === 'available'" class="size-4" />
                <X v-else-if="address.state === 'taken'" class="size-4" />
                {{ address.message }}
            </p>
            <InputError :message="errors.slug" />
        </div>

        <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_7rem]">
            <div class="grid gap-2">
                <Label for="timezone">Time zone</Label>
                <select
                    id="timezone"
                    v-model="timezone"
                    name="timezone"
                    class="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-2.5 text-sm shadow-xs"
                >
                    <option v-for="zone in zones" :key="zone" :value="zone">
                        {{ zone.replace(/_/g, ' ') }}
                    </option>
                </select>
                <InputError :message="errors.timezone" />
            </div>

            <div class="grid gap-2">
                <Label for="currency">Currency</Label>
                <select
                    id="currency"
                    v-model="currency"
                    name="currency"
                    class="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-2.5 text-sm shadow-xs"
                >
                    <option
                        v-for="code in currencies"
                        :key="code"
                        :value="code"
                    >
                        {{ code }}
                    </option>
                </select>
                <InputError :message="errors.currency" />
            </div>
        </div>

        <Button
            type="submit"
            class="w-full"
            :disabled="processing"
            data-test="create-business-button"
        >
            <Spinner v-if="processing" />
            Create my booking page
        </Button>
    </Form>
</template>
