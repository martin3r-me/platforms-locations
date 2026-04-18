<?php

namespace Platform\Locations\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class Sidebar extends Component
{
    public function render()
    {
        $user = auth()->user();

        if (!$user) {
            return view('locations::livewire.sidebar', []);
        }

        return view('locations::livewire.sidebar', []);
    }
}
