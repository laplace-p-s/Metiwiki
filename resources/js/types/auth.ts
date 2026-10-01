// 共有 props に載るログイン中ユーザー（HandleInertiaRequests で項目を絞っている）
export type User = {
    id: number;
    name: string;
    login_id: string;
    email: string | null;
    is_admin: boolean;
};

// 管理画面で扱うユーザー
export type AdminUser = {
    id: number;
    name: string;
    login_id: string;
    email: string | null;
    is_admin: boolean;
    two_factor_enabled: boolean;
};

export type Auth = {
    user: User | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
