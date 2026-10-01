<?php

namespace App\Models;

use Database\Factories\UploadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $token
 * @property string $path
 * @property string $original_name
 * @property string $mime
 * @property int $size
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['path', 'original_name', 'mime', 'size', 'user_id'])]
class Upload extends Model
{
    /** @use HasFactory<UploadFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // 配信 URL の識別子は保存時に必ず作る（24 文字の 16 進数 = 96 ビット）
        static::creating(function (Upload $upload) {
            $upload->token ??= self::newToken();
        });
    }

    public static function newToken(): string
    {
        return bin2hex(random_bytes(12));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 配信 URL（サイト内の相対パス）。route() は % などをエンコードしないため、表示名は rawurlencode する
     */
    public function url(): string
    {
        return request()->getBaseUrl().'/-/uploads/'.$this->token.'/'.rawurlencode($this->original_name);
    }
}
