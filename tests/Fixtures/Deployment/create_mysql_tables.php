<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_site_options', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->text('value')->nullable();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('email');
        });
        Schema::create('cms_plugins', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            foreach (['name', 'package', 'label', 'description', 'provider', 'installed_version', 'status'] as $column) {
                $table->string($column)->nullable();
            }
            $table->timestamp('installed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_plugins');
        Schema::dropIfExists('users');
        Schema::dropIfExists('cms_site_options');
    }
};
