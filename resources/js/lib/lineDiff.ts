// 行単位の差分

export type DiffLine = {
    type: 'same' | 'add' | 'del';
    text: string;
    // 1 起点の行番号。その側に存在しない行（追加行の oldNo など）は null
    oldNo: number | null;
    newNo: number | null;
};

export type SideBySideRow = {
    left: string | null;
    leftNo: number | null;
    right: string | null;
    rightNo: number | null;
    type: 'same' | 'change' | 'add' | 'del';
};

/**
 * LCS（最長共通部分列）ベースの行差分。行順は新しい本文の並びに従う。
 */
export function computeLineDiff(oldText: string, newText: string): DiffLine[] {
    const oldLines = (oldText || '').split('\n');
    const newLines = (newText || '').split('\n');
    const m = oldLines.length;
    const n = newLines.length;

    const dp = Array.from({ length: m + 1 }, () => new Int32Array(n + 1));

    for (let i = 1; i <= m; i++) {
        for (let j = 1; j <= n; j++) {
            dp[i][j] =
                oldLines[i - 1] === newLines[j - 1]
                    ? dp[i - 1][j - 1] + 1
                    : Math.max(dp[i - 1][j], dp[i][j - 1]);
        }
    }

    const diff: Omit<DiffLine, 'oldNo' | 'newNo'>[] = [];
    let i = m;
    let j = n;

    while (i > 0 || j > 0) {
        if (i > 0 && j > 0 && oldLines[i - 1] === newLines[j - 1]) {
            diff.unshift({ type: 'same', text: oldLines[i - 1] });
            i--;
            j--;
        } else if (j > 0 && (i === 0 || dp[i][j - 1] >= dp[i - 1][j])) {
            diff.unshift({ type: 'add', text: newLines[j - 1] });
            j--;
        } else {
            diff.unshift({ type: 'del', text: oldLines[i - 1] });
            i--;
        }
    }

    let oldNo = 0;
    let newNo = 0;

    return diff.map((line) => ({
        ...line,
        oldNo: line.type === 'add' ? null : ++oldNo,
        newNo: line.type === 'del' ? null : ++newNo,
    }));
}

/**
 * computeLineDiff() の結果を左右比較用の行に組み替える。
 * 連続する変更（del / add の塊）を先頭から突き合わせ、余った側は反対側を空欄にする。
 */
export function toSideBySideRows(lines: DiffLine[]): SideBySideRow[] {
    const rows: SideBySideRow[] = [];
    let i = 0;

    while (i < lines.length) {
        if (lines[i].type === 'same') {
            rows.push({
                left: lines[i].text,
                leftNo: lines[i].oldNo,
                right: lines[i].text,
                rightNo: lines[i].newNo,
                type: 'same',
            });
            i++;
            continue;
        }

        // del / add がどちらの順で並んでいても塊としてまとめて扱う
        const block: DiffLine[] = [];

        while (i < lines.length && lines[i].type !== 'same') {
            block.push(lines[i]);
            i++;
        }

        const dels = block.filter((l) => l.type === 'del');
        const adds = block.filter((l) => l.type === 'add');

        for (let k = 0; k < Math.max(dels.length, adds.length); k++) {
            const del = dels[k] ?? null;
            const add = adds[k] ?? null;
            rows.push({
                left: del?.text ?? null,
                leftNo: del?.oldNo ?? null,
                right: add?.text ?? null,
                rightNo: add?.newNo ?? null,
                type: del && add ? 'change' : del ? 'del' : 'add',
            });
        }
    }

    return rows;
}

export function diffStats(lines: DiffLine[]): {
    added: number;
    deleted: number;
} {
    return {
        added: lines.filter((l) => l.type === 'add').length,
        deleted: lines.filter((l) => l.type === 'del').length,
    };
}
