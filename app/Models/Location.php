<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Location extends Model
{
    use HasFactory, TraitUuid;

    protected $table = 'locations';
    protected $primaryKey = 'location_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'location_uuid',
        'domain_uuid',
        'name',
        'description',
    ];

    protected static function booted()
    {
        static::deleting(function ($location) {
            // Delete all entries in the locationables table for this location's UUID.
            // This is the most generic way to handle cascading deletes for a morphed
            // relationship with multiple possible related models.
            DB::table('locationables')
                ->where('location_uuid', $location->location_uuid)
                ->delete();
        });
    }

    /**
     * Get all of the users that are assigned to this location.
     */
    public function users()
    {
        return $this->morphedByMany(
            \App\Models\User::class,
            'locationable',
            'locationables',
            'location_uuid',               // this model's id column on pivot 
            'locationable_id',             // related model's id column on pivot
            'location_uuid',               // this model's local key
            'user_uuid'                    // related model's local key
        );
    }
}
