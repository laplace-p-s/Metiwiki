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

        Schema::create('article_links', function (Blueprint $table) use ($binary) {
            $table->id();
            $table->foreignId('from_article_id')->constrained('articles')->cascadeOnDelete();
            // リンク先は未作成ページもありうるため、ID ではなく照合キーで持つ
            $table->string('to_title_key')->collation($binary);
            $table->timestamps();

            $table->index('to_title_key');
            $table->index('from_article_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_links');
    }
};
