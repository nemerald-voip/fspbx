<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NumberTranslationDetail extends Model
{
    // Legacy order values are text, sometimes without leading zeroes.
    public const ORDER_SQL = "CASE WHEN number_translation_detail_order ~ '^[0-9]+$' THEN number_translation_detail_order::numeric END ASC NULLS LAST";

    protected $table = 'v_number_translation_details';
    protected $primaryKey = 'number_translation_detail_uuid';
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';
    protected $guarded = [];
}
