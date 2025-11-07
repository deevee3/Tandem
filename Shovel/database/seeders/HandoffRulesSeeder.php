<?php

namespace Database\Seeders;

use App\Services\HandoffRulesService;
use Illuminate\Database\Seeder;

class HandoffRulesSeeder extends Seeder
{
    public function run(): void
    {
        $handoffRulesService = app(HandoffRulesService::class);
        $handoffRulesService->seedDefaultRules();
    }
}
