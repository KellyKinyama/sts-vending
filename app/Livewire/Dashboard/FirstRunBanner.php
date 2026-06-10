<?php

namespace App\Livewire\Dashboard;

use App\Models\Meter;
use App\Models\SupplyGroup;
use App\Models\VendingKey;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;

/**
 * Dashboard banner that shows up only when the database has no
 * SupplyGroup / VendingKey / Meter yet. Clicking "Seed demo data"
 * runs the same DatabaseSeeder that `php artisan sts:setup` does,
 * so the user can start exploring without leaving the browser.
 */
class FirstRunBanner extends Component
{
    public bool $hasData = false;

    public ?string $message = null;

    public bool $allowed = false;

    public function mount(): void
    {
        // Only expose the in-browser seeder in non-production. Prod
        // operators should run `php artisan sts:setup` deliberately.
        $this->allowed = ! app()->environment('production');
        $this->refresh();
    }

    public function refresh(): void
    {
        $this->hasData = SupplyGroup::query()->exists()
            && VendingKey::query()->exists()
            && Meter::query()->exists();
    }

    public function seed(): void
    {
        if (! $this->allowed) {
            $this->message = 'Seeding is disabled in production. Run `php artisan sts:setup` on the server.';

            return;
        }

        try {
            $code = Artisan::call('db:seed', ['--force' => true]);
            if ($code !== 0) {
                $this->message = 'db:seed exited with code '.$code.'. Check storage/logs.';

                return;
            }
            $this->message = 'Demo data seeded. Reload the page to see it.';
            $this->refresh();
        } catch (\Throwable $e) {
            $this->message = 'Seed failed: '.$e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.dashboard.first-run-banner');
    }
}
