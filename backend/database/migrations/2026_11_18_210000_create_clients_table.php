<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->string('cnpj', 14);
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->char('uf', 2);
            $table->unsignedInteger('municipality_ibge');
            $table->string('current_regime', 30)->default('simples_nacional');
            $table->string('simples_annex', 3)->nullable();          // I, II, III, IV, V
            $table->json('activities')->nullable();                   // cAtivSN codes, e.g. ["01"]
            $table->decimal('revenue_12m', 15, 2)->default(0);        // RBT12
            $table->decimal('purchases_12m', 15, 2)->default(0);      // used by manual simulations
            $table->decimal('b2b_sales_pct', 5, 2)->default(0);
            $table->string('default_ncm', 8)->nullable();
            $table->string('default_nbs', 9)->nullable();
            $table->string('default_cst', 3)->default('000');
            $table->string('default_c_class_trib', 6)->default('000001');
            $table->json('assumptions')->nullable();                  // per-client overrides
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['office_id', 'cnpj']);
            $table->index(['office_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
