<?php

namespace App\Enums;

/**
 * 版の種別。削除と復元も版として記録し、履歴と最近の更新に残す。
 * 削除・復元の版の本文は直前の版と同じ（差分は無い）
 */
enum VersionKind: string
{
    case Edit = 'edit';
    case Delete = 'delete';
    case Restore = 'restore';
}
