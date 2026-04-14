<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('yandex_market_products', function (Blueprint $table) {
            $table->json('campaign_ids')->nullable()->after('monitoring_enabled');
        });
    }

    public function down()
    {
        Schema::table('yandex_market_products', function (Blueprint $table) {
            $table->dropColumn('campaign_ids');
        });
    }
};
