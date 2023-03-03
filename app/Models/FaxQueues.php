<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FaxQueues extends Model
{
    use HasFactory, TraitUuid;

    protected $table = "v_fax_queue";

    public $timestamps = false;

    protected $primaryKey = 'fax_queue_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = ['fax_queue_uuid'];

    public function faxFile()
    {
        return $this->hasOne(FaxFiles::class,  'fax_file_path', 'fax_file');
    }

    public function getFaxFile()
    {
        return $this->faxFile()->firstOrFail();
    }
}
