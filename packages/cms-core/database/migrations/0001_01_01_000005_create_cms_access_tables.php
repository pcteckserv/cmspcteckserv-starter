<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('key')->unique();
            $table->boolean('is_protected')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('cms_permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('group')->index();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('cms_role_permission', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained('cms_roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('cms_permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('cms_role_user', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained('cms_roles')->cascadeOnDelete();
            $table->morphs('user');
            $table->primary(['role_id', 'user_type', 'user_id']);
        });

        Schema::create('cms_permission_user', function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained('cms_permissions')->cascadeOnDelete();
            $table->morphs('user');
            $table->primary(['permission_id', 'user_type', 'user_id']);
        });

        Schema::create('cms_user_states', function (Blueprint $table): void {
            $table->id();
            $table->morphs('user');
            $table->string('state')->default('active')->index();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->unique(['user_type', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_user_states');
        Schema::dropIfExists('cms_permission_user');
        Schema::dropIfExists('cms_role_user');
        Schema::dropIfExists('cms_role_permission');
        Schema::dropIfExists('cms_permissions');
        Schema::dropIfExists('cms_roles');
    }
};
