<?php

namespace App\Models\Concerns;

use App\Models\Office;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Multi-tenancy by office: every query is scoped to the current tenant and new
 * records get the tenant's office_id automatically. Jobs set the tenant explicitly
 * (Tenant::run) because there is no authenticated user in the queue worker.
 */
trait BelongsToOffice
{
    public static function bootBelongsToOffice(): void
    {
        static::addGlobalScope('office', function (Builder $builder) {
            $officeId = Tenant::id();
            if ($officeId !== null) {
                $builder->where($builder->getModel()->qualifyColumn('office_id'), $officeId);
            }
        });

        static::creating(function ($model) {
            if (empty($model->office_id) && Tenant::id() !== null) {
                $model->office_id = Tenant::id();
            }
        });
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }
}
