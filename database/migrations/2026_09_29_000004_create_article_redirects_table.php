<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 照合キーはバイナリ照合で比較する（articles テーブルのマイグレーションを参照）
        $binary = in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)
            ? 'utf8mb4_bin'
            : null;

        Schema::create('article_redirects', function (Blueprint $table) use ($binary) {
            $table->id();
            $table->string('old_title_key')->collation($binary);
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique('old_title_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_redirects');
    }
};
