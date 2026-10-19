<script setup lang="ts">
import LegalDocument from '@/components/marketing/LegalDocument.vue';

defineProps<{
    updated: string;
    contact: { email: string | null; issues: string };
}>();

const sections = [
    { id: 'who', title: 'Who we are' },
    { id: 'what', title: 'What we keep' },
    { id: 'why', title: 'Why we keep it' },
    { id: 'cookies', title: 'Cookies' },
    { id: 'sharing', title: 'Who else sees it' },
    { id: 'how-long', title: 'How long we keep it' },
    { id: 'choices', title: 'Your choices' },
    { id: 'security', title: 'Keeping it safe' },
    { id: 'changes', title: 'Changes and questions' },
];

const summary = [
    'We keep what a booking needs: who booked, what, when, and whether a deposit was paid. Nothing more.',
    'Card details go straight to the payment provider. We never see or store them.',
    'No adverts, no tracking, no analytics. The only cookies are the ones that keep you signed in and remember your light or dark choice.',
    'A business can download or delete everything at any time. Its customers can ask it to remove their details.',
    'Halfpast is a portfolio project running in test mode. Please don’t put real personal details into the demo businesses.',
];
</script>

<template>
    <LegalDocument
        title="Privacy policy"
        :updated="updated"
        :summary="summary"
        :sections="sections"
    >
        <h2 id="who">Who we are</h2>
        <p>
            Halfpast is a booking service for small businesses: salons, clinics,
            tutors and the like. It's a portfolio project built and run by
            Prabhjot Singh. Payments run in test mode, so no real money changes
            hands.
        </p>
        <p>
            There are two kinds of people here, and we treat them differently:
        </p>
        <ul>
            <li>
                <strong>Businesses</strong> (owners and their team) have an
                account with us. For their account, we decide what's kept and
                why.
            </li>
            <li>
                <strong>Customers</strong> book with a business through its
                booking page. Their booking belongs to that business, which
                decides how it uses their details; we keep and process them on
                the business's behalf. Questions about a booking are best sent
                to the business first.
            </li>
        </ul>

        <h2 id="what">What we keep</h2>
        <h3>About businesses and their team</h3>
        <ul>
            <li>
                Your name, email address and password. The password is stored
                only as a one-way hash, so even we can't read it. If you turn on
                two-factor sign-in, its secret is stored encrypted.
            </li>
            <li>
                Your business: its name, booking address, time zone, currency,
                logo and the profile you write; your services, prices and
                deposit rules; your team's names, photos and working hours; your
                time off and closures.
            </li>
            <li>
                The bookings, customers and payments your page takes, and who on
                your team marked or cancelled a booking.
            </li>
        </ul>
        <h3>About customers</h3>
        <ul>
            <li>
                The name, email address and phone number given when booking, and
                anything written in the notes.
            </li>
            <li>
                The booking itself: the service, who it's with, the time, and
                what happened to it (confirmed, completed, cancelled or missed).
            </li>
            <li>
                For a deposit: the amount, the payment provider, its reference
                for the payment, and whether it was paid, refunded or kept.
                Never card or bank details.
            </li>
            <li>Private notes the business adds to its own customer list.</li>
        </ul>
        <h3>About everyone</h3>
        <ul>
            <li>
                Our servers write an error log when something goes wrong, which
                can include the page address and your IP address.
            </li>
            <li>
                To slow down password guessing and abuse, we briefly count
                attempts by email address and IP address.
            </li>
        </ul>
        <p>
            We don't use analytics, advertising or tracking tools of any kind,
            and we don't build profiles of anyone.
        </p>

        <h2 id="why">Why we keep it</h2>
        <ul>
            <li>
                <strong>To provide the service</strong> you signed up for, or
                the booking you made: showing free times, holding a slot, taking
                a deposit, sending the confirmation and reminders, and refunding
                when a cancellation qualifies.
            </li>
            <li>
                <strong>To keep it safe and working:</strong> signing you in,
                stopping abuse, and finding and fixing faults.
            </li>
            <li>
                <strong>Because the law asks:</strong> for example, keeping a
                record of payments.
            </li>
        </ul>
        <p>
            We only send emails about your account or your booking:
            confirmations, reminders 24 and 2 hours before, cancellations and
            refunds. Never newsletters or marketing.
        </p>

        <h2 id="cookies">Cookies</h2>
        <p>
            Only what the site needs to work, so there's no cookie banner to
            click through.
        </p>
        <table>
            <thead>
                <tr>
                    <th>Cookie</th>
                    <th>What it's for</th>
                    <th>How long</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>halfpast-session</code></td>
                    <td>
                        Keeps you signed in and remembers a slot you're holding
                    </td>
                    <td>2 hours after your last visit</td>
                </tr>
                <tr>
                    <td><code>XSRF-TOKEN</code></td>
                    <td>
                        Proves a form was sent from this site, not another one
                    </td>
                    <td>The same as the session</td>
                </tr>
                <tr>
                    <td><code>remember_web_…</code></td>
                    <td>Only if you tick "Remember me": signs you back in</td>
                    <td>Until you sign out, at most about a year</td>
                </tr>
                <tr>
                    <td><code>appearance</code></td>
                    <td>
                        Your light, dark or system choice (also kept in your
                        browser's storage)
                    </td>
                    <td>A year</td>
                </tr>
                <tr>
                    <td><code>sidebar_state</code></td>
                    <td>Whether the dashboard's sidebar is open</td>
                    <td>A week</td>
                </tr>
            </tbody>
        </table>
        <p>
            Payment providers set their own cookies on their own pages while you
            pay; their privacy policies cover those.
        </p>

        <h2 id="sharing">Who else sees it</h2>
        <p>We never sell anyone's details or share them for advertising.</p>
        <ul>
            <li>
                <strong>The business you book with</strong> sees your booking
                and the details you gave.
            </li>
            <li>
                <strong>Payment providers</strong> (Stripe, Razorpay and PayPal)
                take payment on their own pages, under their own privacy
                policies. We pass them the amount, the currency and a
                description of the booking.
            </li>
            <li>
                <strong>Our email provider</strong> delivers confirmations and
                reminders, and <strong>our hosting provider</strong> runs the
                servers the data is stored on. They handle it only to provide
                those services to us.
            </li>
            <li>
                <strong>The law:</strong> if we're legally required to, we may
                share specific information with the authorities.
            </li>
        </ul>

        <h2 id="how-long">How long we keep it</h2>
        <table>
            <thead>
                <tr>
                    <th>What</th>
                    <th>How long</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>A business and everything in it</td>
                    <td>
                        Until the owner deletes the business, which removes it
                        straight away
                    </td>
                </tr>
                <tr>
                    <td>An account</td>
                    <td>Until you delete it in your settings</td>
                </tr>
                <tr>
                    <td>A customer's details</td>
                    <td>
                        As long as the business keeps them, or until it removes
                        them on request; past bookings stay, with nothing
                        personal left in them
                    </td>
                </tr>
                <tr>
                    <td>The demo businesses</td>
                    <td>
                        Rebuilt every night; anything typed there is gone by
                        morning
                    </td>
                </tr>
                <tr>
                    <td>Error logs</td>
                    <td>14 days</td>
                </tr>
                <tr>
                    <td>Performance records</td>
                    <td>7 days</td>
                </tr>
                <tr>
                    <td>Backups</td>
                    <td>Deleted data leaves them within 30 days</td>
                </tr>
            </tbody>
        </table>

        <h2 id="choices">Your choices</h2>
        <ul>
            <li>
                <strong>Businesses</strong> can download everything, as
                spreadsheets, from Business settings under Your data, and delete
                the business there for good.
            </li>
            <li>
                <strong>Anyone with an account</strong> can change their details
                or delete their account in Settings.
            </li>
            <li>
                <strong>Customers</strong> can cancel through the link in their
                confirmation email, and can ask the business to correct or
                remove their details. A business can remove a customer's details
                in one click from its customer list.
            </li>
        </ul>
        <p>
            Wherever you live, you can ask us what we hold about you, ask us to
            correct or delete it, or object to how it's used. If you're unhappy
            with our answer, you can complain to your local data protection
            authority.
        </p>

        <h2 id="security">Keeping it safe</h2>
        <ul>
            <li>Every page is served over an encrypted connection.</li>
            <li>
                Passwords are hashed, two-factor sign-in is available, and
                sign-in attempts are limited.
            </li>
            <li>
                Links for managing a booking are signed, so they can't be
                guessed or altered.
            </li>
            <li>
                Each business only ever sees its own records, and the tests
                check that on every change.
            </li>
        </ul>
        <p>
            Halfpast isn't meant for children: you need to be 16 or over to open
            an account.
        </p>

        <h2 id="changes">Changes and questions</h2>
        <p>
            If this policy changes, this page and the date at the top will say
            so. For anything important, we'll tell account holders by email
            first.
        </p>
        <p>
            Questions, or a request about your details?
            <template v-if="contact.email">
                Email
                <a :href="`mailto:${contact.email}`">{{ contact.email }}</a
                >.
            </template>
            <template v-else>
                Open an issue on
                <a :href="contact.issues">the project's GitHub page</a>, and
                leave personal details out of it; we'll reply there with a way
                to talk privately.
            </template>
        </p>
    </LegalDocument>
</template>
