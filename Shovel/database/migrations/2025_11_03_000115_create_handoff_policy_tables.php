<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('handoff_policies')) {
            Schema::create('handoff_policies', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('reason_code');
                $table->decimal('confidence_threshold', 5, 4)->nullable();
                $table->json('metadata')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->unique('reason_code');
                $table->index('active');
            });
        }

        if (! Schema::hasTable('handoff_policy_rules')) {
            Schema::create('handoff_policy_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('handoff_policy_id')->constrained()->cascadeOnDelete();
                $table->string('trigger_type');
                $table->json('criteria')->nullable();
                $table->unsignedInteger('priority')->default(0);
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->index(['handoff_policy_id', 'active']);
                $table->index(['trigger_type', 'active']);
            });
        }

        if (! Schema::hasTable('handoff_policy_skills')) {
            Schema::create('handoff_policy_skills', function (Blueprint $table) {
                $table->id();
                $table->foreignId('handoff_policy_id')->constrained()->cascadeOnDelete();
                $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['handoff_policy_id', 'skill_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('handoff_policy_skills');
        Schema::dropIfExists('handoff_policy_rules');
        Schema::dropIfExists('handoff_policies');
    }
};
