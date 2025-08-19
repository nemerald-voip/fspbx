<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProvisioningTemplate extends Model
{
    protected $table = 'provisioning_templates';
    protected $primaryKey = 'template_uuid';
    public $incrementing = false;       // UUID primary key
    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'vendor',
        'name',
        'type',             // 'default' | 'custom'
        'version',          // SemVer for defaults; customs carry base_version
        'revision',         // bumps on each custom edit
        'base_template',    // defaults: null; customs: source default name
        'base_version',     // defaults: null; customs: source default version
        'content',
        'checksum',
        'updated_by',
    ];

    protected $casts = [
        'revision'  => 'integer',
        'created_at'=> 'datetime',
        'updated_at'=> 'datetime',
    ];

    // --- Lifecycle hooks ----------------------------------------------------

    protected static function booted()
    {
        // On create: ensure checksum; set revision defaults
        static::creating(function (self $m) {
            if (!empty($m->content) && empty($m->checksum)) {
                $m->checksum = hash('sha256', $m->content);
            }
            if ($m->type === 'default') {
                $m->revision = 0;
            } elseif ($m->type === 'custom' && (is_null($m->revision) || $m->revision < 1)) {
                $m->revision = 1;
            }
        });

        // On update: if content changed, refresh checksum and bump revision for customs
        static::updating(function (self $m) {
            if ($m->isDirty('content')) {
                $m->checksum = hash('sha256', $m->content);
                if ($m->type === 'custom') {
                    $m->revision = (int) $m->revision + 1;
                }
            }
        });
    }

    // --- Scopes -------------------------------------------------------------

    public function scopeVendor($q, string $vendor)
    {
        return $q->where('vendor', strtolower($vendor));
    }

    public function scopeDefault($q)
    {
        return $q->where('type', 'default');
    }

    public function scopeCustom($q)
    {
        return $q->where('type', 'custom');
    }

    // --- Helpers ------------------------------------------------------------

    public function isDefault(): bool
    {
        return $this->type === 'default';
    }

    public function isCustom(): bool
    {
        return $this->type === 'custom';
    }

    public function isLocked(): bool
    {
        // per your rule: defaults are non-editable in the portal
        return $this->isDefault();
    }

    /**
     * Make (but do not persist) a custom copy from a DEFAULT template.
     * Controller can $clone->save() and then assign to a device.
     */
    public function makeCustomCopy(?string $newName = null, ?string $updatedBy = null): self
    {
        if (!$this->isDefault()) {
            throw new \LogicException('Can only clone from a default template.');
        }

        $clone = new self();
        $clone->vendor        = $this->vendor;
        $clone->name          = $newName ?: ($this->name . ' (Custom)');
        $clone->type          = 'custom';
        $clone->version       = $this->version;     // carry base SemVer for reference
        $clone->base_template = $this->name;
        $clone->base_version  = $this->version;
        $clone->revision      = 1;
        $clone->content       = $this->content;
        $clone->checksum      = hash('sha256', $this->content);
        $clone->updated_by    = $updatedBy;

        return $clone;
    }

}
