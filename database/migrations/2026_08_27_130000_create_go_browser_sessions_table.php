<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGoBrowserSessionsTable extends Migration
{
    public function up()
    {
        Schema::create('go_browser_sessions', function (Blueprint $table) {
            $table->char('idHash', 64)->primary();
            $table->unsignedBigInteger('userId');
            $table->unsignedBigInteger('idCostumer');
            $table->char('csrfTokenHash', 64);
            $table->timestamp('expiresAt')->index();
            $table->timestamps();

            $table->foreign('userId')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('idCostumer')->references('id')->on('costumers')->cascadeOnDelete();
            $table->index(['idCostumer', 'userId', 'expiresAt'], 'go_browser_session_scope_expiry_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('go_browser_sessions');
    }
}
