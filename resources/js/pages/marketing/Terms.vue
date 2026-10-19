<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import LegalDocument from '@/components/marketing/LegalDocument.vue';

defineProps<{
    updated: string;
    contact: { email: string | null; issues: string };
}>();

const sections = [
    { id: 'what', title: 'What Halfpast is' },
    { id: 'account', title: 'Your account' },
    { id: 'page', title: 'Your booking page' },
    { id: 'booking', title: 'Booking with a business' },
    { id: 'payments', title: 'Deposits and refunds' },
    { id: 'use', title: 'Fair use' },
    { id: 'data', title: 'Your data' },
    { id: 'promises', title: 'What we can promise' },
    { id: 'changes', title: 'Changes and questions' },
];

const summary = [
    'Halfpast is a portfolio project, free while it’s in beta. Payments run in test mode: no real money moves.',
    'A business is responsible for what its page offers, its prices and its cancellation rules. Halfpast keeps to those rules exactly.',
    'A booking is between the customer and the business. Questions about one go to the business first.',
    'Use it honestly: no spam, nothing illegal, and no attacking the service or other people’s pages.',
    'Your data stays yours, to download or delete whenever you like.',
];
</script>

<template>
    <LegalDocument
        title="Terms of use"
        :updated="updated"
        :summary="summary"
        :sections="sections"
    >
        <h2 id="what">What Halfpast is</h2>
        <p>
            Halfpast gives small businesses a booking page that takes deposits,
            sends reminders and never double-books. It's a portfolio project
            built and run by Prabhjot Singh, free while it's in beta.
        </p>
        <p>
            <strong>Payments run in test mode.</strong> Card payments go through
            the payment providers' test systems, so no real money is charged,
            paid out or refunded. Please don't rely on Halfpast to run a real
            business yet. By using it, you agree to these terms and to how the
            <Link :href="route('privacy')">privacy policy</Link> says we handle
            data.
        </p>

        <h2 id="account">Your account</h2>
        <ul>
            <li>
                You need to be 16 or over, and to give your real name and an
                email address you can read.
            </li>
            <li>
                Keep your password to yourself. We recommend turning on
                two-factor sign-in in your settings.
            </li>
            <li>
                You're responsible for what happens under your account,
                including what people you add to your team do there.
            </li>
            <li>
                The demo logins are shared with everyone and rebuilt every
                night. Don't put anything private into them.
            </li>
        </ul>

        <h2 id="page">Your booking page</h2>
        <ul>
            <li>
                You decide what's offered: the services, prices, deposits, who
                does them and when. Make sure it's accurate and that you're
                allowed to offer it.
            </li>
            <li>
                Your booking rules (how much notice, how far ahead, and the
                cancellation window) are shown to customers before they book,
                and Halfpast applies them exactly as you set them.
            </li>
            <li>
                You're responsible for honouring the bookings your page takes,
                and for how you treat your customers' details. Use them for
                their bookings, not for things they didn't agree to.
            </li>
            <li>
                Your booking address must not pretend to be another business or
                person.
            </li>
        </ul>

        <h2 id="booking">Booking with a business</h2>
        <ul>
            <li>
                When you book, your booking is with that business, not with
                Halfpast. The business is responsible for the service itself.
            </li>
            <li>
                A time you pick is held for you for a few minutes while you
                finish; the page shows how long is left. If the hold runs out,
                the time goes back on offer.
            </li>
            <li>
                The link in your confirmation email lets you see and cancel your
                booking. Keep it to yourself: anyone with the link can use it.
            </li>
        </ul>

        <h2 id="payments">Deposits and refunds</h2>
        <ul>
            <li>
                Some services take a deposit when you book. You pay it on the
                payment provider's own page; Halfpast never sees your card.
            </li>
            <li>
                Cancel before the business's cancellation window and the deposit
                comes back to you automatically. Cancel inside it, or don't turn
                up, and the business keeps it. The exact time is shown before
                you confirm a cancellation.
            </li>
            <li>
                If a business cancels your booking, it decides whether to refund
                the deposit, and normally emails you to say so.
            </li>
            <li>
                In test mode, none of this moves real money: deposits, refunds
                and earnings are for show.
            </li>
        </ul>

        <h2 id="use">Fair use</h2>
        <p>Don't use Halfpast to:</p>
        <ul>
            <li>offer anything illegal, or harass or deceive anyone;</li>
            <li>send spam, or collect people's details for other purposes;</li>
            <li>
                attack, overload or probe the service, or try to reach another
                business's records;
            </li>
            <li>flood the demo businesses so other visitors can't try them.</li>
        </ul>
        <p>
            We may suspend or remove an account or booking page that breaks
            these terms, and we'll say why when we can.
        </p>

        <h2 id="data">Your data</h2>
        <p>
            What you put into Halfpast stays yours. You can download all of a
            business's data as spreadsheets, or delete the business and
            everything in it, whenever you like, from Business settings. The
            <Link :href="route('privacy')">privacy policy</Link> explains what's
            kept, why and for how long.
        </p>

        <h2 id="promises">What we can promise</h2>
        <p>
            We work hard to keep Halfpast running, correct and safe, and the
            code is tested on every change. But it's provided as it is, without
            guarantees: it may be unavailable at times, it may change, and it
            may one day close. If it closes, we'll give account holders notice
            and time to download their data.
        </p>
        <p>
            As far as the law allows, we aren't responsible for indirect losses,
            such as missed bookings or lost income, that come from using or not
            being able to use Halfpast. Nothing here limits rights you have by
            law that can't be limited.
        </p>

        <h2 id="changes">Changes and questions</h2>
        <p>
            If these terms change, this page and the date at the top will say
            so, and we'll email account holders about anything important before
            it takes effect.
        </p>
        <p>
            Questions?
            <template v-if="contact.email">
                Email
                <a :href="`mailto:${contact.email}`">{{ contact.email }}</a
                >.
            </template>
            <template v-else>
                Open an issue on
                <a :href="contact.issues">the project's GitHub page</a>.
            </template>
        </p>
    </LegalDocument>
</template>
