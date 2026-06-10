<?php

namespace App\Livewire\Tokens;

use App\Models\Meter;
use App\Models\Token;
use App\Services\DartTokenEngine;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class TokenManager extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'meter')]
    public string $meterFilter = '';

    public bool $showForm = false;
    public ?int $meter_id = null;
    public ?float $amount_kwh = null;
    public int $random_no = 7;

    public ?string $lastTokenNo = null;
    public ?string $lastError = null;
    public ?bool $engineHealthy = null;

    protected function rules(): array
    {
        return [
            'meter_id'   => ['required', 'integer', 'exists:meters,id'],
            'amount_kwh' => ['required', 'numeric', 'min:0.001'],
            'random_no'  => ['required', 'integer', 'between:0,15'],
        ];
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'meterFilter'], true)) {
            $this->resetPage();
        }
    }

    public function newToken(): void
    {
        $this->reset(['meter_id', 'amount_kwh', 'lastTokenNo', 'lastError']);
        $this->random_no = 7;
        $this->resetErrorBag();
        $this->showForm  = true;
    }

    public function checkEngine(): void
    {
        try {
            $this->engineHealthy = DartTokenEngine::fromConfig()->isHealthy();
        } catch (\Throwable $e) {
            $this->engineHealthy = false;
        }
    }

    public function issue(): void
    {
        $data = $this->validate();
        $meter = Meter::with(['vendingKey', 'tariff'])->findOrFail($data['meter_id']);
        if (! $meter->vendingKey) {
            $this->addError('meter_id', 'Meter has no vending key assigned.');
            return;
        }

        try {
            $engine = DartTokenEngine::fromConfig();
            $resp   = $engine->generateTransferElectricityCredit(
                $meter->vendingKey,
                $meter,
                (float) $data['amount_kwh'],
                CarbonImmutable::now(),
                (int) $data['random_no'],
            );
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            Token::create([
                'request_id'      => (string) \Illuminate\Support\Str::uuid(),
                'token_no'        => '00000000000000000000',
                'meter_id'        => $meter->id,
                'vending_key_id'  => $meter->vending_key_id,
                'issued_by'       => auth()->id(),
                'token_class'     => 0,
                'token_kind'      => 'TransferElectricityCredit',
                'amount_kwh'      => $data['amount_kwh'],
                'currency'        => $meter->tariff?->currency,
                'issued_at'       => now(),
                'payload'         => $data,
                'engine_response' => ['error' => $e->getMessage()],
                'status'          => 'failed',
                'failure_reason'  => substr($e->getMessage(), 0, 250),
            ]);
            session()->flash('sts.flash', 'Token generation FAILED.');
            return;
        }

        $tokenNo = $this->extractTokenNo($resp);
        Token::create([
            'request_id'      => (string) ($resp['request_id'] ?? \Illuminate\Support\Str::uuid()),
            'token_no'        => $tokenNo ?? '00000000000000000000',
            'meter_id'        => $meter->id,
            'vending_key_id'  => $meter->vending_key_id,
            'issued_by'       => auth()->id(),
            'token_class'     => 0,
            'token_kind'      => 'TransferElectricityCredit',
            'amount_kwh'      => $data['amount_kwh'],
            'currency'        => $meter->tariff?->currency,
            'issued_at'       => now(),
            'payload'         => $data,
            'engine_response' => $resp,
            'status'          => $tokenNo ? 'issued' : 'failed',
            'failure_reason'  => $tokenNo ? null : 'Engine returned no token_no',
        ]);

        $this->lastTokenNo = $tokenNo;
        $this->lastError   = null;
        session()->flash('sts.flash', $tokenNo ? "Token issued: {$tokenNo}" : 'Engine accepted request but returned no token.');
        $this->showForm = false;
    }

    public function cancel(): void
    {
        $this->showForm = false;
    }

    protected function extractTokenNo(array $resp): ?string
    {
        // Engine envelope: { data: { token: [ { token_no } ] } } per Dart api_server contract.
        $tok = $resp['data']['token'][0]['token_no'] ?? $resp['data']['token_no'] ?? null;
        return is_string($tok) ? $tok : null;
    }

    #[Computed]
    public function meters()
    {
        return Meter::query()
            ->where('is_active', true)
            ->whereNotNull('vending_key_id')
            ->with('customer:id,account_number,name')
            ->orderBy('pan')
            ->get(['id', 'pan', 'customer_id']);
    }

    public function render()
    {
        $tokens = Token::query()
            ->with(['meter:id,pan', 'issuer:id,name', 'vendingKey:id,name'])
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('token_no', 'like', "%{$this->search}%")
                  ->orWhere('token_kind', 'like', "%{$this->search}%");
            }))
            ->when($this->meterFilter, fn ($q) => $q->where('meter_id', $this->meterFilter))
            ->orderByDesc('issued_at')
            ->paginate(20);

        return view('livewire.tokens.token-manager', ['tokens' => $tokens]);
    }
}
