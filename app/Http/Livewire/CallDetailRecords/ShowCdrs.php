<?php

namespace App\Http\Livewire\CallDetailRecords;

use Livewire\Component;

class ShowCdrs extends Component
{
    public function render()
    {
        return view('livewire.call-detail-records.show-cdrs')
            ->extends('layouts.horizontal',['page_title' => "Call Derail Records"])
            ->section('body');
    }
}
