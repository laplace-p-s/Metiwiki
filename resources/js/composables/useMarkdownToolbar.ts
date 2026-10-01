import type { Icon } from '@tabler/icons-vue';
import {
    IconBold,
    IconCode,
    IconHeading,
    IconItalic,
    IconLink,
    IconList,
    IconListCheck,
    IconListNumbers,
    IconQuote,
    IconStrikethrough,
    IconTable,
} from '@tabler/icons-vue';
import type { Ref } from 'vue';
import { nextTick } from 'vue';

export type ToolbarItem = {
    icon: Icon;
    title: string;
    action: () => void;
    // この項目の前に区切り線を引く
    divider?: boolean;
};

// Markdown エディタのツールバー操作ロジック。
// textareaRef: textarea 要素への ref / getValue・setValue: 本文の取得・更新
export function useMarkdownToolbar(
    textareaRef: Ref<HTMLTextAreaElement | null>,
    getValue: () => string,
    setValue: (value: string) => void,
) {
    function focusAndSelect(
        el: HTMLTextAreaElement,
        start: number,
        end: number,
    ) {
        void nextTick(() => {
            el.focus();
            el.setSelectionRange(start, end);
        });
    }

    // カーソル選択範囲を before/after で囲む。未選択時は placeholder を挿入して選択状態にする
    function wrapSelection(before: string, after = before, placeholder = '') {
        const el = textareaRef.value;

        if (!el) {
            return;
        }

        const start = el.selectionStart;
        const end = el.selectionEnd;
        const value = getValue();
        const selected = value.slice(start, end) || placeholder;

        setValue(
            value.slice(0, start) +
                before +
                selected +
                after +
                value.slice(end),
        );

        const selStart = start + before.length;
        focusAndSelect(el, selStart, selStart + selected.length);
    }

    // 選択中の各行の先頭に prefix を付与（リスト・引用・見出し用）。ordered=true なら連番
    function prefixLines(prefix: string, ordered = false) {
        const el = textareaRef.value;

        if (!el) {
            return;
        }

        const value = getValue();
        const start = el.selectionStart;
        const end = el.selectionEnd;
        const lineStart = value.lastIndexOf('\n', start - 1) + 1;
        const transformed = value
            .slice(lineStart, end)
            .split('\n')
            .map((line, i) => (ordered ? `${i + 1}. ` : prefix) + line)
            .join('\n');

        setValue(value.slice(0, lineStart) + transformed + value.slice(end));
        focusAndSelect(el, lineStart, lineStart + transformed.length);
    }

    // ── リスト行のインデント（Tab / Shift+Tab）────────────────────────
    // 「- 」「* 」「+ 」「1. 」「1) 」「- [ ] 」で始まる行をリスト行とみなす
    const LIST_LINE_RE = /^[ \t]*(?:[-*+]|\d+[.)])(?:[ \t]+\[[ xX]\])?[ \t]+/;
    const INDENT = '  ';

    // 指定範囲を text で置換する。undo 履歴を保つため execCommand を優先し、
    // 使えない環境では値の差し替えにフォールバックする
    function replaceRange(
        el: HTMLTextAreaElement,
        start: number,
        end: number,
        text: string,
    ) {
        el.focus();
        el.setSelectionRange(start, end);
        let inserted = false;

        try {
            // 空文字の insertText は環境によって無視されるため、削除は delete コマンドで行う
            inserted =
                text === '' && start !== end
                    ? document.execCommand('delete')
                    : document.execCommand('insertText', false, text);
        } catch {
            inserted = false;
        }

        if (inserted) {
            setValue(el.value);
        } else {
            const value = getValue();
            setValue(value.slice(0, start) + text + value.slice(end));
        }
    }

    // カーソル行（または選択範囲の各行）のリスト行をインデント／アンインデントする。
    // 対象となるリスト行が無ければ何もせず false を返し、Tab 本来のフォーカス移動に委ねる
    function indentListLines(outdent = false): boolean {
        const el = textareaRef.value;

        if (!el) {
            return false;
        }

        const value = getValue();
        const selStart = el.selectionStart;
        const selEnd = el.selectionEnd;
        const blockStart = value.lastIndexOf('\n', selStart - 1) + 1;
        const nextBreak = value.indexOf('\n', selEnd);
        const blockEnd = nextBreak === -1 ? value.length : nextBreak;

        const lines = value.slice(blockStart, blockEnd).split('\n');

        if (!lines.some((line) => LIST_LINE_RE.test(line))) {
            return false;
        }

        let firstDelta = 0;
        let totalDelta = 0;
        const transformed = lines
            .map((line, i) => {
                if (!LIST_LINE_RE.test(line)) {
                    return line;
                }

                let next = line;

                if (outdent) {
                    const m = /^(?: {1,2}|\t)/.exec(line);

                    if (m) {
                        next = line.slice(m[0].length);
                    }
                } else {
                    next = INDENT + line;
                }

                const delta = next.length - line.length;

                if (i === 0) {
                    firstDelta = delta;
                }

                totalDelta += delta;

                return next;
            })
            .join('\n');

        // 既に最も浅い（変化なし）だが、フォーカスは移動させない
        if (totalDelta === 0) {
            return true;
        }

        replaceRange(el, blockStart, blockEnd, transformed);
        const nextStart = Math.max(blockStart, selStart + firstDelta);
        const nextEnd = Math.max(nextStart, selEnd + totalDelta);
        focusAndSelect(el, nextStart, nextEnd);

        return true;
    }

    // ── Enter でのリスト継続 ──────────────────────────────────────
    // 1:インデント 2:マーカー 3:番号 4:番号の区切り(.|)) 5:チェックボックスの状態
    const LIST_ITEM_RE =
        /^([ \t]*)((?:[-*+])|(\d+)([.)]))(?:[ \t]+\[([ xX])\])?[ \t]+/;

    // リスト行で改行したとき、次の行に同じインデント・同じマーカー（番号なら次の番号）を出す。
    // リスト行でなければ false を返し、通常の改行に委ねる
    function continueList(): boolean {
        const el = textareaRef.value;

        if (!el) {
            return false;
        }

        const value = getValue();
        const start = el.selectionStart;
        const end = el.selectionEnd;
        const lineStart = value.lastIndexOf('\n', start - 1) + 1;
        const nextBreak = value.indexOf('\n', start);
        const lineEnd = nextBreak === -1 ? value.length : nextBreak;

        const line = value.slice(lineStart, lineEnd);
        const m = LIST_ITEM_RE.exec(line);

        // カーソルがマーカーより前にあるときは何もしない（通常の改行）
        if (!m || start < lineStart + m[0].length) {
            return false;
        }

        // 中身が空のまま改行したらリストを終わらせる（マーカーを消して空行にする）
        if (start === end && line.slice(m[0].length).trim() === '') {
            replaceRange(el, lineStart, lineEnd, '');
            focusAndSelect(el, lineStart, lineStart);

            return true;
        }

        const marker = m[3] ? `${Number(m[3]) + 1}${m[4]} ` : `${m[2]} `;
        const checkbox = m[5] !== undefined ? '[ ] ' : '';
        const insert = `\n${m[1]}${marker}${checkbox}`;

        replaceRange(el, start, end, insert);
        const caret = start + insert.length;
        focusAndSelect(el, caret, caret);

        return true;
    }

    function insertLink() {
        const el = textareaRef.value;

        if (!el) {
            return;
        }

        const selected = getValue().slice(el.selectionStart, el.selectionEnd);
        wrapSelection('[', '](url)', selected ? '' : 'リンクテキスト');
    }

    // [[ページ名]] 形式の WikiLink を挿入
    function insertWikiLink() {
        wrapSelection('[[', ']]', 'ページ名');
    }

    // 3列 × 2行のテーブル雛形を挿入する。テーブルは前後に空行がないと表として解釈されないため、
    // カーソル位置の前後を見て足りない改行だけを補う
    const TABLE_HEAD = '見出し1';
    const TABLE_TEMPLATE = [
        `| ${TABLE_HEAD} | 見出し2 | 見出し3 |`,
        '| --- | --- | --- |',
        '|  |  |  |',
        '|  |  |  |',
    ].join('\n');

    function insertTable() {
        const el = textareaRef.value;

        if (!el) {
            return;
        }

        const value = getValue();
        const before = value.slice(0, el.selectionStart);
        const after = value.slice(el.selectionEnd);

        const lead =
            before === '' || before.endsWith('\n\n')
                ? ''
                : before.endsWith('\n')
                  ? '\n'
                  : '\n\n';
        const trail = after.startsWith('\n\n')
            ? ''
            : after.startsWith('\n')
              ? '\n'
              : '\n\n';
        setValue(before + lead + TABLE_TEMPLATE + trail + after);

        // 先頭セルの見出しを選択状態にして、そのまま打ち替えられるようにする
        const headStart = before.length + lead.length + 2;
        focusAndSelect(el, headStart, headStart + TABLE_HEAD.length);
    }

    const baseItems: ToolbarItem[] = [
        {
            icon: IconBold,
            title: '太字 (Ctrl+B)',
            action: () => wrapSelection('**', '**', '太字'),
        },
        {
            icon: IconItalic,
            title: '斜体 (Ctrl+I)',
            action: () => wrapSelection('*', '*', '斜体'),
        },
        {
            icon: IconStrikethrough,
            title: '取り消し線',
            action: () => wrapSelection('~~', '~~', '取り消し線'),
        },
        {
            icon: IconHeading,
            title: '見出し',
            action: () => prefixLines('## '),
        },
        { icon: IconQuote, title: '引用', action: () => prefixLines('> ') },
        {
            icon: IconCode,
            title: 'コード',
            action: () => wrapSelection('`', '`', 'コード'),
        },
        { icon: IconLink, title: 'リンク (Ctrl+K)', action: insertLink },
        {
            icon: IconList,
            title: '箇条書き',
            action: () => prefixLines('- '),
        },
        {
            icon: IconListNumbers,
            title: '番号付きリスト',
            action: () => prefixLines('', true),
        },
        {
            icon: IconListCheck,
            title: 'チェックリスト',
            action: () => prefixLines('- [ ] '),
        },
        {
            icon: IconTable,
            title: 'テーブル',
            action: insertTable,
            divider: true,
        },
    ];

    function handleShortcut(event: KeyboardEvent) {
        // Tab はリスト行にいるときだけ横取りする（それ以外の行では既定のフォーカス移動を残す）
        if (
            event.key === 'Tab' &&
            !event.ctrlKey &&
            !event.metaKey &&
            !event.altKey
        ) {
            if (indentListLines(event.shiftKey)) {
                event.preventDefault();
            }

            return;
        }

        // Enter はリスト行でのみ横取りする（IME 変換確定中の Enter は除く）
        if (
            event.key === 'Enter' &&
            !event.shiftKey &&
            !event.ctrlKey &&
            !event.metaKey &&
            !event.altKey
        ) {
            if (event.isComposing || event.keyCode === 229) {
                return;
            }

            if (continueList()) {
                event.preventDefault();
            }

            return;
        }

        if (!(event.ctrlKey || event.metaKey)) {
            return;
        }

        const key = event.key.toLowerCase();

        if (key === 'b') {
            event.preventDefault();
            wrapSelection('**', '**', '太字');
        } else if (key === 'i') {
            event.preventDefault();
            wrapSelection('*', '*', '斜体');
        } else if (key === 'k') {
            event.preventDefault();
            insertLink();
        }
    }

    return {
        wrapSelection,
        prefixLines,
        indentListLines,
        continueList,
        insertLink,
        insertWikiLink,
        insertTable,
        baseItems,
        handleShortcut,
    };
}
