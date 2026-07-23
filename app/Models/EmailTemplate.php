<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmailTemplate extends Model
{
    use HasFactory, TraitUuid;

    protected $table = 'email_templates';
    protected $primaryKey = 'email_template_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'email_template_uuid',
        'domain_uuid',
        'base_template_uuid',
        'base_version',
        'template_key',
        'template_type',
        'template_language',
        'template_category',
        'template_subcategory',
        'template_layout',
        'version',
        'template_subject',
        'template_html',
        'template_text',
        'template_enabled',
        'template_description',
        'checksum',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'template_enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (EmailTemplate $template) {
            $actor = app()->runningInConsole() ? null : session('user_uuid');
            $template->created_by = $template->created_by ?: $actor;
            $template->updated_by = $template->updated_by ?: $actor;
            $template->checksum = $template->contentChecksum();
        });

        static::updating(function (EmailTemplate $template) {
            if (! app()->runningInConsole()) {
                $template->updated_by = session('user_uuid');
            }
            if ($template->isDirty(['template_subject', 'template_html', 'template_text', 'template_layout'])) {
                $template->checksum = $template->contentChecksum();
            }
        });
    }

    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    public function baseTemplate()
    {
        return $this->belongsTo(self::class, 'base_template_uuid', 'email_template_uuid');
    }

    public function isDefault(): bool
    {
        return $this->template_type === 'default';
    }

    public function isCustom(): bool
    {
        return $this->template_type === 'custom';
    }

    public function contentChecksum(): string
    {
        return hash('sha256', implode("\n", [
            (string) $this->template_subject,
            (string) $this->template_html,
            (string) $this->template_text,
            (string) $this->template_layout,
        ]));
    }
}
