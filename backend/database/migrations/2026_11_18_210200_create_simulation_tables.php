<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulation_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('reference_year');
            $table->string('source', 20)->default('auto');      // auto|invoices|manual
            $table->string('status', 20)->default('pending');   // pending|running|completed|failed
            $table->json('client_ids');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        // One comparison = the two scenarios for one client and reference year.
        Schema::create('comparisons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('simulation_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('reference_year');
            $table->string('source', 20);                       // invoices|manual
            $table->string('status', 20)->default('pending');   // pending|running|completed|failed
            $table->json('input')->nullable();                  // what the user asked (manual values, filters)
            $table->json('assumptions')->nullable();            // resolved assumptions actually used
            $table->json('result')->nullable();                 // totals, difference, recommendation
            $table->string('recommendation', 30)->nullable();   // manter_simples|migrar_regular|indiferente
            $table->text('error_message')->nullable();
            $table->string('report_path')->nullable();
            $table->timestamp('report_generated_at')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->index(['office_id', 'created_at']);
            $table->index(['client_id', 'reference_year']);
        });

        Schema::create('simulations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('comparison_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('reference_year');
            $table->string('scenario', 30);                     // simples_integral|ibs_cbs_regular
            $table->string('status', 20)->default('pending');
            $table->json('input')->nullable();                  // normalized items/parameters
            // Audit trail, keyed by calculator: {"simples_nacional": {...}, "regime_geral": {...}}
            $table->json('request_payload')->nullable();
            $table->json('response_raw')->nullable();
            $table->json('calculator_version')->nullable();
            $table->json('totals')->nullable();
            $table->json('item_errors')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['comparison_id', 'scenario']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulations');
        Schema::dropIfExists('comparisons');
        Schema::dropIfExists('simulation_batches');
    }
};
