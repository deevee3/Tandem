<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->timestamp('first_response_due_at')->nullable()->after('last_activity_at');
            $table->timestamp('resolution_due_at')->nullable()->after('first_response_due_at');
            $table->timestamp('first_responded_at')->nullable()->after('resolution_due_at');
            $table->timestamp('sla_breached_at')->nullable()->after('first_responded_at');
            $table->json('sla_status')->nullable()->after('sla_breached_at');
            
            $table->index(['first_response_due_at', 'resolution_due_at']);
            $table->index('sla_breached_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['first_response_due_at', 'resolution_due_at']);
            $table->dropIndex('sla_breached_at');
            $table->dropColumn(['first_response_due_at', 'resolution_due_at', 'first_responded_at', 'sla_breached_at', 'sla_status']);
        });
    }
};
