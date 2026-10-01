<?php

use App\Http\Controllers\Admin\DeletedArticleController;
use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\SiteSettingsController;
use App\Http\Controllers\Admin\UploadController as AdminUploadController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ArticlePreviewController;
use App\Http\Controllers\InvitationAcceptController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ArticleController::class, 'home'])->name('home');

// 初回設定（初期設定の前だけ使える。PrepareInstallation ミドルウェアがここへ誘導する）
Route::prefix('-')->group(function () {
    Route::get('setup', [SetupController::class, 'show'])->name('setup');
    Route::post('setup', [SetupController::class, 'store'])->middleware('throttle:10,1')->name('setup.store');
});

// 招待リンクからのアカウント登録
Route::prefix('-')->middleware('guest')->group(function () {
    Route::get('invite/{token}', [InvitationAcceptController::class, 'show'])->name('invitation.show');
    Route::post('invite/{token}', [InvitationAcceptController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('invitation.store');
});

// 管理画面（管理者のみ）
Route::prefix('-/admin')->name('admin.')->middleware(['auth', 'can:admin'])->group(function () {
    Route::redirect('/', '/-/admin/users');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::put('users/{user}/password', [UserController::class, 'resetPassword'])->name('users.password');
    Route::delete('users/{user}/two-factor', [UserController::class, 'disableTwoFactor'])->name('users.two-factor');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    Route::get('invitations', [InvitationController::class, 'index'])->name('invitations.index');
    Route::post('invitations', [InvitationController::class, 'store'])->name('invitations.store');
    Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');

    Route::get('deleted-pages', [DeletedArticleController::class, 'index'])->name('deleted-pages.index');
    Route::post('deleted-pages/{article}/restore', [DeletedArticleController::class, 'restore'])
        ->whereNumber('article')
        ->name('deleted-pages.restore');

    Route::get('uploads', [AdminUploadController::class, 'index'])->name('uploads.index');
    Route::delete('uploads/{upload}', [AdminUploadController::class, 'destroy'])->name('uploads.destroy');

    Route::get('settings', [SiteSettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('settings', [SiteSettingsController::class, 'update'])->name('settings.update');
});

require __DIR__.'/settings.php';

// システム画面（認証・個人設定・特殊ページなど）はすべて /-/ の下に置き、
// ページタイトル（/{title}）と衝突させない
Route::prefix('-')->name('wiki.')->group(function () {
    Route::get('recent-changes', [ArticleController::class, 'recentChanges'])->name('recent-changes');
    Route::get('all-pages', [ArticleController::class, 'allPages'])->name('all-pages');
    Route::get('search', [ArticleController::class, 'search'])->name('search');

    // ヘルプ（システムページ）。閲覧は誰でも、編集・履歴・版・差分は管理者だけ（ArticlePolicy）
    Route::get('help', [ArticleController::class, 'helpShow'])->name('help');
    Route::get('help/edit', [ArticleController::class, 'helpEdit'])->middleware('auth')->name('help.edit');
    Route::get('help/history', [ArticleController::class, 'helpHistory'])->name('help.history');
    Route::get('help/history/{version}', [ArticleController::class, 'helpVersion'])->whereNumber('version')->name('help.version');
    Route::get('help/diff', [ArticleController::class, 'helpDiff'])->name('help.diff');

    // アップロードした画像の配信（ゲストも閲覧可）。URL は Upload::url() で作る
    // 画像は推測できない token で指す（連番の id では総当たりで見られるため）
    Route::get('uploads/{upload:token}/{filename}', [UploadController::class, 'show'])
        ->where(['upload' => '[0-9a-f]{24}', 'filename' => '[^/]+'])
        ->name('uploads.show');

    Route::middleware(['auth'])->group(function () {
        // 新規ページ作成の入口。入力されたタイトルを正規化・検証して編集画面へ送る
        Route::get('new', [ArticleController::class, 'create'])->name('create');

        // 入力のたびに（デバウンスして）呼ばれるため、上限を緩めに取ったレート制限をかける
        Route::post('preview', ArticlePreviewController::class)->middleware('throttle:60,1')->name('preview');

        Route::post('uploads', [UploadController::class, 'store'])->middleware('throttle:30,1')->name('uploads.store');

        Route::post('pages', [ArticleController::class, 'store'])->name('store');
        Route::patch('pages/{article}', [ArticleController::class, 'update'])->name('update');
        Route::delete('pages/{article}', [ArticleController::class, 'destroy'])->name('destroy');
        Route::post('pages/{article}/versions/{version}/restore', [ArticleController::class, 'restoreVersion'])
            ->whereNumber('version')
            ->name('restore-version');
    });
});

// ページ（タイトル＝URL）。キャッチオールなので必ず最後に登録する。
// タイトル「-」は /-/ と衝突するため、どのルートでも受け付けない
Route::name('wiki.')->where(['title' => '(?!-(?:/|$))[^/]+'])->group(function () {
    Route::get('{title}/-/edit', [ArticleController::class, 'edit'])->middleware('auth')->name('edit');
    Route::get('{title}/-/history', [ArticleController::class, 'history'])->name('history');
    Route::get('{title}/-/history/{version}', [ArticleController::class, 'version'])->whereNumber('version')->name('version');
    Route::get('{title}/-/diff', [ArticleController::class, 'diff'])->name('diff');
    Route::get('{title}', [ArticleController::class, 'show'])->name('show');
});
