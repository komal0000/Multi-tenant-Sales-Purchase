<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained('parties');
            $table->decimal('amount', 15, 2);
            $table->enum('type', ['received', 'given']);
            $table->foreignId('account_id')->constrained('accounts');
            $table->foreignId('sale_id')->nullable()->constrained('sales');
            $table->foreignId('purchase_id')->nullable()->constrained('purchases');
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
