<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOffice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SimulationBatch extends Model
{
    use BelongsToOffice;

    protected $fillable = [
        'office_id', 'user_id', 'reference_year', 'source', 'status', 'client_ids', 'total', 'processed',
        'failed', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'client_ids' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function comparisons(): HasMany
    {
        return $this->hasMany(Comparison::class);
    }

    public function progress(): int
    {
        return $this->total > 0 ? (int) floor(($this->processed + $this->failed) * 100 / $this->total) : 0;
    }
}
