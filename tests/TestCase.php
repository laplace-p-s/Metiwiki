<?php

namespace Tests;

use App\Models\Setting;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 初期設定の前はすべての画面が /-/setup へ誘導されるため、DB を使うテストは初期設定済みにしておく。
        // 初期設定そのもののテストは markNotInstalled() で戻す
        if (in_array(RefreshDatabase::class, class_uses_recursive($this), true)) {
            Setting::create(['key' => SiteSettings::INSTALLED_AT, 'value' => now()->toIso8601String()]);
        }
    }

    protected function markNotInstalled(): void
    {
        Setting::query()->whereKey(SiteSettings::INSTALLED_AT)->delete();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
