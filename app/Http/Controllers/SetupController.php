<?php

namespace App\Http\Controllers;

use App\Http\Requests\SetupRequest;
use App\Setup\Installer;
use App\Support\PasswordRequirements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Web の初回設定。APP_KEY の生成と DB の準備は PrepareInstallation ミドルウェアが済ませている。
 */
class SetupController extends Controller
{
    public function __construct(private Installer $installer) {}

    public function show(): Response
    {
        abort_if($this->installer->isInstalled(), 404);

        return Inertia::render('setup/Index', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'passwordRequirements' => PasswordRequirements::describe(),
        ]);
    }

    public function store(SetupRequest $request): RedirectResponse
    {
        abort_if($this->installer->isInstalled(), 404);

        $admin = $this->installer->install($request->setupData());

        Auth::login($admin);
        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => '初期設定が完了しました。']);

        return redirect('/');
    }
}
