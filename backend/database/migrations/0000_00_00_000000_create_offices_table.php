<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('cnpj', 14)->unique();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('primary_color', 7)->default('#0F4C81');
            $table->string('secondary_color', 7)->default('#F2A900');
            $table->string('plan', 30)->default('starter');
            $table->string('plan_status', 30)->default('trialing');
            $table->timestamp('trial_ends_at')->nullable();
            $table->string('billing_customer_id')->nullable();
            $table->string('billing_subscription_id')->nullable();
            // Office-level default assumptions (docs/premissas.md). Null keys fall back to config/premissas.php.
            $table->json('assumptions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offices');
    }
};
