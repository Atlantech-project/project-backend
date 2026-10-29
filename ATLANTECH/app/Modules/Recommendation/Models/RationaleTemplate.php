<?php

namespace App\Modules\Recommendation\Models;

use Database\Factories\Recommendation\RationaleTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fixed, versioned templates; never LLM (D11).
 * Generated, never written: `active_code`.
 */
#[Table(name: 'rationale_templates', timestamps: false)]
#[Fillable(['code', 'version', 'template_text', 'is_active'])]
#[UseFactory(RationaleTemplateFactory::class)]
class RationaleTemplate extends Model
{
    /** @use HasFactory<RationaleTemplateFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<RecommendationOption, $this>
     */
    public function recommendationOptions(): HasMany
    {
        return $this->hasMany(RecommendationOption::class, 'rationale_template_id');
    }
}
