<?php

namespace App\Models;

use App\Enums\LoanItemStatus;
use Database\Factories\EquipmentLoanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EquipmentLoan extends Model
{
    /** @use HasFactory<EquipmentLoanFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'equipment_loans';

    protected $fillable = [
        'loanee_id',
        'loaned_at',
        'returns_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'loaned_at' => 'datetime',
            'returns_at' => 'datetime',
        ];
    }

    /**
     * Get the loanee associated with the loan.
     */
    public function loanee(): BelongsTo
    {
        return $this->belongsTo(Loanee::class, 'loanee_id');
    }

    /**
     * Get the items in this loan.
     */
    public function items(): HasMany
    {
        return $this->hasMany(EquipmentLoanItem::class, 'loan_id');
    }

    /**
     * Get the equipments in this loan.
     */
    public function equipments(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class, 'equipment_loan_items', 'loan_id', 'equipment_id')
            ->withPivot(['id', 'quantity', 'status'])
            ->withTimestamps();
    }

    /**
     * Check if all items in this loan are returned or finalized.
     */
    public function isFullyReturned(): bool
    {
        if ($this->items->isEmpty()) {
            return false;
        }

        return $this->items->every(fn (EquipmentLoanItem $item) => $item->status !== LoanItemStatus::BORROWED);
    }

    /**
     * Check if the loan is overdue.
     */
    public function isOverdue(): bool
    {
        if ($this->isFullyReturned()) {
            return false;
        }

        return $this->returns_at && $this->returns_at->isPast();
    }
}
