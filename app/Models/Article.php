<?php

namespace App\Models;

use App\Enums\VersionKind;
use Database\Factories\ArticleFactory;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $title_key
 * @property string $body
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property string|null $active_title_key
 * @property string|null $system_key
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'body', 'created_by', 'updated_by', 'deleted_by'])]
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory, SoftDeletes;

    /** ヘルプページの system_key */
    public const SYSTEM_HELP = 'help';

    /**
     * 通常のページだけに絞るグローバルスコープの名前。
     * システムページ（ヘルプ）は一覧・検索・最近の更新・リンク解決などに出さないため、既定で除く。
     * 扱うときは withoutGlobalScope(self::CONTENT_SCOPE) で明示する
     */
    public const CONTENT_SCOPE = 'content';

    protected static function booted(): void
    {
        static::addGlobalScope(self::CONTENT_SCOPE, fn (Builder $query) => $query->whereNull($query->qualifyColumn('system_key')));
    }

    /**
     * ページ ID で指す更新系のルート（/-/pages/{article}）は、システムページも対象にする。
     * 操作の可否は ArticlePolicy が判定する
     *
     * @param  Model|BuilderContract|Relation<*, *, *>  $query
     * @return BuilderContract
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        // 通常のルートではモデル自身が、子ルートの結合ではクエリが渡される
        if ($query instanceof self) {
            $query = $query->newQueryWithoutScope(self::CONTENT_SCOPE);
        } elseif ($query instanceof Builder) {
            $query = $query->withoutGlobalScope(self::CONTENT_SCOPE);
        }

        return parent::resolveRouteBindingQuery($query, $value, $field);
    }

    /**
     * システムページ（system_key 付き）を取得するクエリ
     *
     * @return Builder<self>
     */
    public static function system(string $key): Builder
    {
        return static::withoutGlobalScope(self::CONTENT_SCOPE)->where('system_key', $key);
    }

    public function isSystem(): bool
    {
        return $this->system_key !== null;
    }

    public function isHelp(): bool
    {
        return $this->system_key === self::SYSTEM_HELP;
    }

    /**
     * 正規化済みタイトルから照合キーを作る。
     *
     * タイトルの一意性判定・解決・リンク追跡・リダイレクトはすべてこのキーの完全一致で行い、
     * DB の照合順序に依存しない。
     */
    public static function titleKey(string $title): string
    {
        return mb_strtolower($title);
    }

    /**
     * タイトルを設定すると照合キーも同時に設定する。
     *
     * @return Attribute<string, string>
     */
    protected function title(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => [
                'title' => $value,
                'title_key' => static::titleKey($value),
            ],
        );
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return BelongsTo<User, $this> */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * 最後の削除の版（削除済みページ一覧で削除の理由を示す）
     *
     * @return HasOne<ArticleVersion, $this>
     */
    public function latestDeletion(): HasOne
    {
        return $this->hasOne(ArticleVersion::class)->ofMany(
            ['version_number' => 'max'],
            fn ($query) => $query->where('kind', VersionKind::Delete),
        );
    }

    /** @return HasMany<ArticleVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(ArticleVersion::class)->orderBy('version_number', 'desc');
    }

    /**
     * このページが他ページへ張っているリンク
     *
     * @return HasMany<ArticleLink, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(ArticleLink::class, 'from_article_id');
    }

    /**
     * このページへの改名前タイトルからのリダイレクト
     *
     * @return HasMany<ArticleRedirect, $this>
     */
    public function redirects(): HasMany
    {
        return $this->hasMany(ArticleRedirect::class);
    }
}
