<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->decimal('value', 12, 2);
            $table->string('stage')->default('lead');
            $table->date('expected_close_date')->nullable();
            $table->text('description')->nullable();
            $table->text('lost_reason')->nullable();
            $table->timestamps();

            $table->index('customer_id');
            $table->index('stage');
            $table->index('expected_close_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
