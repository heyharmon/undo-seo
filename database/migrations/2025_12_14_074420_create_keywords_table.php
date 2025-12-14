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
        Schema::dropIfExists('keywords');
        
        Schema::create('keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('keywords')->onDelete('cascade');
            $table->string('name');
            $table->integer('volume')->nullable();
            $table->enum('intent', ['info', 'commercial', 'transactional', 'navigational']);
            $table->enum('status', ['active', 'draft', 'planned']);
            $table->enum('keyword_type', ['product', 'service', 'benefit', 'price', 'competitor']);
            $table->enum('content_type', ['pillar_page', 'article', 'tutorial', 'comparison', 'landing_page']);
            $table->text('strategic_role')->nullable();
            $table->text('strategic_opportunity')->nullable();
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'parent_id', 'position']);
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
