<?php

namespace App\Support;

/**
 * 2 つの列の最長共通部分列（LCS）。差分画面のプレビュー比較（ブロック単位・文字単位）で使う。
 *
 * 先頭と末尾の一致を先に除き、残りだけを動的計画法で比べる。
 * 残りの表（要素数の積）が上限を超えるときは、残りを比べずに complete = false を返す。
 */
class SequenceDiff
{
    /**
     * 共通部分列に入る要素の添字
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return array{0: array<int, true>, 1: array<int, true>, 2: bool} [a の共通要素, b の共通要素, 残りまで比べたか]
     */
    public static function commonIndexes(array $a, array $b, int $maxCells): array
    {
        $keptA = [];
        $keptB = [];

        $start = 0;
        $n = count($a);
        $m = count($b);
        while ($start < $n && $start < $m && $a[$start] === $b[$start]) {
            $keptA[$start] = $keptB[$start] = true;
            $start++;
        }

        $endA = $n;
        $endB = $m;
        while ($endA > $start && $endB > $start && $a[$endA - 1] === $b[$endB - 1]) {
            $endA--;
            $endB--;
            $keptA[$endA] = true;
            $keptB[$endB] = true;
        }

        $rows = $endA - $start;
        $cols = $endB - $start;
        if ($rows === 0 || $cols === 0) {
            return [$keptA, $keptB, true];
        }
        if ($rows * $cols > $maxCells) {
            return [$keptA, $keptB, false];
        }

        // $len[$i][$j]: a[$i..] と b[$j..] の LCS の長さ（中間部分の添字で持つ）
        $len = array_fill(0, $rows + 1, array_fill(0, $cols + 1, 0));
        for ($i = $rows - 1; $i >= 0; $i--) {
            for ($j = $cols - 1; $j >= 0; $j--) {
                $len[$i][$j] = $a[$start + $i] === $b[$start + $j]
                    ? $len[$i + 1][$j + 1] + 1
                    : max($len[$i + 1][$j], $len[$i][$j + 1]);
            }
        }

        for ($i = 0, $j = 0; $i < $rows && $j < $cols;) {
            if ($a[$start + $i] === $b[$start + $j]) {
                $keptA[$start + $i] = $keptB[$start + $j] = true;
                $i++;
                $j++;
            } elseif ($len[$i + 1][$j] >= $len[$i][$j + 1]) {
                $i++;
            } else {
                $j++;
            }
        }

        return [$keptA, $keptB, true];
    }
}
