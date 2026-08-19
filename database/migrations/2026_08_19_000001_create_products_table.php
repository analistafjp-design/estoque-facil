<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('nome');
            $table->string('sku');
            $table->string('categoria')->nullable();
            $table->integer('quantidade_atual')->default(0);
            $table->integer('estoque_minimo')->default(0);
            $table->timestamps();
            // "Desativar" (CRUD da v1) é soft delete — nunca apaga de
            // verdade, senão o histórico de movimentações perde o produto
            // que ele referencia.
            $table->softDeletes();

            // SKU é único por empresa, não globalmente: duas empresas
            // diferentes podem cadastrar o mesmo código sem colidir.
            // Limitação conhecida (fora do escopo da v1): como o soft
            // delete não entra nesse índice, um SKU desativado continua
            // "ocupado" — reativar com o mesmo código exige reaproveitar o
            // registro desativado, não criar um novo.
            $table->unique(['company_id', 'sku']);
            $table->index(['company_id', 'categoria']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
