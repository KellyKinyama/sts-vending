<?php

namespace App\Livewire\Dashboard;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

class EngineStatusCard extends Component
{
    public string $url = '';

    public string $bearerPreview = '';

    /** 'ok' | 'error' | null (checking) */
    public ?string $status = null;

    public ?string $error = null;

    public ?int $latencyMs = null;

    public ?string $checkedAt = null;

    public ?string $service = null;

    public function mount(): void
    {
        $this->url = (string) config('services.sts_engine.url');
        $token = (string) config('services.sts_engine.token');
        $this->bearerPreview = $token === '' ? '(none)' : substr($token, 0, 8).'…';
        $this->refresh();
    }

    public function refresh(): void
    {
        $start = microtime(true);
        try {
            $resp = Http::withToken((string) config('services.sts_engine.token'))
                ->timeout(2)
                ->connectTimeout(1)
                ->acceptJson()
                ->get(rtrim($this->url, '/').'/healthz');

            $this->latencyMs = (int) round((microtime(true) - $start) * 1000);
            $this->checkedAt = now()->format('H:i:s');

            if ($resp->successful() && $resp->json('status') === 'ok') {
                $this->status = 'ok';
                $this->service = (string) ($resp->json('service') ?? '');
                $this->error = null;
            } else {
                $this->status = 'error';
                $this->error = 'HTTP '.$resp->status();
                $this->service = null;
            }
        } catch (ConnectionException) {
            $this->status = 'error';
            $this->error = 'connection refused';
            $this->latencyMs = null;
            $this->checkedAt = now()->format('H:i:s');
            $this->service = null;
        } catch (\Throwable $e) {
            $this->status = 'error';
            $this->error = $e->getMessage();
            $this->latencyMs = null;
            $this->checkedAt = now()->format('H:i:s');
            $this->service = null;
        }
    }

    public function render()
    {
        return view('livewire.dashboard.engine-status-card');
    }
}
