<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queues', function (Blueprint $table) {
            $table->json('sla_targets')->nullable()->after('priority_policy');
            $table->timestamp('sla_breached_at')->nullable()->after('sla_targets');
            $table->index('sla_breached_at');
        });
    }

    public function down(): void
    {
        Schema::table('queues', function (Blueprint $table) {
            $table->dropIndex(['sla_breached_at']);
            $table->dropColumn(['sla_targets', 'sla_breached_at']);
        });
    }
};
