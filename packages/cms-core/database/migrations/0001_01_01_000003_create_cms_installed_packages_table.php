<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_installed_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('installed_version')->nullable();
            $table->string('available_version')->nullable();
            $table->string('channel')->default('stable');
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_installed_packages');
    }
};
