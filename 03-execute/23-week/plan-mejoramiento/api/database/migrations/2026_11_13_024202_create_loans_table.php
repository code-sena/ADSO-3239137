<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();

            $table->foreignId('beneficiary_id')
                ->constrained('beneficiaries')
                ->restrictOnDelete();

            $table->foreignId('manager_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('loaned_at');

            $table->timestamps();

            $table->index(['beneficiary_id', 'loaned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};