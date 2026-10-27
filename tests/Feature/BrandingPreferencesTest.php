<?php

namespace Tests\Feature;

use App\Livewire\BrandingPreferences;
use App\Models\User;
use App\Services\BrandingService;
use App\Support\Branding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BrandingPreferencesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    public function test_personalization_is_saved_and_applied_only_to_its_owner(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Livewire::actingAs($user)->test(BrandingPreferences::class)
            ->set('name', 'Meu Financeiro')
            ->set('colors.primary', '#123456')
            ->set('colors.base-100', '#ffffff')
            ->call('save')->assertHasNoErrors()->assertRedirect(route('preferences'));
        $this->assertSame('Meu Financeiro', $user->fresh()->branding['name']);
        $this->assertNull($other->fresh()->branding);
        $this->actingAs($user->fresh())->get(route('dashboard'))
            ->assertSee('Meu Financeiro')->assertSee('--color-primary:#123456', false)
            ->assertSee('--color-primary-content:#ffffff', false);
        $this->actingAs($other)->get(route('dashboard'))->assertDontSee('Meu Financeiro');
        $this->assertSame('', Branding::style());
    }

    public function test_invalid_colors_name_and_svg_upload_are_rejected(): void
    {
        $user = User::factory()->create();
        Livewire::actingAs($user)->test(BrandingPreferences::class)
            ->set('name', ' ')
            ->set('colors.primary', 'red;display:none')
            ->call('save')->assertHasErrors(['name', 'colors.primary']);
        Livewire::actingAs($user)->test(BrandingPreferences::class)
            ->set('icon', UploadedFile::fake()->create('icon.svg', 1, 'image/svg+xml'))
            ->call('save')->assertHasErrors('icon');
        $this->assertNull($user->fresh()->branding);
    }

    public function test_icon_is_private_and_replacement_and_removal_clean_old_files(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $service = app(BrandingService::class);
        $data = ['name' => 'Meu sistema', 'show_name' => false, 'colors' => array_fill_keys(array_keys(Branding::COLORS), '')];
        $service->update($data, UploadedFile::fake()->image('icon.png'));
        $old = $user->fresh()->branding['icon_path'];
        Storage::disk('local')->assertExists($old);
        $this->get(route('branding.icon'))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs(User::factory()->create())->get(route('branding.icon'))->assertNotFound();
        $this->actingAs($user->fresh());
        $service->update($data, UploadedFile::fake()->image('new.png'));
        Storage::disk('local')->assertMissing($old);
        $current = $user->fresh()->branding['icon_path'];
        $service->update($data, null, true);
        Storage::disk('local')->assertMissing($current);
        $this->assertNull($user->fresh()->branding['icon_path']);
    }

    public function test_reset_restores_original_theme_colors_without_changing_theme(): void
    {
        $user = User::factory()->create(['theme' => 'light']);
        $this->actingAs($user);
        $user->forceFill(['branding' => ['name' => 'Meu sistema', 'show_name' => true, 'colors' => ['primary' => '#123456']]])->save();
        Livewire::test(BrandingPreferences::class)->call('resetColors')->call('save')->assertHasNoErrors();
        $this->assertSame([], $user->fresh()->branding['colors']);
        $this->assertSame('light', $user->fresh()->theme);
        $this->assertSame('', Branding::style());
    }

    public function test_guest_cannot_read_icon_or_edit_personalization(): void
    {
        $this->get(route('branding.icon'))->assertRedirect(route('login'));
        Livewire::test(BrandingPreferences::class)->assertForbidden();
    }

    public function test_first_save_without_color_edits_keeps_original_dark_and_light_themes(): void
    {
        foreach (['dark', 'light'] as $theme) {
            $user = User::factory()->create(['theme' => $theme]);
            Livewire::actingAs($user)->test(BrandingPreferences::class)
                ->assertSet('colors', array_fill_keys(array_keys(Branding::COLORS), ''))
                ->assertSee('themeColor(', false)
                ->assertDontSee('#808080')
                ->call('save')->assertHasNoErrors();
            $this->assertSame([], $user->fresh()->branding['colors']);
            $this->assertSame('', Branding::style());
            $this->assertSame($theme, $user->fresh()->theme);
        }
    }
}
