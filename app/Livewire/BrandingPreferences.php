<?php

namespace App\Livewire;

use App\Services\BrandingService;
use App\Support\Branding;
use App\Support\Feedback;
use Livewire\Component;
use Livewire\WithFileUploads;

class BrandingPreferences extends Component
{
    use WithFileUploads;

    public string $name = 'MoneyControl';

    public bool $showName = true;

    public array $colors = [];

    public $icon = null;

    public bool $removeIcon = false;

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
        $branding = auth()->user()->branding ?? [];
        $this->name = $branding['name'] ?? 'MoneyControl';
        $this->showName = $branding['show_name'] ?? true;
        $this->colors = array_fill_keys(array_keys(Branding::COLORS), '');
        $this->colors = array_replace($this->colors, $branding['colors'] ?? []);
    }

    public function resetColors(): void
    {
        $this->colors = array_fill_keys(array_keys(Branding::COLORS), '');
    }

    public function save(BrandingService $service)
    {
        abort_unless(auth()->check(), 403);
        $service->update([
            'name' => $this->name, 'show_name' => $this->showName, 'colors' => $this->colors,
        ], $this->icon, $this->removeIcon);
        Feedback::flash('updated', 'Personalização salva com sucesso!');

        return redirect()->route('preferences');
    }

    public function render()
    {
        return view('livewire.branding-preferences', ['colorLabels' => Branding::COLORS]);
    }
}
