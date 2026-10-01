<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Upload;
use App\Models\User;
use App\Support\JpegMetadataStripper;
use finfo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * 画像のアップロード。
 *
 * - 形式は JPEG・PNG・GIF・WebP のみ（SVG はスクリプトを含められるため不可）。拡張子ではなく中身で判定する
 * - JPEG は撮影位置などのメタデータを取り除いてから保存する（向きは残す）
 * - storage/app/private にランダムなファイル名で保存し、UploadController から配信する
 */
class UploadService
{
    public const DISK = 'local';

    /** 受け付ける MIME と保存・表示に使う拡張子 */
    public const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    /**
     * 実際にアップロードできる 1 枚の上限（バイト）。設定値（WIKI_UPLOAD_MAX_KB）と
     * PHP の upload_max_filesize・post_max_size のうち最も小さいもの
     */
    public static function maxBytes(): int
    {
        $configured = max(1, (int) config('wiki.upload_max_kb')) * 1024;
        $php = (int) UploadedFile::getMaxFilesize();

        return $php > 0 ? min($configured, $php) : $configured;
    }

    /**
     * @throws ValidationException 画像として受け付けられないとき
     */
    public function store(UploadedFile $file, User $user): Upload
    {
        $path = $file->getRealPath();

        if ($path === false) {
            throw $this->invalid('画像を読み取れませんでした。');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);

        if (! is_string($mime) || ! isset(self::MIME_EXTENSIONS[$mime])) {
            throw $this->invalid('JPEG・PNG・GIF・WebP の画像だけアップロードできます。');
        }

        // 画像として読めるか（ヘッダーだけ偽装したファイルを弾く）
        $info = @getimagesize($path);

        if ($info === false || $info['mime'] !== $mime) {
            throw $this->invalid('画像を読み取れませんでした。壊れているか、対応していない形式です。');
        }

        $bytes = (string) file_get_contents($path);

        if ($mime === 'image/jpeg') {
            try {
                $bytes = JpegMetadataStripper::strip($bytes);
            } catch (RuntimeException) {
                // 位置情報などを消せたか保証できないため、受け付けない
                throw $this->invalid('JPEG の撮影情報を取り除けなかったため、アップロードできませんでした。');
            }
        }

        $extension = self::MIME_EXTENSIONS[$mime];
        $storedPath = 'uploads/'.now()->format('Y/m').'/'.Str::random(40).'.'.$extension;

        Storage::disk(self::DISK)->put($storedPath, $bytes);

        return Upload::create([
            'path' => $storedPath,
            'original_name' => self::displayName($file->getClientOriginalName(), $extension),
            'mime' => $mime,
            'size' => strlen($bytes),
            'user_id' => $user->id,
        ]);
    }

    /**
     * この画像を最新版の本文で使っているページ（削除済みのページは除く）。
     * 古い版だけで使っている場合は数えない（決定 17。削除すると古い版の画像は欠ける）
     *
     * @return Collection<int, Article>
     */
    public function pagesUsing(Upload $upload): Collection
    {
        // 表示名やサブディレクトリの違いによらず、/-/uploads/{token}/ を含むかで判定する
        $needle = '%'.addcslashes("/-/uploads/{$upload->token}/", '\\%_').'%';

        // ヘルプ（システムページ）での使用も数える
        $query = Article::withoutGlobalScope(Article::CONTENT_SCOPE)->orderBy('title_key');

        if (in_array($query->getModel()->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $query->whereRaw('body LIKE ?', [$needle]);
        } else {
            $query->whereRaw("body LIKE ? ESCAPE '\\'", [$needle]);
        }

        return $query->get(['id', 'title', 'system_key']);
    }

    /** 画像のファイルと記録を削除する。本文に残った記法はそのまま（表示されなくなる） */
    public function delete(Upload $upload): void
    {
        Storage::disk(self::DISK)->delete($upload->path);
        $upload->delete();
    }

    /**
     * URL と Markdown に使う表示名。元のファイル名から URL・Markdown で問題になる文字を除き、
     * 拡張子を中身に合わせて付け直す
     */
    public static function displayName(string $original, string $extension): string
    {
        $stem = pathinfo(str_replace('\\', '/', $original), PATHINFO_FILENAME);
        $stem = preg_replace('/[\p{C}\/?#%\[\]()<>"|*:\\\\]+/u', '', $stem) ?? '';
        $stem = trim(preg_replace('/\s+/u', ' ', $stem) ?? '', ' .');
        $stem = mb_substr($stem, 0, 80);

        return ($stem !== '' ? $stem : 'image').'.'.$extension;
    }

    private function invalid(string $message): ValidationException
    {
        return ValidationException::withMessages(['file' => $message]);
    }
}
