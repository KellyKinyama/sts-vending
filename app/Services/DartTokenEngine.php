<?php

namespace App\Services;

use App\Models\Meter;
use App\Models\VendingKey;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin HTTP client for the `nectar_sts_dart` API server.
 *
 * Translates Eloquent objects (VendingKey, Meter) into the
 * VirtualHsmParams JSON payload that the Dart `POST /v1/tokens`
 * endpoint expects, calls it, and unwraps the `ApiResponse`
 * envelope.
 */
class DartTokenEngine
{
    public function __construct(
        protected string $baseUrl,
        protected ?string $bearerToken = null,
        protected int $timeoutSeconds = 10,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            baseUrl: rtrim((string) config('services.sts_engine.url'), '/'),
            bearerToken: config('services.sts_engine.token'),
            timeoutSeconds: (int) config('services.sts_engine.timeout', 10),
        );
    }

    /**
     * Issue a Class 0 / SubClass 0 TransferElectricityCredit token.
     *
     * @return array{token_no:string, raw:array} engine response
     */
    public function generateTransferElectricityCredit(
        VendingKey $key,
        Meter $meter,
        float $amountKwh,
        CarbonInterface $issuedAt,
        int $randomNo = 7,
    ): array {
        $params = $this->meterParams($key, $meter) + [
            'class'     => '0',
            'subclass'  => '0',
            'amount'    => $amountKwh,
            'token_id'  => $issuedAt->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'random_no' => $randomNo,
        ];

        return $this->post('/v1/tokens', $params);
    }

    /**
     * Decode a 20-digit token via the engine and return the structured
     * data block.
     *
     * @return array decoded payload
     */
    public function decode(VendingKey $key, Meter $meter, string $tokenNo): array
    {
        $params = $this->meterParams($key, $meter);

        return $this->post('/v1/tokens/' . $tokenNo, $params);
    }

    /** Liveness probe for the Dart engine. */
    public function isHealthy(): bool
    {
        try {
            $response = $this->client()->get($this->baseUrl . '/healthz');

            return $response->ok();
        } catch (ConnectionException) {
            return false;
        }
    }

    /** Shared params from a VendingKey + Meter pair. */
    protected function meterParams(VendingKey $key, Meter $meter): array
    {
        // NOTE: we deliberately do NOT send `vending_key` over the
        // wire. The Dart server rejects it (`_rejectSensitiveParams`)
        // and uses its own configured `VENDING_KEY_HEX` instead, so
        // the secret never leaves Laravel's app-key-encrypted blob
        // except via the server's local env. The two sides must
        // agree on the key out-of-band (operator configuration).
        return [
            'decoder_key_generation_algorithm' => $key->algorithm === 'DKGA04' ? '04' : '02',
            'encryption_algorithm'             => $key->encryption_algorithm === 'EA11' ? 'misty1' : 'sta',
            'key_type'                         => $key->key_type,
            'supply_group_code'                => $key->supplyGroup->code,
            'tariff_index'                     => $key->tariff_index,
            'key_revision_no'                  => $key->key_revision_number,
            'issuer_identification_no'         => $meter->iin,
            'decoder_reference_number'         => $meter->iain,
            'base_date'                        => (string) $key->base_date,
        ];
    }

    protected function post(string $path, array $params): array
    {
        try {
            $response = $this->client()->post($this->baseUrl . $path, $params);
        } catch (ConnectionException $e) {
            throw new \RuntimeException(
                "STS engine unreachable at {$this->baseUrl}: " . $e->getMessage(),
                previous: $e,
            );
        }

        $body = $response->json() ?? [];

        if (! $response->successful()) {
            $message = $body['status']['message'] ?? "HTTP {$response->status()}";
            Log::warning('STS engine error', ['path' => $path, 'body' => $body]);
            throw new \RuntimeException("STS engine: {$message}");
        }

        return $body;
    }

    protected function client(): PendingRequest
    {
        $client = Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->asJson();

        if ($this->bearerToken) {
            $client = $client->withToken($this->bearerToken);
        }

        return $client;
    }
}
