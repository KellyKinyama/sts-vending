<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VendingKey;
use Illuminate\Http\Request;

class VendingKeyController extends Controller
{
    public function index(Request $r)
    {
        return VendingKey::query()
            ->with('supplyGroup:id,code,name')
            ->when($r->integer('supply_group_id'), fn ($q, $v) => $q->where('supply_group_id', $v))
            ->orderBy('name')
            ->paginate($r->integer('per_page', 25));
    }

    public function store(Request $r)
    {
        $data = $r->validate($this->rules());
        $this->assertVudkLength($data['algorithm'], $data['vudk_blob']);
        return response()->json(VendingKey::create($data), 201);
    }

    public function show(VendingKey $vendingKey)
    {
        // vudk_blob is in $hidden — not serialized.
        return $vendingKey->load('supplyGroup:id,code,name');
    }

    public function update(Request $r, VendingKey $vendingKey)
    {
        $data = $r->validate([
            'name'                 => ['sometimes', 'required', 'string', 'max:120'],
            'supply_group_id'      => ['sometimes', 'required', 'integer', 'exists:supply_groups,id'],
            'key_type'             => ['sometimes', 'required', 'integer', 'between:1,7'],
            'tariff_index'         => ['sometimes', 'required', 'string', 'size:2'],
            'key_revision_number'  => ['sometimes', 'required', 'integer', 'between:0,15'],
            'key_expiry_number'    => ['sometimes', 'required', 'integer', 'between:0,255'],
            'algorithm'            => ['sometimes', 'required', 'in:DKGA02,DKGA04'],
            'encryption_algorithm' => ['sometimes', 'required', 'in:EA07,EA11'],
            'base_date'            => ['sometimes', 'required', 'integer', 'between:1980,2100'],
            'vudk_blob'            => ['sometimes', 'required', 'string', 'regex:/^[0-9a-fA-F]+$/'],
            'is_active'            => ['boolean'],
        ]);
        if (isset($data['vudk_blob'])) {
            $algo = $data['algorithm'] ?? $vendingKey->algorithm;
            $this->assertVudkLength($algo, $data['vudk_blob']);
        }
        $vendingKey->update($data);
        return $vendingKey;
    }

    public function destroy(VendingKey $vendingKey)
    {
        $vendingKey->delete();
        return response()->noContent();
    }

    private function rules(): array
    {
        return [
            'name'                 => ['required', 'string', 'max:120'],
            'supply_group_id'      => ['required', 'integer', 'exists:supply_groups,id'],
            'key_type'             => ['required', 'integer', 'between:1,7'],
            'tariff_index'         => ['required', 'string', 'size:2'],
            'key_revision_number'  => ['required', 'integer', 'between:0,15'],
            'key_expiry_number'    => ['required', 'integer', 'between:0,255'],
            'algorithm'            => ['required', 'in:DKGA02,DKGA04'],
            'encryption_algorithm' => ['required', 'in:EA07,EA11'],
            'base_date'            => ['required', 'integer', 'between:1980,2100'],
            'vudk_blob'            => ['required', 'string', 'regex:/^[0-9a-fA-F]+$/'],
            'is_active'            => ['boolean'],
        ];
    }

    private function assertVudkLength(string $algorithm, string $hex): void
    {
        $expected = $algorithm === 'DKGA02' ? 16 : 40;
        abort_unless(
            strlen($hex) === $expected,
            422,
            "VUDK for {$algorithm} must be exactly {$expected} hex chars."
        );
    }
}
