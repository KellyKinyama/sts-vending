<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MeterController extends Controller
{
    public function index(Request $r)
    {
        return Meter::query()
            ->with(['supplyGroup:id,code,name', 'vendingKey:id,name', 'tariff:id,name,currency', 'customer:id,account_number,name'])
            ->when($r->string('q')->trim()->value(), fn ($q, $s) => $q->where(fn ($q) =>
                $q->where('pan', 'like', "%$s%")->orWhere('iain', 'like', "%$s%")
            ))
            ->when($r->integer('supply_group_id'), fn ($q, $v) => $q->where('supply_group_id', $v))
            ->orderBy('pan')
            ->paginate($r->integer('per_page', 25));
    }

    public function store(Request $r)
    {
        $data = $r->validate($this->rules());
        return response()->json(Meter::create($data), 201);
    }

    public function show(Meter $meter)
    {
        return $meter->load(['supplyGroup', 'vendingKey', 'tariff', 'customer']);
    }

    public function update(Request $r, Meter $meter)
    {
        $data = $r->validate($this->rules(true, $meter->id));
        $meter->update($data);
        return $meter;
    }

    public function destroy(Meter $meter)
    {
        $meter->delete();
        return response()->noContent();
    }

    private function rules(bool $patch = false, ?int $ignoreId = null): array
    {
        $req = $patch ? ['sometimes', 'required'] : ['required'];
        return [
            'pan'                    => [...$req, 'string', 'size:18', 'regex:/^\d{18}$/', Rule::unique('meters', 'pan')->ignore($ignoreId)],
            'iin'                    => [...$req, 'string', 'size:6', 'regex:/^\d{6}$/'],
            'iain'                   => [...$req, 'string', 'regex:/^\d{11,13}$/'],
            'manufacturer_code'      => ['nullable', 'string', 'max:4'],
            'decoder_serial_number'  => ['nullable', 'string', 'max:8'],
            'supply_group_id'        => [...$req, 'integer', 'exists:supply_groups,id'],
            'vending_key_id'         => ['nullable', 'integer', 'exists:vending_keys,id'],
            'tariff_id'              => ['nullable', 'integer', 'exists:tariffs,id'],
            'customer_id'            => ['nullable', 'integer', 'exists:customers,id'],
            'location'               => ['nullable', 'string', 'max:200'],
            'installed_at'           => ['nullable', 'date'],
            'is_active'              => ['boolean'],
        ];
    }
}
