<script setup lang="ts">
import { Users } from '@lucide/vue';
import MemberAvatar from '@/components/staff/MemberAvatar.vue';
import type { BookableStaff } from '@/types/booking';

defineProps<{
    staff: BookableStaff[];
    /** Null means anyone. */
    selected: number | null;
}>();

const emit = defineEmits<{ select: [id: number | null] }>();
</script>

<template>
    <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Who with">
        <button
            type="button"
            role="radio"
            :aria-checked="selected === null"
            class="flex items-center gap-2.5 rounded-full border py-1.5 pr-4 pl-1.5 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            :class="
                selected === null
                    ? 'border-foreground bg-foreground text-background'
                    : 'bg-card hover:border-foreground/30'
            "
            data-test="staff-anyone"
            @click="emit('select', null)"
        >
            <span class="flex -space-x-2">
                <MemberAvatar
                    v-for="member in staff.slice(0, 3)"
                    :key="member.id"
                    :name="member.name"
                    :photo="member.photo_url"
                    size="xs"
                    class="ring-2 ring-background"
                />
                <span
                    v-if="staff.length === 0"
                    class="grid size-6 place-items-center rounded-full bg-muted"
                >
                    <Users class="size-3.5" />
                </span>
            </span>
            Anyone free
        </button>
        <button
            v-for="member in staff"
            :key="member.id"
            type="button"
            role="radio"
            :aria-checked="selected === member.id"
            :title="member.bio ?? undefined"
            class="flex items-center gap-2.5 rounded-full border py-1.5 pr-4 pl-1.5 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            :class="
                selected === member.id
                    ? 'border-foreground bg-foreground text-background'
                    : 'bg-card hover:border-foreground/30'
            "
            :data-test="`staff-${member.id}`"
            @click="emit('select', member.id)"
        >
            <MemberAvatar
                :name="member.name"
                :photo="member.photo_url"
                size="xs"
            />
            {{ member.name }}
        </button>
    </div>
</template>
