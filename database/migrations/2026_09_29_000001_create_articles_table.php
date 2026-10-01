<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 照合キーは DB の照合順序に依存させない。MySQL の既定（utf8mb4_unicode_ci）は
        // カナの濁点や全角半角まで同一視するため、バイナリ照合にして完全一致で比較する
        $binary = in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)
            ? 'utf8mb4_bin'
            : null;

        Schema::create('articles', function (Blueprint $table) use ($binary) {
            $table->id();
            $table->string('title');
            // 正規化済みタイトルを mb_strtolower した照合キー（大文字小文字を区別しない判定用）
            $table->string('title_key')->collation($binary);
            $table->longText('body');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            // システムページ（ヘルプなど）の識別子。通常のページは NULL。
            // システムページは通常のページのタイトルの名前空間に属さない（App\Models\Article::SYSTEM_HELP）
            $table->string('system_key', 32)->nullable()->unique();
            $table->softDeletes();
            $table->timestamps();

            // 削除済みページと同名のページを新規作成できるよう、未削除時だけ照合キーを持つ
            // 生成カラムにユニーク制約をかける（削除時とシステムページは NULL になり制約の対象外）
            $table->string('active_title_key')
                ->collation($binary)
                ->nullable()
                ->storedAs('CASE WHEN deleted_at IS NULL AND system_key IS NULL THEN title_key ELSE NULL END');

            $table->unique('active_title_key');
            $table->index('title_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
