<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `companies` é a raiz do multi-tenancy: toda tabela de dado de negócio
     * (products, stock_movements, invitations) referencia company_id, e o
     * CompanyScope global filtra automaticamente por ela — ver
     * ARCHITECTURE.md. Precisa existir antes de `users`, que já nasce com
     * a FK.
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('cnpj')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
