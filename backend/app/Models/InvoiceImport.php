<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOffice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceImport extends Model
{
    use BelongsToOffice;

    protected $fillable = [
        'office_id', 'client_id', 'user_id', 'status', 'files', 'total_files', 'processed_files', 'failed_files',
        'duplicated_documents', 'imported_items', 'errors', 'started_at', 'finished_at',
    ];

    protected $hidden = ['files'];

    protected function casts(): array
    {
        return [
            'files' => 'array',
            'errors' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function progress(): int
    {
        return $this->total_files > 0 ? (int) floor($this->processed_files * 100 / $this->total_files) : 0;
    }
}
