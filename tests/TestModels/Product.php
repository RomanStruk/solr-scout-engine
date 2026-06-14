<?php

namespace RomanStruk\SolrScoutEngine\Tests\TestModels;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use Searchable;

    protected $guarded = [];

    public $timestamps = false;

    public function searchableAs(): string
    {
        return 'products';
    }

    /**
     * Disable automatic sync on save; tests drive the engine explicitly.
     */
    public function shouldBeSearchable(): bool
    {
        return false;
    }

    public function toSearchableArray(): array
    {
        return [
            'name_txt_uk' => $this->name,
            'price_f' => (float) $this->price,
            'active_b' => (int) $this->active,
            'category_id_i' => $this->category_id,
        ];
    }

    public function scoutIndexMigration(): array
    {
        return [];
    }
}
