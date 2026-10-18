<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Download } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

const props = defineProps<{
    name: string;
    isDemo: boolean;
    counts: { bookings: number; customers: number };
    /** Bookings still to come, which stop the business being deleted. */
    upcoming: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Business settings', href: route('business.rules.edit') },
        ],
    },
});

const confirming = ref(false);
const form = useForm({ name: '', password: '' });

function destroy(): void {
    form.delete(route('business.destroy'), {
        preserveScroll: true,
        onError: () => form.reset('password'),
    });
}

const plural = (n: number, one: string, many: string) =>
    `${n} ${n === 1 ? one : many}`;
</script>

<template>
    <Head title="Your data" />

    <div class="space-y-10">
        <section class="space-y-4">
            <Heading
                variant="small"
                title="Download everything"
                description="A ZIP of spreadsheets: every booking, customer, payment and refund, your services, team and time off, and your settings."
            />
            <p class="text-sm text-muted-foreground">
                That's
                {{ plural(counts.bookings, 'booking', 'bookings') }} and
                {{ plural(counts.customers, 'customer', 'customers') }} so far.
                Times come in your own time and in UTC.
            </p>
            <Button as-child variant="outline">
                <a
                    :href="route('business.data.export')"
                    data-test="export"
                    download
                >
                    <Download class="size-4" />Download a copy
                </a>
            </Button>
        </section>

        <section
            class="space-y-4 rounded-xl border border-destructive/30 p-5"
            data-test="delete-business"
        >
            <Heading
                variant="small"
                title="Delete this business"
                description="Its booking page goes, and so does everything in it: bookings, customers, payments, services and the team. This can't be undone, so download a copy first."
            />

            <p v-if="isDemo" class="text-sm text-muted-foreground">
                Demo businesses can't be deleted. Everything in them is rebuilt
                every night anyway.
            </p>

            <p v-else-if="upcoming > 0" class="text-sm">
                {{
                    upcoming === 1
                        ? 'There is 1 booking'
                        : `There are ${upcoming} bookings`
                }}
                still to come. Cancel
                {{ upcoming === 1 ? 'it' : 'them' }} from the
                <Link
                    :href="route('calendar', { view: 'list' })"
                    class="font-medium underline underline-offset-4"
                    >calendar</Link
                >
                first, so customers are told and get their deposits back.
            </p>

            <template v-else>
                <Button
                    v-if="!confirming"
                    variant="outline"
                    class="text-destructive hover:text-destructive"
                    data-test="delete-start"
                    @click="confirming = true"
                    >Delete {{ props.name }}</Button
                >

                <form
                    v-else
                    class="grid max-w-md gap-4"
                    @submit.prevent="destroy"
                >
                    <div class="grid gap-2">
                        <Label for="confirm-name"
                            >Type
                            <strong class="font-semibold">{{
                                props.name
                            }}</strong>
                            to confirm</Label
                        >
                        <Input
                            id="confirm-name"
                            v-model="form.name"
                            autocomplete="off"
                            data-test="delete-name"
                        />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="confirm-password">Your password</Label>
                        <PasswordInput
                            id="confirm-password"
                            v-model="form.password"
                            autocomplete="current-password"
                            data-test="delete-password"
                        />
                        <InputError :message="form.errors.password" />
                    </div>
                    <div class="flex gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="confirming = false"
                            >Keep it</Button
                        >
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="form.processing"
                            data-test="delete-confirm"
                        >
                            <Spinner v-if="form.processing" />
                            Delete for good
                        </Button>
                    </div>
                </form>
            </template>
        </section>
    </div>
</template>
