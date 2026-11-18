<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOffice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Simulation extends Model
{
    use BelongsToOffice;

    public const SCENARIO_SIMPLES = 'simples_integral';
    public const SCENARIO_REGULAR = 'ibs_cbs_regular';
    public const SCENARIOS = [self::SCENARIO_SIMPLES, self::SCENARIO_REGULAR];

    protected $fillable = [
        'office_id', 'client_id', 'comparison_id', 'reference_year', 'scenario', 'status', 'input',
        'request_payload', 'response_raw', 'calculator_version', 'totals', 'item_errors', 'error_message',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'request_payload' => 'array',
            'response_raw' => 'array',
            'calculator_version' => 'array',
            'totals' => 'array',
            'item_errors' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    public function comparison(): BelongsTo
    {
        return $this->belongsTo(Comparison::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
