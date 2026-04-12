<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases');
            $table->string('particular');
            $table->decimal('qty', 15, 2);
            $table->decimal('price', 15, 2);
            $table->decimal('total', 15, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
