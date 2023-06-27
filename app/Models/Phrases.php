<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Phrases extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_phrases";

    public $timestamps = false;

    protected $primaryKey = 'phrase_uuid';

    protected $keyType = 'string';
}
