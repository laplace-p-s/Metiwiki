<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ThemeColor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSiteSettingsRequest;
use App\Support\AuthFeatures;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SiteSettingsController extends Controller
{
    public function __construct(private SiteSettings $settings) {}

    public function edit(): Response
    {
        return Inertia::render('admin/Settings', [
            'siteName' => $this->settings->siteName(),
            'registrationEnabled' => $this->settings->registrationEnabled(),
            'themeColor' => $this->settings->themeColor()->value,
            'themeColors' => ThemeColor::options(),
            // メールによるパスワードリセットは .env のメール設定で決まる（画面からは変えられない）
            'passwordResetEnabled' => AuthFeatures::passwordResetEnabled(),
        ]);
    }

    public function update(UpdateSiteSettingsRequest $request): RedirectResponse
    {
        $this->settings->set(SiteSettings::SITE_NAME, $request->string('site_name')->trim()->toString());
        $this->settings->set(SiteSettings::REGISTRATION_ENABLED, $request->boolean('registration_enabled') ? '1' : '0');
        $this->settings->set(SiteSettings::THEME_COLOR, $request->enum('theme_color', ThemeColor::class)?->value);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'サイト設定を保存しました。']);

        return to_route('admin.settings.edit');
    }
}
