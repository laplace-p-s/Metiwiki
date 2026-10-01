<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uploads', function (Blueprint $table) {
            $table->id();
            // 配信 URL（/-/uploads/{token}/{表示名}）に使う推測できない識別子。
            // 連番の id を URL に出すと、本文から外した画像なども総当たりで見られてしまうため
            $table->string('token', 32)->unique();
            // storage/app 配下の相対パス（ディスク上のファイル名はランダム）
            $table->string('path');
            $table->string('original_name');
            // 拡張子ではなく中身（finfo）で判定した MIME
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploads');
    }
};
