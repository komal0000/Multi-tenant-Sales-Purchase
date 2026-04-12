<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items');
            $table->enum('type', ['in', 'out']);
            $table->decimal('qty', 15, 4);
            $table->decimal('rate', 15, 2);
            $table->string('identifier');
            $table->unsignedBigInteger('foreign_key');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['item_id', 'created_at']);
            $table->index(['identifier', 'foreign_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_ledgers');
    }
};
