<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Seed disposable, tenant-scoped records for Laravel/Go differential capture. */
class ParallelRunCaptureSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('costumers')->updateOrInsert(
            ['id' => 9001],
            ['costumer' => 'fixture-customer-smoke', 'description' => 'Disposable capture tenant', 'apiKey' => 'fixture-cli-key-9001', 'apiKeyExpiresAt' => $now->copy()->addDay(), 'updated_at' => $now, 'created_at' => $now]
        );
        DB::table('users')->updateOrInsert(
            ['id' => 9001],
            ['name' => 'Smoke Browser User', 'email' => 'fixture-smoke@example.invalid', 'password' => Hash::make('SmokePassword123!'), 'role' => 2, 'idCostumer' => 9001, 'status' => 'active', 'updated_at' => $now, 'created_at' => $now]
        );
        DB::table('projects')->updateOrInsert(['id' => 9001], ['name' => 'fixture-project-smoke', 'description' => 'Disposable capture project', 'idCostumer' => 9001, 'updated_at' => $now, 'created_at' => $now]);
        DB::table('test_cycles')->updateOrInsert(['id' => 9001], ['name' => 'fixture-cycle-smoke', 'description' => 'Disposable capture cycle', 'config' => json_encode(['tests' => [9001]]), 'idProject' => 9001, 'idCostumer' => 9001, 'updated_at' => $now, 'created_at' => $now]);
        DB::table('parallel_run_schedules')->updateOrInsert(
            ['id' => 9001],
            ['idProject' => 9001, 'testCycleId' => 9001, 'idCostumer' => 9001, 'idempotencyKey' => 'fixture-parallel-run-smoke', 'status' => 'queued', 'requestedConcurrency' => 1, 'workerStates' => json_encode(['worker-smoke' => ['status' => 'queued']]), 'resultSummary' => json_encode([]), 'metadata' => json_encode(['fixture' => 'fixture-parallel-run-smoke']), 'scheduledAt' => $now, 'updated_at' => $now, 'created_at' => $now]
        );
        DB::table('agent_registrations')->updateOrInsert(
            ['idCostumer' => 9001, 'agentId' => 'fixture-agent-smoke'],
            ['status' => 'active', 'version' => 'fixture', 'capabilities' => json_encode(['selenium']), 'maxConcurrency' => 1, 'health' => 'healthy', 'updated_at' => $now, 'created_at' => $now]
        );
    }
}
