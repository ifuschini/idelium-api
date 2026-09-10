<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('costumers', 'apiKeyExpiresAt')) {
            return;
        }

        Schema::table('costumers', function (Blueprint $table): void {
            $table->timestamp('apiKeyExpiresAt')->nullable()->after('apiKeyLastUsedAt');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('costumers', 'apiKeyExpiresAt')) {
            return;
        }

        Schema::table('costumers', function (Blueprint $table): void {
            $table->dropColumn('apiKeyExpiresAt');
        });
    }
};
