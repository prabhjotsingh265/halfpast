<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            // The gateways the owner has switched on: ["stripe", "razorpay"].
            $table->jsonb('payment_gateways')->default('[]');
        });

        // One row per attempt to pay a deposit. A booking can have several
        // (a declined card, then a second try), but only one ends up captured.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 20);
            $table->unsignedInteger('amount');
            $table->char('currency', 3);
            $table->string('status', 24)->default('pending');
            // The gateway's checkout or order id, and its payment id once paid.
            $table->string('gateway_order_id')->nullable();
            $table->string('gateway_payment_id')->nullable();
            $table->timestampTz('authorized_at')->nullable();
            $table->timestampTz('captured_at')->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->unsignedInteger('refunded_amount')->default(0);
            // Why money was given back without the customer asking:
            // slot_taken, duplicate, amount_mismatch, capture_failed.
            $table->string('release_reason', 40)->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'gateway_order_id']);
            $table->index(['booking_id', 'status']);
        });

        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ('pending', 'authorized', 'captured', 'released', 'refunded', 'partially_refunded', 'failed'))");
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_refund_check CHECK (refunded_amount <= amount)');

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->string('status', 20)->default('pending');
            // customer_cancelled, business_cancelled, slot_taken, duplicate...
            $table->string('reason', 40);
            $table->string('gateway_refund_id')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at']);
        });

        DB::statement("ALTER TABLE refunds ADD CONSTRAINT refunds_status_check CHECK (status IN ('pending', 'succeeded', 'failed'))");
        DB::statement('ALTER TABLE refunds ADD CONSTRAINT refunds_amount_check CHECK (amount > 0)');

        // Every webhook received. Gateways resend them, so the unique pair
        // makes sure each one is only acted on once.
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 20);
            $table->string('event_id');
            $table->string('type', 100);
            $table->jsonb('payload');
            $table->timestampTz('processed_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['gateway', 'event_id']);
        });

        // Money in and out for each business. Deposits are positive, refunds
        // negative, so the balance is just the sum. Rows are never changed.
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->integer('amount');
            $table->char('currency', 3);
            $table->string('gateway', 20);
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['business_id', 'created_at']);
            // A payment is recorded as a deposit once, and each refund once.
            $table->unique(['payment_id', 'type', 'refund_id']);
        });

        DB::statement("ALTER TABLE ledger_entries ADD CONSTRAINT ledger_type_check CHECK ((type = 'deposit' AND amount > 0 AND refund_id IS NULL) OR (type = 'refund' AND amount < 0 AND refund_id IS NOT NULL))");
        // NULLs don't clash in a unique index, so deposits get their own rule.
        DB::statement("CREATE UNIQUE INDEX ledger_one_deposit_per_payment ON ledger_entries (payment_id) WHERE type = 'deposit'");
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('payment_gateways');
        });
    }
};
