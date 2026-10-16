<?php

use App\Models\Business;
use App\Tenancy\BelongsToBusiness;
use App\Tenancy\CurrentBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A throwaway model, so the trait is tested on its own before real models use it.
 */
class TenancyTestNote extends Model
{
    use BelongsToBusiness;

    protected $table = 'tenancy_test_notes';

    protected $guarded = [];
}

beforeEach(function () {
    Schema::create('tenancy_test_notes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('business_id')->constrained();
        $table->string('body');
        $table->timestamps();
    });

    $this->glow = Business::factory()->create();
    $this->northside = Business::factory()->create();
    $this->current = app(CurrentBusiness::class);
});

it('stamps new rows with the current business', function () {
    $this->current->set($this->glow);

    $note = TenancyTestNote::create(['body' => 'Restock colour']);

    expect($note->business_id)->toBe($this->glow->id)
        ->and($note->business->is($this->glow))->toBeTrue();
});

it("only ever sees the current business's rows", function () {
    $this->current->set($this->glow);
    TenancyTestNote::create(['body' => 'Glow note']);

    $this->current->set($this->northside);
    TenancyTestNote::create(['body' => 'Northside note']);

    expect(TenancyTestNote::pluck('body')->all())->toBe(['Northside note']);

    $this->current->set($this->glow);

    expect(TenancyTestNote::pluck('body')->all())->toBe(['Glow note'])
        ->and(TenancyTestNote::whereKey(2)->first())->toBeNull();
});

it('refuses to create a row when no business is set', function () {
    TenancyTestNote::create(['body' => 'Orphan']);
})->throws(LogicException::class);
