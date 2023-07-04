<?php

namespace App\Http\Livewire\Extensions;

use Livewire\Component;

class MakeUser extends Component
{
    public $extension;

    public function render()
    {
        return view('livewire.extensions.make-user');
    }

    public function makeUser()
    {
        logger($this->extension);
    }
}
