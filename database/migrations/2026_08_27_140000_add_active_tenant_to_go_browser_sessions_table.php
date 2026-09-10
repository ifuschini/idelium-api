<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddActiveTenantToGoBrowserSessionsTable extends Migration
{
    public function up()
    {
        Schema::table('go_browser_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('activeTenantId')->nullable()->after('idCostumer');
            $table->string('impersonationReason', 255)->nullable()->after('csrfTokenHash');
            $table->timestamp('impersonationExpiresAt')->nullable()->after('impersonationReason');

            $table->foreign('activeTenantId')->references('id')->on('costumers')->nullOnDelete();
            $table->index(['activeTenantId', 'impersonationExpiresAt'], 'go_browser_session_active_tenant_idx');
        });
    }

    public function down()
    {
        Schema::table('go_browser_sessions', function (Blueprint $table) {
            $table->dropForeign(['activeTenantId']);
            $table->dropIndex('go_browser_session_active_tenant_idx');
            $table->dropColumn([
                'activeTenantId',
                'impersonationReason',
                'impersonationExpiresAt',
            ]);
        });
    }
}
