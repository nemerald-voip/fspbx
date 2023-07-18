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

        // logger($this->timeoutDestinationsByCategory['extensions']);
                logger($this->timeoutCategory);

    }

    public function updatedTimeoutCategory($value)
    {
        logger($value);
        // Put your desired code here that you want to execute when timeoutCategory is set.
        // You can access the updated value of timeoutCategory through the $value variable.

        // Example: You can call a method to handle the changes, like:
        // $this->handleTimeoutCategoryChange($value);
    }

    public function render()
    {
        return view('livewire.timeout-destinations');
    }
}
