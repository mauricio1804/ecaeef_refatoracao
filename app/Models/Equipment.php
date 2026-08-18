<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipment extends Model
{
    /** @use HasFactory<\Database\Factories\EquipmentFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'equipments';

    protected $fillable = [
        'name',
        'asset_number',
    ];

    /**
     * The classes that belong to the equipment.
     */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(CourseClass::class, 'class_equipments', 'equipment_id', 'class_id')
                    ->withPivot('quantity')
                    ->withTimestamps();
    }

    /**
     * The loan items that belong to the equipment.
     */
    public function loanItems(): HasMany
    {
        return $this->hasMany(EquipmentLoanItem::class, 'equipment_id');
    }

    /**
     * The loans that contain this equipment.
     */
    public function loans(): BelongsToMany
    {
        return $this->belongsToMany(EquipmentLoan::class, 'equipment_loan_items', 'equipment_id', 'loan_id')
                    ->withPivot(['quantity', 'status'])
                    ->withTimestamps();
    }
}
