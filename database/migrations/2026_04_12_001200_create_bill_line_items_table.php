<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_line_items', function (Blueprint $table) {
            $table->id();
            $table->enum('bill_type', ['sale', 'purchase']);
            $table->unsignedBigInteger('bill_id');
            $table->enum('line_type', ['item', 'general', 'expense']);
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->text('description')->nullable();
            $table->foreignId('expense_category_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->decimal('qty', 15, 4)->nullable();
            $table->decimal('rate', 15, 2);
            $table->decimal('total', 15, 2);

            $table->index(['bill_type', 'bill_id']);
            $table->index(['line_type', 'item_id']);
            $table->index(['line_type', 'expense_category_id']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE bill_line_items ADD CONSTRAINT chk_bill_line_items_expense_purchase CHECK (line_type <> 'expense' OR bill_type = 'purchase')");
            DB::statement("ALTER TABLE bill_line_items ADD CONSTRAINT chk_bill_line_items_item_requires_item CHECK (line_type <> 'item' OR item_id IS NOT NULL)");
            DB::statement("ALTER TABLE bill_line_items ADD CONSTRAINT chk_bill_line_items_expense_requires_category CHECK (line_type <> 'expense' OR expense_category_id IS NOT NULL)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_line_items');
    }
};
