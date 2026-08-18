<?php

namespace App\Models;

use Database\Factories\LoaneeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Loanee extends Model
{
    /** @use HasFactory<LoaneeFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'loanees';

    protected $fillable = [
        'name',
        'document_number',
        'contact',
    ];

    /**
     * Get all loans for this loanee.
     */
    public function loans(): HasMany
    {
        return $this->hasMany(EquipmentLoan::class, 'loanee_id');
    }
}
