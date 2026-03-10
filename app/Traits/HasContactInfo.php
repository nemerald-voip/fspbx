<?php

namespace App\Traits;

trait HasContactInfo
{
    // Relationships
    public function phones()
    {
        return $this->morphMany(\App\Models\ContactPhone::class, 'phoneable');
    }

    public function emails()
    {
        return $this->morphMany(\App\Models\ContactEmail::class, 'emailable');
    }

    public function addresses()
    {
        return $this->morphMany(\App\Models\ContactAddress::class, 'addressable');
    }

    // Cascading Logic: Automatically delete children when Parent is deleted
    // This allows us to remove the logic from the individual Observers!
    public static function bootHasContactInfo()
    {
        static::deleting(function ($model) {
            $model->phones()->delete();
            $model->emails()->delete();
            $model->addresses()->delete();
        });
    }
}