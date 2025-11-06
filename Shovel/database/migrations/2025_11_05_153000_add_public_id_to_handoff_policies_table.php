<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('handoff_policies', function (Blueprint $table) {
            $table->char('public_id', 26)->nullable()->after('id');
        });

        DB::table('handoff_policies')
            ->select('id')
            ->lazyById()
            ->each(function ($policy) {
                DB::table('handoff_policies')
                    ->where('id', $policy->id)
                    ->update(['public_id' => (string) Str::ulid()]);
            });
    }

    public function down(): void
    {
        Schema::table('handoff_policies', function (Blueprint $table) {
            $table->dropColumn('public_id');
        });
    }
};
