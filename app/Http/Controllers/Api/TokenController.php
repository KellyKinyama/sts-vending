<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meter;
use App\Models\Token;
use App\Services\DartTokenEngine;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TokenController extends Controller
{
    public function index(Request $r)
    {
        return Token::query()
            ->with(['meter:id,pan', 'issuer:id,name', 'vendingKey:id,name'])
            ->when($r->integer('meter_id'), fn ($q, $v) => $q->where('meter_id', $v))
            ->when($r->string('status')->value(), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('issued_at')
            ->paginate($r->integer('per_page', 25));
    }

    public function show(Token $token)
    {
        return $token->load(['meter:id,pan', 'issuer:id,name', 'vendingKey:id,name']);
    }

    public function issue(Request $r)
    {
        $data = $r->validate([
            'meter_id'   => ['required', 'integer', 'exists:meters,id'],
            'amount_kwh' => ['required', 'numeric', 'min:0.001'],
            'random_no'  => ['nullable', 'integer', 'between:0,15'],
        ]);
        $meter = Meter::with(['vendingKey', 'tariff'])->findOrFail($data['meter_id']);
        abort_unless($meter->vendingKey, 422, 'Meter has no vending key assigned.');

        try {
            $engine = DartTokenEngine::fromConfig();
            $resp = $engine->generateTransferElectricityCredit(
                $meter->vendingKey,
                $meter,
                (float) $data['amount_kwh'],
                CarbonImmutable::now(),
                (int) ($data['random_no'] ?? 7),
            );
        } catch (\Throwable $e) {
            $token = $this->persist($meter, $data, ['error' => $e->getMessage()], null, 'failed', $e->getMessage());
            return response()->json(['token' => $token, 'error' => $e->getMessage()], 502);
        }

        $tokenNo = $resp['data']['token'][0]['token_no'] ?? $resp['data']['token_no'] ?? null;
        $token = $this->persist($meter, $data, $resp, $tokenNo, $tokenNo ? 'issued' : 'failed', $tokenNo ? null : 'Engine returned no token_no');
        return response()->json(['token' => $token, 'engine' => $resp], $tokenNo ? 201 : 502);
    }

    public function decode(Request $r, string $tokenNo)
    {
        $data = $r->validate([
            'meter_id' => ['required', 'integer', 'exists:meters,id'],
        ]);
        $meter = Meter::with('vendingKey')->findOrFail($data['meter_id']);
        abort_unless($meter->vendingKey, 422, 'Meter has no vending key assigned.');

        try {
            return DartTokenEngine::fromConfig()->decode($meter->vendingKey, $meter, $tokenNo);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    private function persist(Meter $meter, array $payload, array $resp, ?string $tokenNo, string $status, ?string $failure): Token
    {
        return Token::create([
            'request_id'      => (string) ($resp['request_id'] ?? Str::uuid()),
            'token_no'        => $tokenNo ?? '00000000000000000000',
            'meter_id'        => $meter->id,
            'vending_key_id'  => $meter->vending_key_id,
            'issued_by'       => auth()->id(),
            'token_class'     => 0,
            'token_kind'      => 'TransferElectricityCredit',
            'amount_kwh'      => $payload['amount_kwh'],
            'currency'        => $meter->tariff?->currency,
            'issued_at'       => now(),
            'payload'         => $payload,
            'engine_response' => $resp,
            'status'          => $status,
            'failure_reason'  => $failure ? substr($failure, 0, 250) : null,
        ]);
    }
}
