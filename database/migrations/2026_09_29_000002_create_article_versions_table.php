<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('body');
            $table->string('summary')->nullable();
            $table->unsignedInteger('version_number');
            // 版の種別（App\Enums\VersionKind。編集・削除・復元）
            $table->string('kind', 16)->default('edit');
            $table->timestamps();

            $table->unique(['article_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_versions');
    }
};
