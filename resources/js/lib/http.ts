// Inertia の画面遷移以外で API を呼ぶときの共通処理

/** Laravel が発行する XSRF-TOKEN クッキーの値（CSRF 対策のヘッダーに載せる） */
export function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/** 同一オリジンの API を呼ぶときのヘッダー */
export function apiHeaders(): Record<string, string> {
    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': xsrfToken(),
    };
}

/** Laravel のバリデーションエラー（422）の JSON から、最初のメッセージを取り出す */
export async function firstErrorMessage(
    response: Response,
): Promise<string | null> {
    try {
        const body = (await response.json()) as {
            message?: string;
            errors?: Record<string, string[]>;
        };
        const first = Object.values(body.errors ?? {})[0]?.[0];

        return first ?? body.message ?? null;
    } catch {
        return null;
    }
}
