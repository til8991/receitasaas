<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('pending');   // pending|processing|completed|failed
            $table->json('files');                              // [{name, path}]
            $table->unsignedInteger('total_files')->default(0);
            $table->unsignedInteger('processed_files')->default(0);
            $table->unsignedInteger('failed_files')->default(0);
            $table->unsignedInteger('duplicated_documents')->default(0);
            $table->unsignedInteger('imported_items')->default(0);
            $table->json('errors')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_import_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 10);                 // nfe|nfce|nfse
            $table->string('document_key', 64);                  // access key, or hash for NFS-e without key
            $table->string('document_number', 20)->nullable();
            $table->dateTime('issued_at');
            $table->string('direction', 10);                     // saida (sale by the client) | entrada (purchase)
            $table->string('issuer_document', 14)->nullable();
            $table->string('recipient_document', 14)->nullable();
            $table->string('recipient_type', 10)->default('unknown'); // pj|pf|exterior|unknown
            $table->char('recipient_uf', 2)->nullable();
            $table->unsignedInteger('item_number');
            $table->string('description')->nullable();
            $table->string('ncm', 8)->nullable();
            $table->string('nbs', 9)->nullable();
            $table->string('cfop', 4)->nullable();
            $table->string('cst', 3)->nullable();
            $table->string('c_class_trib', 6)->nullable();
            $table->decimal('quantity', 15, 4)->default(0);
            $table->string('unit', 10)->nullable();
            $table->decimal('product_amount', 15, 2)->default(0);   // vProd / vServ
            $table->decimal('freight', 15, 2)->default(0);
            $table->decimal('insurance', 15, 2)->default(0);
            $table->decimal('other', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('base_amount', 15, 2)->default(0);      // vProd + vFrete + vSeg + vOutro - vDesc
            $table->timestamps();

            $table->unique(['client_id', 'document_key', 'item_number']);
            $table->index(['client_id', 'direction', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoice_imports');
    }
};
