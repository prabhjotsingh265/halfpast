<?php

namespace App\Demo;

use App\Enums\DepositType;

/**
 * The three demo businesses: a salon in Kolkata taking rupees, a barber in
 * London taking pounds, and a physio clinic in New York taking dollars.
 * Between them they show off time zones, currencies, deposit rules, split
 * shifts, time off and closures.
 */
final class Catalogue
{
    /** Every demo login uses this password; the one-click buttons don't need it. */
    public const PASSWORD = 'password';

    /**
     * @return list<array<string, mixed>>
     */
    public static function businesses(): array
    {
        return [self::kesar(), self::fadeAndCo(), self::stillwater()];
    }

    /**
     * @return array<string, mixed>
     */
    private static function kesar(): array
    {
        return [
            'slug' => 'kesar-beauty-lounge',
            'name' => 'Kesar Beauty Lounge',
            'summary' => 'A salon in Kolkata, in rupees',
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'settings' => ['slot_step_minutes' => 15, 'min_notice_minutes' => 60, 'cancellation_window_hours' => 24, 'payment_gateways' => ['stripe', 'razorpay', 'test']],
            'owner' => ['name' => 'Priya Sharma', 'email' => 'demo@halfpast.test'],
            'staff' => [
                ['key' => 'priya', 'owner' => true, 'bio' => 'Cuts and colour, twelve years in the chair.', 'hours' => [[[2, 3, 4, 5, 6], [['10:00', '14:00'], ['15:00', '19:00']]]]],
                ['key' => 'aisha', 'name' => 'Aisha Khan', 'bio' => 'Bridal and party make-up.', 'hours' => [[[3, 4, 5], [['11:00', '20:00']]], [[6, 7], [['10:00', '18:00']]]]],
                ['key' => 'rohan', 'name' => 'Rohan Mehta', 'bio' => 'Fades, beards and hot towel shaves.', 'hours' => [[[1, 2, 3, 4, 5, 6], [['09:30', '17:30']]]], 'login' => 'staff@halfpast.test'],
            ],
            'services' => [
                ['Haircut & style', 'Wash, cut and blow-dry.', 45, 0, 80000, DepositType::Percent, 20, ['priya', 'rohan']],
                ['Beard trim', 'Shape, line-up and hot towel.', 20, 0, 30000, DepositType::None, 0, ['rohan']],
                ['Eyebrow threading', 'Shape and tidy.', 15, 0, 15000, DepositType::None, 0, ['priya', 'aisha']],
                ['Hair colour', 'Global colour with a gloss finish.', 120, 15, 350000, DepositType::Fixed, 70000, ['priya']],
                ['Classic facial', 'Cleanse, steam, mask and massage.', 60, 15, 150000, DepositType::Fixed, 30000, ['priya', 'aisha']],
                ['Party make-up', 'Long-wear make-up for an evening out.', 90, 15, 250000, DepositType::Percent, 25, ['aisha']],
            ],
            'customers' => [
                ['Ananya Iyer', 'ananya@example.com', '98200 11223', 'Prefers Priya for colour.'],
                ['Kabir Singh', 'kabir@example.com', '98330 44556', null],
                ['Meera Nair', 'meera@example.com', '99870 77889', null],
                ['Vikram Rao', 'vikram@example.com', '90040 99001', null],
                ['Riya Kapoor', 'riya@example.com', '98111 23456', 'Allergic to ammonia-based dyes.'],
                ['Arjun Menon', 'arjun@example.com', '98450 67890', null],
                ['Sneha Patil', 'sneha@example.com', '97690 12345', null],
                ['Rahul Verma', 'rahul@example.com', '98919 54321', null],
                ['Ishaan Gupta', 'ishaan@example.com', '99300 11122', null],
                ['Pooja Desai', 'pooja@example.com', '98201 33344', null],
                ['Neha Joshi', 'neha@example.com', '97730 55566', null],
                ['Tanvi Shah', 'tanvi@example.com', '98670 77788', null],
            ],
            'time_off' => [
                ['staff' => 'aisha', 'from' => 10, 'days' => 3, 'reason' => 'Family wedding'],
                ['staff' => null, 'from' => 9, 'days' => 1, 'reason' => 'Team training day'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function fadeAndCo(): array
    {
        return [
            'slug' => 'fade-and-co',
            'name' => 'Fade & Co. Barbers',
            'summary' => 'A barber in London, in pounds',
            'timezone' => 'Europe/London',
            'currency' => 'GBP',
            'settings' => ['slot_step_minutes' => 15, 'min_notice_minutes' => 30, 'cancellation_window_hours' => 12, 'payment_gateways' => ['stripe', 'test']],
            'owner' => ['name' => 'Tom Walsh', 'email' => 'barber@halfpast.test'],
            'staff' => [
                ['key' => 'tom', 'owner' => true, 'bio' => 'Twenty years of fades and flat tops.', 'hours' => [[[2, 3, 4, 5, 6], [['09:00', '13:00'], ['13:30', '18:00']]]]],
                ['key' => 'kemi', 'name' => 'Kemi Adeyemi', 'bio' => 'Textured hair, twists and sharp line-ups.', 'hours' => [[[1, 2, 3, 4, 5], [['10:00', '19:00']]]]],
                ['key' => 'luca', 'name' => 'Luca Romano', 'bio' => 'Classic cuts and the best hot towel shave in town.', 'hours' => [[[3, 4, 5, 6, 7], [['11:00', '15:00'], ['15:30', '20:00']]]]],
            ],
            'services' => [
                ['Skin fade', 'Clippers down to the skin, blended up into any length.', 45, 0, 2800, DepositType::None, 0, ['tom', 'kemi', 'luca']],
                ['Classic cut', 'Scissors and clippers, finished with a wash.', 30, 0, 2200, DepositType::None, 0, ['tom', 'luca']],
                ['Beard sculpt', 'Shape, line-up and beard oil.', 20, 0, 1600, DepositType::None, 0, ['tom', 'kemi', 'luca']],
                ['Hot towel shave', 'Straight razor, hot towels and a cold finish.', 40, 5, 3000, DepositType::Fixed, 800, ['luca']],
                ['Cut & beard', 'The full works.', 60, 0, 4000, DepositType::Percent, 25, ['tom', 'kemi']],
                ["Kids' cut", 'For under-12s.', 30, 0, 1500, DepositType::None, 0, ['tom', 'kemi']],
            ],
            'customers' => [
                ['Oliver Bennett', 'oliver@example.com', '07700 900101', 'Number 2 on the sides, scissors on top.'],
                ['Amara Okafor', 'amara@example.com', '07700 900102', null],
                ['Jack Turner', 'jack@example.com', '07700 900103', null],
                ['Callum Reid', 'callum@example.com', '07700 900104', null],
                ['Hassan Ali', 'hassan@example.com', '07700 900105', 'Sensitive skin: no alcohol aftershave.'],
                ['George Hughes', 'george@example.com', '07700 900106', null],
                ['Ethan Clarke', 'ethan@example.com', '07700 900107', null],
                ['Noah Patel', 'noah@example.com', '07700 900108', null],
                ['Freddie Shaw', 'freddie@example.com', '07700 900109', null],
                ['Daniel Owusu', 'daniel@example.com', '07700 900110', null],
                ['Leo Murphy', 'leo@example.com', '07700 900111', null],
                ['Samuel Green', 'samuel@example.com', '07700 900112', null],
            ],
            'time_off' => [
                ['staff' => 'kemi', 'from' => 5, 'days' => 3, 'reason' => 'Holiday'],
                ['staff' => null, 'from' => 12, 'days' => 1, 'reason' => 'Shop refit'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function stillwater(): array
    {
        return [
            'slug' => 'stillwater-physio',
            'name' => 'Stillwater Physio',
            'summary' => 'A physio clinic in New York, in dollars',
            'timezone' => 'America/New_York',
            'currency' => 'USD',
            'settings' => ['slot_step_minutes' => 30, 'min_notice_minutes' => 720, 'cancellation_window_hours' => 48, 'booking_horizon_days' => 60, 'payment_gateways' => ['stripe', 'test']],
            'owner' => ['name' => 'Maya Chen', 'email' => 'clinic@halfpast.test'],
            'staff' => [
                ['key' => 'maya', 'owner' => true, 'bio' => 'Physical therapist; knees, shoulders and getting runners back out.', 'hours' => [[[1, 2, 3, 4], [['08:00', '16:00']]]]],
                ['key' => 'daniel', 'name' => 'Daniel Brooks', 'bio' => 'Physical therapist with a soft spot for desk-bound backs.', 'hours' => [[[2, 3, 4, 5, 6], [['10:00', '13:00'], ['14:00', '18:00']]]]],
                ['key' => 'sofia', 'name' => 'Sofia Alvarez', 'bio' => 'Massage therapist: sports, deep tissue and recovery.', 'hours' => [[[3, 4, 5, 6, 7], [['12:00', '20:00']]]]],
            ],
            'services' => [
                ['Initial assessment', 'An hour to work out what is going on and make a plan.', 60, 15, 14000, DepositType::Percent, 50, ['maya', 'daniel']],
                ['Follow-up session', 'Treatment and exercises, picking up where we left off.', 45, 15, 9500, DepositType::Fixed, 2500, ['maya', 'daniel']],
                ['Sports massage', 'Firm, focused work on tight spots.', 60, 10, 11000, DepositType::Fixed, 3000, ['sofia']],
                ['Deep tissue massage', 'Slow, deep pressure for long-held tension.', 90, 15, 15000, DepositType::Percent, 25, ['sofia']],
                ['Dry needling', 'Add-on for stubborn trigger points.', 30, 0, 7000, DepositType::None, 0, ['maya']],
                ['Posture check', 'A quick look at how you sit, stand and move.', 30, 0, 6000, DepositType::None, 0, ['daniel']],
            ],
            'customers' => [
                ['Emily Johnson', 'emily@example.com', '(212) 555-0101', 'Left knee, six weeks after ACL repair.'],
                ['Michael Rodriguez', 'michael@example.com', '(212) 555-0102', null],
                ['Sarah Kim', 'sarah@example.com', '(212) 555-0103', 'Prefers firm pressure.'],
                ['David Thompson', 'david@example.com', '(212) 555-0104', null],
                ['Jessica Lee', 'jessica@example.com', '(212) 555-0105', null],
                ['Christopher Garcia', 'chris@example.com', '(212) 555-0106', null],
                ['Ashley Martinez', 'ashley@example.com', '(212) 555-0107', null],
                ['Matthew Wilson', 'matthew@example.com', '(212) 555-0108', null],
                ['Lauren Davis', 'lauren@example.com', '(212) 555-0109', null],
                ['Andrew Nguyen', 'andrew@example.com', '(212) 555-0110', null],
                ['Rachel Brown', 'rachel@example.com', '(212) 555-0111', null],
                ["Kevin O'Brien", 'kevin@example.com', '(212) 555-0112', null],
            ],
            'time_off' => [
                ['staff' => 'sofia', 'from' => 6, 'days' => 2, 'reason' => 'Massage conference'],
                ['staff' => null, 'from' => 11, 'days' => 1, 'reason' => 'Clinic closed for staff training'],
            ],
        ];
    }
}
