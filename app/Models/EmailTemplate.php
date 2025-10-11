<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmailTemplate extends Model
{
    use HasFactory, TraitUuid;

    protected $table = 'v_email_templates';
    protected $primaryKey = 'email_template_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'email_template_uuid',
        'domain_uuid',
        'template_language',
        'template_category',
        'template_subcategory',
        'template_body',
        'template_type',
        'template_enabled',
        'template_description',
    ];

}
