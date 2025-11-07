<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('handoff_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->json('conditions');
            $table->json('actions');
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handoff_rules');
    }
};
