<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOffice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comparison extends Model
{
    use BelongsToOffice;

    public const RECOMMEND_KEEP = 'manter_simples';
    public const RECOMMEND_MIGRATE = 'migrar_regular';
    public const RECOMMEND_NEUTRAL = 'indiferente';

    protected $fillable = [
        'office_id', 'client_id', 'user_id', 'simulation_batch_id', 'reference_year', 'source', 'status',
        'input', 'assumptions', 'result', 'recommendation', 'error_message', 'report_path',
        'report_generated_at', 'calculated_at',
    ];

    protected $hidden = ['report_path'];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'assumptions' => 'array',
            'result' => 'array',
            'calculated_at' => 'datetime',
            'report_generated_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function simulations(): HasMany
    {
        return $this->hasMany(Simulation::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SimulationBatch::class, 'simulation_batch_id');
    }
}
