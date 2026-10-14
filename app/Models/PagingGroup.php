<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PagingGroup extends Model
{
    use HasFactory, TraitUuid;

    public const BUSY_SKIP = 'skip';
    public const BUSY_FORCE = 'force';

    public const MODE_LIVE = 'live';
    public const MODE_RECORDED = 'recorded';

    public const ROLE_MEMBER = 'member';
    public const ROLE_CALLER = 'caller';

    protected $table = 'paging_groups';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'dialplan_uuid',
        'name',
        'extension',
        'busy_mode',
        'mode',
        'intro_beep',
        'intro_recording',
        'enabled',
        'description',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'intro_beep' => 'boolean',
    ];

    public function members(): BelongsToMany
    {
        return $this->extensions(self::ROLE_MEMBER);
    }

    public function allowedCallers(): BelongsToMany
    {
        return $this->extensions(self::ROLE_CALLER);
    }

    private function extensions(string $role): BelongsToMany
    {
        return $this->belongsToMany(Extensions::class, 'paging_group_extensions', 'paging_group_uuid', 'extension_uuid', 'uuid', 'extension_uuid')
            ->wherePivot('role', $role)
            ->withPivotValue('role', $role)
            ->withTimestamps();
    }
}
