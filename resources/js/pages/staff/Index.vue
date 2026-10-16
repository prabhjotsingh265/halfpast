<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarOff, Pencil, Plus, Trash2, Users } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import MemberAvatar from '@/components/staff/MemberAvatar.vue';
import WeekStrip from '@/components/staff/WeekStrip.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { duration } from '@/lib/time';
import { weekFromHours, weekMinutes, weekSummary } from '@/lib/week';
import type { StaffMember } from '@/types/staff';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Team', href: route('staff.index') }],
    },
});

const props = defineProps<{
    staff: StaffMember[];
}>();

const people = props.staff.map((member) => {
    const week = weekFromHours(member.hours);

    return {
        ...member,
        week,
        summary: weekSummary(week),
        total: weekMinutes(week),
    };
});

function remove(member: StaffMember): void {
    if (
        window.confirm(
            `Remove ${member.name} from the team? Customers won't be able to book them any more.`,
        )
    ) {
        router.delete(route('staff.destroy', member.id), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Team" />

    <div class="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <Heading
                title="Team"
                description="The people customers can book, what they do and when they work."
            />
            <Button as-child>
                <Link :href="route('staff.create')">
                    <Plus class="size-4" />
                    Add someone
                </Link>
            </Button>
        </div>

        <div
            v-if="people.length === 0"
            class="flex flex-col items-center rounded-xl border border-dashed px-6 py-16 text-center"
        >
            <span class="grid size-12 place-items-center rounded-full bg-muted">
                <Users class="size-5 text-muted-foreground" />
            </span>
            <h2 class="mt-4 text-lg font-semibold">No one on the team yet</h2>
            <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                Add the people customers can book, with the services they do and
                the hours they work.
            </p>
            <Button as-child class="mt-6">
                <Link :href="route('staff.create')">Add the first person</Link>
            </Button>
        </div>

        <ul v-else class="grid gap-4 md:grid-cols-2">
            <li
                v-for="(member, index) in people"
                :key="member.id"
                class="group flex animate-in flex-col gap-4 rounded-xl border bg-card p-4 transition-shadow fade-in slide-in-from-bottom-2 hover:shadow-md md:p-5"
                :style="{ animationDelay: `${index * 50}ms` }"
                :data-test="`member-${member.id}`"
            >
                <div class="flex items-start gap-3">
                    <MemberAvatar :name="member.name" />
                    <div class="min-w-0 flex-1">
                        <p
                            class="flex flex-wrap items-center gap-2 font-semibold"
                        >
                            {{ member.name }}
                            <Badge v-if="!member.is_active" variant="secondary"
                                >Not taking bookings</Badge
                            >
                        </p>
                        <p
                            class="mt-0.5 line-clamp-2 text-sm text-muted-foreground"
                        >
                            {{
                                member.bio ||
                                (member.total
                                    ? `${duration(member.total)} a week`
                                    : 'No hours set yet')
                            }}
                        </p>
                    </div>
                    <div class="flex gap-1">
                        <Button
                            as-child
                            variant="ghost"
                            size="icon"
                            :aria-label="`Time off for ${member.name}`"
                        >
                            <Link
                                :href="
                                    route('time-off.index', {
                                        staff: member.id,
                                    })
                                "
                            >
                                <CalendarOff class="size-4" />
                            </Link>
                        </Button>
                        <Button
                            as-child
                            variant="ghost"
                            size="icon"
                            :aria-label="`Edit ${member.name}`"
                        >
                            <Link :href="route('staff.edit', member.id)">
                                <Pencil class="size-4" />
                            </Link>
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            :aria-label="`Remove ${member.name}`"
                            @click="remove(member)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </div>

                <WeekStrip :week="member.week" />

                <p class="text-xs text-muted-foreground">
                    {{ member.summary }}
                </p>

                <div class="mt-auto flex flex-wrap gap-1.5">
                    <Badge
                        v-for="service in member.services"
                        :key="service"
                        variant="outline"
                        class="font-normal"
                        >{{ service }}</Badge
                    >
                    <p
                        v-if="!member.services?.length"
                        class="text-xs text-destructive"
                    >
                        Not offering any services yet, so no one can book them.
                    </p>
                </div>
            </li>
        </ul>
    </div>
</template>
