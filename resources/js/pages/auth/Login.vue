<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        title: 'Log in to your account',
        description: 'Enter your email and password below to log in',
    },
});

withDefaults(
    defineProps<{
        status?: string;
        canResetPassword: boolean;
        /** One-click logins into the demo businesses, while demo mode is on. */
        demos?: {
            slug: string;
            name: string;
            summary: string;
            logins: { as: 'owner' | 'staff'; label: string }[];
        }[];
    }>(),
    { demos: () => [] },
);

const entering = ref<string | null>(null);

function enterDemo(slug: string, as: string): void {
    router.post(
        route('demo.login', { slug }),
        { as },
        {
            onStart: () => (entering.value = `${slug}:${as}`),
            onFinish: () => (entering.value = null),
        },
    );
}
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <Form
        :action="route('login.store')"
        method="post"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    v-focus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Password</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-sm"
                        :tabindex="5"
                    >
                        Forgot your password?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Password"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>Remember me</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Log in
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Don't have an account?
            <TextLink :href="route('register')" :tabindex="5">Sign up</TextLink>
        </div>
    </Form>

    <section
        v-if="demos.length > 0"
        class="mt-8 border-t pt-6"
        aria-labelledby="demo-heading"
        data-test="demo-logins"
    >
        <h2 id="demo-heading" class="text-center text-sm font-semibold">
            Or look around a demo
        </h2>
        <p class="mt-1 text-center text-xs text-muted-foreground">
            No sign-up. Change anything you like: it resets every night.
        </p>
        <ul class="mt-4 space-y-2">
            <li
                v-for="demo in demos"
                :key="demo.slug"
                class="rounded-xl border p-3"
            >
                <p class="text-sm font-medium">{{ demo.name }}</p>
                <p class="text-xs text-muted-foreground">{{ demo.summary }}</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <Button
                        v-for="login in demo.logins"
                        :key="login.as"
                        type="button"
                        size="sm"
                        :variant="login.as === 'owner' ? 'default' : 'outline'"
                        :disabled="entering !== null"
                        :data-test="`demo-${demo.slug}-${login.as}`"
                        @click="enterDemo(demo.slug, login.as)"
                    >
                        <Spinner
                            v-if="entering === `${demo.slug}:${login.as}`"
                        />
                        As {{ login.label }}
                    </Button>
                </div>
            </li>
        </ul>
    </section>
</template>
