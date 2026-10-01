import type { Directive } from 'vue';
import type { Auth } from '@/types/auth';
import type { FlashToast } from '@/types/ui';
import type { EditConflict } from '@/types/wiki';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            // テーマ色（app/Enums/ThemeColor の値）
            themeColor: string;
            auth: Auth;
            canRegister: boolean;
            // Metiwiki のバージョン（管理者のときだけ。それ以外は null）
            appVersion: string | null;
            [key: string]: unknown;
        };
        flashDataType: {
            toast?: FlashToast;
            // 編集の競合（保存しようとした版より新しい版があった）
            conflict?: EditConflict;
            // 改名前のタイトルから転送されたときの旧タイトル
            redirectedFrom?: string;
            // 発行した直後の招待リンク（トークンは保存しないため、このときだけ表示できる）
            invitationUrl?: string;
        };
    }
}

declare module 'vue' {
    interface GlobalDirectives {
        vFocus: Directive<HTMLElement, boolean | undefined>;
    }

    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
