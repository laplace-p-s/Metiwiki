import { createInertiaApp, router } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AdminLayout from '@/layouts/admin/Layout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import WikiLayout from '@/layouts/WikiLayout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

// Wiki 名はサイト設定で変わるため、ビルド時の値ではなくサーバーが渡す値を使う。
// 最初の表示では meta タグから読み、画面遷移のたびに共有 props で更新する
let siteName =
    document
        .querySelector('meta[name="application-name"]')
        ?.getAttribute('content') || 'Metiwiki';

router.on('navigate', (event) => {
    const { name, themeColor } = event.detail.page.props;

    if (typeof name === 'string' && name !== '') {
        siteName = name;
    }

    // テーマ色もサイト設定で変わる。最初の表示では app.blade.php が data-theme を付けている
    if (typeof themeColor === 'string' && themeColor !== '') {
        document.documentElement.dataset.theme = themeColor;
    }
});

void createInertiaApp({
    title: (title) => (title ? `${title} - ${siteName}` : siteName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
            case name.startsWith('setup/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [WikiLayout, SettingsLayout];
            case name.startsWith('admin/'):
                return [WikiLayout, AdminLayout];
            default:
                return WikiLayout;
        }
    },
    withApp: (app) => {
        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        // 進捗バーもテーマ色にする（CSS の background にそのまま入る）
        color: 'var(--primary)',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
