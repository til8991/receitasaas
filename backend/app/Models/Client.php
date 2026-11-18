<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOffice;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Client extends Model
{
    use BelongsToOffice, HasFactory;

    public const REGIMES = ['simples_nacional', 'mei', 'lucro_presumido', 'lucro_real'];
    public const ANNEXES = ['I', 'II', 'III', 'IV', 'V'];

    protected $fillable = [
        'cnpj', 'legal_name', 'trade_name', 'uf', 'municipality_ibge', 'current_regime', 'simples_annex',
        'activities', 'revenue_12m', 'purchases_12m', 'b2b_sales_pct', 'default_ncm', 'default_nbs',
        'default_cst', 'default_c_class_trib', 'assumptions', 'active', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'activities' => 'array',
            'assumptions' => 'array',
            'active' => 'boolean',
            'revenue_12m' => 'decimal:2',
            'purchases_12m' => 'decimal:2',
            'b2b_sales_pct' => 'decimal:2',
            'municipality_ibge' => 'integer',
        ];
    }

    public function invoiceImports(): HasMany
    {
        return $this->hasMany(InvoiceImport::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function comparisons(): HasMany
    {
        return $this->hasMany(Comparison::class);
    }

    public function latestComparison(): HasOne
    {
        return $this->hasOne(Comparison::class)->latestOfMany();
    }
}
