<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costumers', function (Blueprint $table) {
            $table->timestamp('apiKeyCreatedAt')->nullable();
            $table->timestamp('apiKeyLastUsedAt')->nullable();
            $table->timestamp('apiKeyExpiresAt')->nullable();
        });

        DB::table('costumers')->update([
            'apiKeyCreatedAt' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('costumers', function (Blueprint $table) {
            $table->dropColumn(['apiKeyCreatedAt', 'apiKeyLastUsedAt', 'apiKeyExpiresAt']);
        });
    }
};
