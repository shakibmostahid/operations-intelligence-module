<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_tag', function (Blueprint $table) {
            $table->foreignId('incident_id')
                ->constrained('incidents')
                ->cascadeOnDelete();
            $table->foreignId('tag_id')
                ->constrained('tags')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['incident_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_tag');
    }
};
