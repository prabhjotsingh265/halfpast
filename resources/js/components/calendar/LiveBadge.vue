<script setup lang="ts">
import { liveState } from '@/lib/live';

/**
 * Whether live updates are connected: a green dot when new bookings will
 * appear by themselves.
 */
const enabled = Boolean(import.meta.env.VITE_REVERB_APP_KEY);
</script>

<template>
    <p
        v-if="enabled"
        class="flex items-center gap-1.5 text-xs text-muted-foreground"
        :title="
            liveState === 'connected'
                ? 'New bookings appear here as they come in.'
                : 'Reconnecting. Reload the page if this stays.'
        "
        data-test="live"
        :data-state="liveState"
    >
        <span
            class="size-2 rounded-full"
            :class="
                liveState === 'connected'
                    ? 'bg-success'
                    : liveState === 'connecting'
                      ? 'animate-pulse bg-sun'
                      : 'bg-muted-foreground/40'
            "
        />
        {{
            liveState === 'connected'
                ? 'Live'
                : liveState === 'connecting'
                  ? 'Connecting'
                  : 'Not live'
        }}
    </p>
</template>
