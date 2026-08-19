<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // restrict, não cascade: um produto com histórico de
            // movimentação não pode ser apagado de verdade (é por isso que
            // "excluir" produto é soft delete, nunca delete físico).
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            // Quem lançou. set null (não cascade): se o usuário for
            // removido no futuro, o histórico do produto continua de pé.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tipo'); // entrada | saida — ver App\Enums\MovementType
            $table->unsignedInteger('quantidade');
            $table->text('observacao')->nullable();
            // "data" do schema pedido no prompt é created_at: já é o
            // timestamp de quando o lançamento aconteceu, não precisa de
            // coluna duplicada.
            $table->timestamps();

            $table->index(['company_id', 'product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
