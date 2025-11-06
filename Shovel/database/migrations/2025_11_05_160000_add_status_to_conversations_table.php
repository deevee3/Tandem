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
        if (! Schema::hasColumn('conversations', 'status')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->string('status')->default('new')->after('subject');
                $table->index('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('conversations', 'status')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->dropIndex('conversations_status_index');
                $table->dropColumn('status');
            });
        }
    }
};
