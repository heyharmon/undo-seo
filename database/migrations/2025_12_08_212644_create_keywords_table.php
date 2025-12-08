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
        Schema::create('keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('keywords')->cascadeOnDelete();
            $table->string('keyword');
            $table->unsignedInteger('search_volume')->nullable();
            $table->unsignedTinyInteger('difficulty')->nullable();
            $table->boolean('is_seed')->default(false);
            $table->timestamps();

            $table->index(['project_id', 'parent_id']);
            $table->index(['project_id', 'is_seed']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('keywords');
    }
};
