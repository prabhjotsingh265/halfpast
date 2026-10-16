<?php

namespace App\Tenancy;

use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For every table that holds one business's data. Queries only ever see the
 * current business's rows, and new rows are stamped with it automatically.
 *
 * @mixin Model
 */
trait BelongsToBusiness
{
    public static function bootBelongsToBusiness(): void
    {
        static::addGlobalScope('business', function (Builder $query): void {
            $id = app(CurrentBusiness::class)->id();

            if ($id !== null) {
                $query->where($query->qualifyColumn('business_id'), $id);
            }
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('business_id') === null) {
                $model->setAttribute('business_id', app(CurrentBusiness::class)->require()->id);
            }
        });
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
