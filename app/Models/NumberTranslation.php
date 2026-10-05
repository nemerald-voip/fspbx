<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NumberTranslation extends Model
{
    protected $table = 'v_number_translations';
    protected $primaryKey = 'number_translation_uuid';
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';
    protected $guarded = [];

    public function rules(): HasMany
    {
        return $this->hasMany(NumberTranslationDetail::class, 'number_translation_uuid')
            ->orderByRaw(NumberTranslationDetail::ORDER_SQL)
            ->orderBy('number_translation_detail_uuid');
    }
}
