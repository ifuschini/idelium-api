<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['performed_test_cycles', 'performed_tests', 'performed_steps'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->string('idempotencyKey', 128)->nullable()->after('idCostumer');
                $table->unique(['idCostumer', 'idempotencyKey'], $tableName.'_tenant_idempotency_unique');
            });
        }
    }

    public function down(): void
    {
        foreach (['performed_test_cycles', 'performed_tests', 'performed_steps'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropUnique($tableName.'_tenant_idempotency_unique');
                $table->dropColumn('idempotencyKey');
            });
        }
    }
};
