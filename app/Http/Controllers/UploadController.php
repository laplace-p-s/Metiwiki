<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUploadRequest;
use App\Models\Upload;
use App\Services\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;

class UploadController extends Controller
{
    /**
     * 編集画面からの画像アップロード。本文に挿入する Markdown を返す
     */
    public function store(StoreUploadRequest $request, UploadService $uploads): JsonResponse
    {
        $file = $request->file('file');

        // バリデーション（required・file）を通っているので、単一のファイルが来ている
        abort_unless($file instanceof UploadedFile, 422);

        $upload = $uploads->store($file, $request->user());

        // 代替テキストは表示名から拡張子を除いたもの
        $alt = pathinfo($upload->original_name, PATHINFO_FILENAME);

        return response()->json([
            'id' => $upload->id,
            'url' => $upload->url(),
            'markdown' => "![{$alt}]({$upload->url()})",
        ], 201);
    }

    /**
     * 画像の配信。ゲストも閲覧できる。内容は変わらないため長期キャッシュさせる
     */
    public function show(Upload $upload, string $filename): BinaryFileResponse|RedirectResponse
    {
        // 表示名が違う URL は正式な URL へ
        if ($filename !== $upload->original_name) {
            return redirect($upload->url(), 301);
        }

        $disk = Storage::disk(UploadService::DISK);

        abort_unless($disk->exists($upload->path), 404);

        return response()->file($disk->path($upload->path), [
            'Content-Type' => $upload->mime,
            'Content-Disposition' => HeaderUtils::makeDisposition('inline', $upload->original_name, 'image'),
            'X-Content-Type-Options' => 'nosniff',
            // 画像を直接開いたときにスクリプトなどが動かないようにする
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
