<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('score')->nullable();
            $table->json('rubric')->nullable();
            $table->text('comments')->nullable();
            $table->foreignId('created_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('key', 64)->unique();
            $table->json('scopes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'active'], 'user_active_api_key_index');
        });

        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->json('events');
            $table->string('secret');
            $table->boolean('active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('active');
        });

        Schema::create('guardrail_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('scope');
            $table->json('config');
            $table->unsignedInteger('version');
            $table->timestamps();
            $table->unique(['scope', 'version']);
        });

        Schema::create('guardrail_actions', function (Blueprint $table) {
            $table->id();
            $table->ulid('action_id')->unique();
            $table->string('guardrail');
            $table->json('previous_config')->nullable();
            $table->json('applied_config');
            $table->json('metrics_snapshot');
            $table->string('initiator')->default('orchestrator');
            $table->timestamp('applied_at');
            $table->timestamps();

            $table->index(['guardrail', 'applied_at']);
        });

        Schema::create('guardrail_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('guardrail');
            $table->json('override_config');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['guardrail', 'starts_at']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('guardrail_overrides');
        Schema::dropIfExists('guardrail_actions');
        Schema::dropIfExists('guardrail_policies');
        Schema::dropIfExists('webhooks');
        Schema::dropIfExists('api_keys');
        Schema::dropIfExists('evaluations');
    }
};
