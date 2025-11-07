<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('metric');
            $table->date('occurred_on');
            $table->foreignId('queue_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('reason_code')->nullable();
            $table->string('priority')->nullable();
            $table->decimal('value', 18, 4)->default(0);
            $table->unsignedBigInteger('numerator')->nullable();
            $table->unsignedBigInteger('denominator')->nullable();
            $table->unsignedBigInteger('sample_size')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['metric', 'occurred_on']);
            $table->index(['metric', 'queue_id']);
            $table->index(['metric', 'priority']);
            $table->index(['metric', 'reason_code']);
            $table->unique(['metric', 'occurred_on', 'queue_id', 'reason_code', 'priority'], 'analytics_metric_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_daily_metrics');
    }
};
