<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            // Stored lower-case. Empty for walk-ins booked by hand.
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // One customer record per email address within a business.
        DB::statement('CREATE UNIQUE INDEX customers_business_email_unique ON customers (business_id, email) WHERE email IS NOT NULL');

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            // Kept even if the service, person or customer is removed later,
            // so the history still reads properly.
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_name', 80);

            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            // The end plus the service's free time after, which nobody else can book.
            $table->timestampTz('blocked_until');

            $table->string('status', 20);
            $table->timestampTz('hold_expires_at')->nullable();
            $table->string('source', 20)->default('online');

            // Minor units, copied from the service when the booking is made.
            $table->unsignedInteger('price');
            $table->unsignedInteger('deposit');
            $table->char('currency', 3);

            // The code in the customer's manage-booking link.
            $table->string('code', 12)->unique();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'starts_at']);
            $table->index(['status', 'hold_expires_at']);
        });

        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_status_check CHECK (status IN ('held', 'confirmed', 'expired', 'cancelled', 'completed', 'no_show'))");
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_times_check CHECK (starts_at < ends_at AND ends_at <= blocked_until)');

        // The guarantee against double booking. Checking "is it free?" and
        // then inserting leaves a gap where two requests can both see the
        // time as free, so PostgreSQL itself refuses a second held or
        // confirmed booking that overlaps one for the same person. '[)'
        // means a booking ending at 15:30 and one starting at 15:30 don't clash.
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        DB::statement(<<<'SQL'
            ALTER TABLE bookings ADD CONSTRAINT bookings_no_overlap
            EXCLUDE USING gist (
                staff_id WITH =,
                tstzrange(starts_at, blocked_until, '[)') WITH &&
            )
            WHERE (status IN ('held', 'confirmed'))
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('customers');
    }
};
