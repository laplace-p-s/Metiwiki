// textarea 内の指定位置のキャレット座標を、同じ字送りのミラー要素を作って算出する。
// 戻り値の top / left は textarea の内容座標（スクロール量は含まない）。
const CARET_STYLE_PROPS = [
    'boxSizing',
    'width',
    'height',
    'overflowX',
    'overflowY',
    'borderTopWidth',
    'borderRightWidth',
    'borderBottomWidth',
    'borderLeftWidth',
    'paddingTop',
    'paddingRight',
    'paddingBottom',
    'paddingLeft',
    'fontStyle',
    'fontVariant',
    'fontWeight',
    'fontStretch',
    'fontSize',
    'lineHeight',
    'fontFamily',
    'textAlign',
    'textTransform',
    'textIndent',
    'letterSpacing',
    'wordSpacing',
    'tabSize',
    'whiteSpace',
    'wordWrap',
    'wordBreak',
] as const;

export function caretOffset(
    el: HTMLTextAreaElement,
    position: number,
): { top: number; left: number; lineHeight: number } {
    const div = document.createElement('div');
    const computed = getComputedStyle(el);

    for (const p of CARET_STYLE_PROPS) {
        div.style.setProperty(
            p.replace(/[A-Z]/g, (c) => `-${c.toLowerCase()}`),
            computed.getPropertyValue(
                p.replace(/[A-Z]/g, (c) => `-${c.toLowerCase()}`),
            ),
        );
    }

    div.style.position = 'absolute';
    div.style.visibility = 'hidden';
    div.style.whiteSpace = 'pre-wrap';
    div.style.overflowWrap = 'break-word';
    div.textContent = el.value.slice(0, position);

    const span = document.createElement('span');
    span.textContent = el.value.slice(position) || '.';
    div.appendChild(span);
    document.body.appendChild(div);

    const top = span.offsetTop;
    const left = span.offsetLeft;
    const lineHeight =
        parseInt(computed.lineHeight) || parseInt(computed.fontSize) * 1.4;

    document.body.removeChild(div);

    return { top, left, lineHeight };
}

/** キャレット直下（次の行の頭）の画面座標。position: fixed の要素をそこへ置くために使う */
export function caretScreenPosition(
    el: HTMLTextAreaElement,
    position: number,
): { left: number; top: number } {
    const { top, left, lineHeight } = caretOffset(el, position);
    const rect = el.getBoundingClientRect();

    return {
        left: rect.left + left - el.scrollLeft,
        top: rect.top + top - el.scrollTop + lineHeight,
    };
}
