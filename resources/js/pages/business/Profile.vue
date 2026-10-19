<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ExternalLink, ImageUp, MapPin, Phone } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import BusinessLogo from '@/components/business/BusinessLogo.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { daylightRange } from '@/lib/daylight';
import { hourNow } from '@/lib/time';

type Profile = {
    name: string;
    description: string | null;
    address: string | null;
    phone: string | null;
    website: string | null;
};

const props = defineProps<{
    profile: Profile;
    logoUrl: string | null;
    bookingUrl: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Business settings',
                href: route('business.profile.edit'),
            },
        ],
    },
});

// A demo's booking page is public, so its profile stays as it is.
const isDemo = computed(() => usePage().props.business?.is_demo ?? false);

const form = useForm({
    name: props.profile.name,
    description: props.profile.description ?? '',
    address: props.profile.address ?? '',
    phone: props.profile.phone ?? '',
    website: props.profile.website ?? '',
});

function save(): void {
    form.put(route('business.profile.update'), { preserveScroll: true });
}

// The logo goes up as soon as it's picked, like a team member's photo.
const logoInput = ref<HTMLInputElement | null>(null);
const logoError = ref<string | null>(null);
const uploading = ref(false);

function chooseLogo(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    logoError.value = null;
    router.post(
        route('business.logo.store'),
        { logo: file },
        {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => (uploading.value = true),
            onFinish: () => {
                uploading.value = false;

                if (logoInput.value) {
                    logoInput.value.value = '';
                }
            },
            onError: (errors) => (logoError.value = errors.logo ?? null),
        },
    );
}

function removeLogo(): void {
    router.delete(route('business.logo.destroy'), { preserveScroll: true });
}

// The preview wears the colour of the hour, like the real booking page.
const hour = ref(12);
let timer = 0;

onMounted(() => {
    hour.value = hourNow();
    timer = window.setInterval(() => (hour.value = hourNow()), 60_000);
});

onBeforeUnmount(() => window.clearInterval(timer));

const tint = computed(() => daylightRange(hour.value, hour.value + 4));
const shownName = computed(() => form.name.trim() || 'Your business');
const website = computed(() =>
    form.website
        .trim()
        .replace(/^https?:\/\//i, '')
        .replace(/\/$/, ''),
);
</script>

<template>
    <Head title="Booking page" />

    <div class="space-y-10">
        <p
            v-if="isDemo"
            class="rounded-xl border border-sun/40 bg-sun/10 p-4 text-sm"
            data-test="demo-locked"
        >
            This is a demo business, and its booking page is public, so its logo
            and details can't be changed here. Sign up to set up your own.
        </p>

        <section class="space-y-5">
            <Heading
                variant="small"
                title="Your logo"
                description="Shown on your booking page, in customers' emails and when your link is shared."
            />

            <div class="flex flex-wrap items-center gap-4" data-test="logo">
                <BusinessLogo :name="shownName" :logo-url="logoUrl" size="xl" />
                <div class="grid gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <label
                            class="inline-flex h-9 cursor-pointer items-center gap-2 rounded-md border bg-background px-3 text-sm font-medium shadow-xs transition-colors focus-within:ring-[3px] focus-within:ring-ring/50 hover:bg-accent"
                        >
                            <Spinner v-if="uploading" />
                            <ImageUp v-else class="size-4" />
                            {{ logoUrl ? 'Change logo' : 'Upload a logo' }}
                            <input
                                ref="logoInput"
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                class="sr-only"
                                :disabled="isDemo"
                                data-test="logo-input"
                                @change="chooseLogo"
                            />
                        </label>
                        <Button
                            v-if="logoUrl && !isDemo"
                            type="button"
                            variant="ghost"
                            size="sm"
                            data-test="logo-remove"
                            @click="removeLogo"
                            >Remove</Button
                        >
                    </div>
                    <p class="text-xs text-muted-foreground">
                        A PNG, JPG or WebP up to 5 MB. A PNG with a see-through
                        background looks best. Without one, your initial is
                        shown.
                    </p>
                    <InputError :message="logoError ?? undefined" />
                </div>
            </div>
        </section>

        <div class="grid gap-10 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <form class="space-y-6" @submit.prevent="save">
                <Heading
                    variant="small"
                    title="About your business"
                    description="What customers read at the top of your booking page."
                />

                <div class="grid gap-2">
                    <Label for="name">Business name</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        required
                        maxlength="80"
                        autocomplete="organization"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="description"
                        >A line or two about it
                        <span class="font-normal text-muted-foreground"
                            >(optional)</span
                        ></Label
                    >
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="3"
                        maxlength="300"
                        placeholder="A neighbourhood salon for cuts, colour and calm. Free tea while you wait."
                        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <p
                        class="text-right text-xs text-muted-foreground tabular-nums"
                    >
                        {{ form.description.length }}/300
                    </p>
                    <InputError :message="form.errors.description" />
                </div>

                <div class="grid gap-2">
                    <Label for="address"
                        >Address
                        <span class="font-normal text-muted-foreground"
                            >(optional)</span
                        ></Label
                    >
                    <Input
                        id="address"
                        v-model="form.address"
                        maxlength="200"
                        autocomplete="street-address"
                        placeholder="14 Park Street, Kolkata 700016"
                    />
                    <p class="text-xs text-muted-foreground">
                        Customers get a link to it on a map.
                    </p>
                    <InputError :message="form.errors.address" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="phone"
                            >Phone
                            <span class="font-normal text-muted-foreground"
                                >(optional)</span
                            ></Label
                        >
                        <Input
                            id="phone"
                            v-model="form.phone"
                            type="tel"
                            maxlength="40"
                            autocomplete="tel"
                        />
                        <InputError :message="form.errors.phone" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="website"
                            >Website
                            <span class="font-normal text-muted-foreground"
                                >(optional)</span
                            ></Label
                        >
                        <Input
                            id="website"
                            v-model="form.website"
                            maxlength="200"
                            autocomplete="url"
                            placeholder="glowstudio.com"
                        />
                        <InputError :message="form.errors.website" />
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <Button
                        type="submit"
                        :disabled="form.processing || isDemo"
                        data-test="save-profile"
                    >
                        <Spinner v-if="form.processing" />
                        Save
                    </Button>
                    <a
                        :href="bookingUrl"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-1.5 text-sm font-medium underline-offset-4 hover:underline"
                    >
                        Open your booking page
                        <ExternalLink class="size-3.5" />
                    </a>
                </div>
            </form>

            <aside aria-label="Preview" class="xl:pt-14">
                <p
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    What customers see
                </p>
                <div
                    class="mt-3 overflow-hidden rounded-2xl border shadow-sm"
                    data-test="profile-preview"
                >
                    <div
                        class="bg-[linear-gradient(120deg,var(--from),var(--to))] p-5 transition-[background] duration-700 dark:bg-[linear-gradient(120deg,var(--from-dark),var(--to-dark))]"
                        :style="tint"
                    >
                        <BusinessLogo
                            :name="shownName"
                            :logo-url="logoUrl"
                            size="lg"
                        />
                        <p class="mt-3 text-xl leading-tight font-[780]">
                            {{ shownName }}
                        </p>
                        <p
                            v-if="form.description.trim()"
                            class="mt-1.5 text-sm text-foreground/75"
                        >
                            {{ form.description }}
                        </p>
                    </div>
                    <ul
                        v-if="form.address || form.phone || website"
                        class="space-y-2 bg-background p-5 text-sm text-muted-foreground"
                    >
                        <li v-if="form.address" class="flex gap-2">
                            <MapPin class="mt-0.5 size-4 shrink-0" />
                            {{ form.address }}
                        </li>
                        <li v-if="form.phone" class="flex gap-2">
                            <Phone class="mt-0.5 size-4 shrink-0" />
                            {{ form.phone }}
                        </li>
                        <li v-if="website" class="flex gap-2">
                            <ExternalLink class="mt-0.5 size-4 shrink-0" />
                            {{ website }}
                        </li>
                    </ul>
                </div>
            </aside>
        </div>
    </div>
</template>
