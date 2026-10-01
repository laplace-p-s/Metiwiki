<?php

namespace Tests\Feature\Wiki;

use App\Models\User;
use App\Services\ArticleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ArticlePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_returns_same_rendering_as_view()
    {
        $user = User::factory()->create();
        $service = app(ArticleService::class);
        $service->create(['title' => 'Laravel', 'body' => '本文'], $user);
        $body = "# 見出し\n\n[[Laravel]] と [[未作成]]";

        $this->actingAs($user)
            ->postJson(route('wiki.preview'), ['body' => $body])
            ->assertOk()
            ->assertExactJson($service->render($body));
    }

    public function test_empty_body_can_be_previewed()
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('wiki.preview'), ['body' => ''])
            ->assertOk()
            ->assertExactJson(['html' => '', 'toc' => []]);
    }

    public function test_body_size_is_limited()
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('wiki.preview'), ['body' => str_repeat('a', ArticleService::MAX_BODY_BYTES + 1)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
    }

    public function test_guests_cannot_preview()
    {
        $this->postJson(route('wiki.preview'), ['body' => '本文'])->assertUnauthorized();
    }

    public function test_preview_is_rate_limited()
    {
        $user = User::factory()->create();

        RateLimiter::increment(sha1((string) $user->id), amount: 60);

        $this->actingAs($user)
            ->postJson(route('wiki.preview'), ['body' => '本文'])
            ->assertTooManyRequests();
    }
}
