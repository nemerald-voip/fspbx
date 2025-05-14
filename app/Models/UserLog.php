<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class UserLog
 *
 * @property string                      $user_log_uuid
 * @property string|null                 $domain_uuid
 * @property \Illuminate\Support\Carbon|null $timestamp
 * @property string|null                 $user_uuid
 * @property string|null                 $username
 * @property string|null                 $type
 * @property string|null                 $result
 * @property string|null                 $remote_address
 * @property string|null                 $user_agent
 * @property \Illuminate\Support\Carbon|null $insert_date
 * @property string|null                 $insert_user
 * @property \Illuminate\Support\Carbon|null $update_date
 * @property string|null                 $update_user
 */
class UserLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'v_user_logs';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'user_log_uuid';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * Custom timestamp column names.
     */
    const CREATED_AT = 'insert_date';
    const UPDATED_AT = 'update_date';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_log_uuid',
        'domain_uuid',
        'timestamp',
        'user_uuid',
        'username',
        'type',
        'result',
        'remote_address',
        'user_agent',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'timestamp'   => 'datetime',
        'insert_date' => 'datetime',
        'update_date' => 'datetime',
    ];

    /**
     * Relationship: the user who generated this log.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_uuid', 'user_uuid');
    }


    /**
     * Relationship: the domain that this log belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

}
