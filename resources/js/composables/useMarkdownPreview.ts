import type { Ref } from 'vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import { apiHeaders } from '@/lib/http';
import { preview } from '@/routes/wiki';
import type { RenderedMarkdown, TocItem } from '@/types';

const DEBOUNCE_MS = 500;

/**
 * 編集プレビュー。閲覧ページと同じサーバー描画（POST /-/preview）の結果を表示する。
 * enabled の間だけ、本文の変更をデバウンスして送る。古い要求は中断し、遅れて届いた結果で上書きしない。
 */
export function useMarkdownPreview(body: Ref<string>, enabled: Ref<boolean>) {
    const html = ref('');
    const toc = ref<TocItem[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);

    let timer: ReturnType<typeof setTimeout> | undefined;
    let controller: AbortController | null = null;

    async function render() {
        controller?.abort();
        controller = new AbortController();
        loading.value = true;

        try {
            const response = await fetch(preview.url(), {
                method: 'POST',
                headers: {
                    ...apiHeaders(),
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ body: body.value }),
                credentials: 'same-origin',
                signal: controller.signal,
            });

            if (!response.ok) {
                error.value =
                    response.status === 429
                        ? 'プレビューの更新が多すぎます。少し待ってから再度お試しください。'
                        : response.status === 422
                          ? '本文が長すぎるためプレビューできません。'
                          : 'プレビューを表示できませんでした。';

                return;
            }

            const result = (await response.json()) as RenderedMarkdown;
            html.value = result.html;
            toc.value = result.toc;
            error.value = null;
        } catch (e) {
            if (!(e instanceof DOMException && e.name === 'AbortError')) {
                error.value = 'プレビューを表示できませんでした。';
            }
        } finally {
            loading.value = false;
        }
    }

    function schedule() {
        clearTimeout(timer);
        timer = setTimeout(render, DEBOUNCE_MS);
    }

    // 有効にした瞬間はすぐ描画し、その後の入力はデバウンスする
    watch(enabled, (on) => {
        if (on) {
            void render();
        } else {
            clearTimeout(timer);
            controller?.abort();
        }
    });

    watch(body, () => {
        if (enabled.value) {
            schedule();
        }
    });

    onBeforeUnmount(() => {
        clearTimeout(timer);
        controller?.abort();
    });

    return { html, toc, loading, error };
}
