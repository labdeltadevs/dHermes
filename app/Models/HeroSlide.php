<?php

namespace App\Models;

use Database\Factories\HeroSlideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Comunicado que se muestra como popup al inicio del sistema. Es contenido
 * global de la plataforma: lo publica un superadministrador y no pertenece
 * a ningún tenant.
 *
 * @property string $id
 * @property string $title
 * @property string $body
 * @property bool $is_published
 * @property int $sort_order
 * @property string|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'body', 'is_published', 'sort_order', 'created_by'])]
class HeroSlide extends Model
{
    /** @use HasFactory<HeroSlideFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeInDisplayOrder(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('created_at');
    }
}
