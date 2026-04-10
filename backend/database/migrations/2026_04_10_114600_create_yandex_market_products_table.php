<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yandex_market_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('offer_id')->unique();
            $table->string('sku')->nullable();
            $table->string('category')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('monitoring_enabled')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yandex_market_products');
    }
};
