<?php

namespace App\Models;

use App\Enums\LoanItemStatus;
use Database\Factories\EquipmentLoanItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EquipmentLoanItem extends Model
{
    /** @use HasFactory<EquipmentLoanItemFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'equipment_loan_items';

    protected $fillable = [
        'loan_id',
        'equipment_id',
        'quantity',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LoanItemStatus::class,
            'quantity' => 'integer',
        ];
    }

    /**
     * Get the loan associated with this item.
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(EquipmentLoan::class, 'loan_id');
    }

    /**
     * Get the equipment associated with this item.
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }
}
