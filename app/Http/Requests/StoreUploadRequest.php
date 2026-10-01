<?php

namespace App\Http\Requests;

use App\Services\UploadService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUploadRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * 形式の判定（中身による）と画像として読めるかの確認は UploadService でも行う。
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.intdiv(UploadService::maxBytes(), 1024),
                'mimetypes:'.implode(',', array_keys(UploadService::MIME_EXTENSIONS)),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $limit = number_format(UploadService::maxBytes() / 1024 / 1024, 1);

        return [
            'file.max' => "画像は {$limit}MB 以下にしてください。",
            'file.mimetypes' => 'JPEG・PNG・GIF・WebP の画像だけアップロードできます。',
            'file.uploaded' => "画像をアップロードできませんでした。{$limit}MB 以下か確認してください。",
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['file' => '画像'];
    }
}
