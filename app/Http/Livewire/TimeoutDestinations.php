<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Session;

class TimeoutDestinations extends Component
{
    public $timeoutCategory;
    public $timeoutDestinationsByCategory;
    public $timeoutData;

    public function mount() 
    {
        $this->timeoutCategory = 'Hang up';
        $this->timeoutDestinationsByCategory = [];
        foreach ([
                     'extensions',
                     'voicemails',
                     'ringgroup',
                     'ivrs',
                     'others'
                 ] as $category) {
            $c = getDestinationByCategory($category, $this->timeoutData);
            if($c['selectedCategory']) {
                $this->timeoutCategory = $c['selectedCategory'];
            }
            $this->timeoutDestinationsByCategory[$category] = $c['list'];

        }
        unset($c, $category);

        // logger($this->timeoutDestinationsByCategory);
        $this->emit('select2');
    }

        // // lifecycle hook sometimes we require it for select2
        // public function hydrate()
        // {
        //     $this->emit('select2');
        // }

    public function render()
    {
        return view('livewire.timeout-destinations');
    }
}
