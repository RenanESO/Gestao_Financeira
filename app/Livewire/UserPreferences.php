<?php

namespace App\Livewire;

use App\Services\UserPreferencesService;
use App\Support\Feedback;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class UserPreferences extends Component
{
    public string $theme = 'dark';

    public int $notificationDaysBefore = 7;

    public function mount(): void
    {
        $user = Auth::user();
        $this->theme = $user->theme;
        $this->notificationDaysBefore = $user->notification_days_before;
    }

    public function save(UserPreferencesService $service): void
    {
        $service->update([
            'theme' => $this->theme,
            'notification_days_before' => $this->notificationDaysBefore,
        ]);

        Feedback::now('updated', 'Preferências alteradas com sucesso!', 'O tema será aplicado nas próximas páginas abertas.');
        $this->dispatch('preferences-saved', theme: $this->theme);
    }

    public function render()
    {
        return view('livewire.user-preferences');
    }
}
