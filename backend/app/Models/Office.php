<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Office extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'cnpj', 'email', 'phone', 'logo_path', 'primary_color', 'secondary_color',
        'plan', 'plan_status', 'trial_ends_at', 'billing_customer_id', 'billing_subscription_id', 'assumptions',
    ];

    protected $hidden = ['billing_customer_id', 'billing_subscription_id'];

    protected $appends = ['logo_url'];

    protected function casts(): array
    {
        return [
            'assumptions' => 'array',
            'trial_ends_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    /** Count used for per-CNPJ billing (active clients). */
    public function activeClientsCount(): int
    {
        return $this->clients()->withoutGlobalScopes()->where('active', true)->count();
    }
}
