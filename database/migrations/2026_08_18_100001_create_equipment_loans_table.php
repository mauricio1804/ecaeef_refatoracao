<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('equipment_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loanee_id')->constrained('loanees')->cascadeOnDelete();
            $table->timestamp('loaned_at');
            $table->timestamp('returns_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_loans');
    }
};
