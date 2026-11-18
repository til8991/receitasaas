<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOffice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use BelongsToOffice;

    public const DIRECTION_SALE = 'saida';
    public const DIRECTION_PURCHASE = 'entrada';

    protected $fillable = [
        'office_id', 'client_id', 'invoice_import_id', 'document_type', 'document_key', 'document_number',
        'issued_at', 'direction', 'issuer_document', 'recipient_document', 'recipient_type', 'recipient_uf',
        'item_number', 'description', 'ncm', 'nbs', 'cfop', 'cst', 'c_class_trib', 'quantity', 'unit',
        'product_amount', 'freight', 'insurance', 'other', 'discount', 'base_amount',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'quantity' => 'decimal:4',
            'product_amount' => 'decimal:2',
            'freight' => 'decimal:2',
            'insurance' => 'decimal:2',
            'other' => 'decimal:2',
            'discount' => 'decimal:2',
            'base_amount' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
