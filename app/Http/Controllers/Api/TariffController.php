<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tariff;
use Illuminate\Http\Request;

class TariffController extends Controller
{
    public function index(Request $r)
    {
        return Tariff::query()
            ->with('supplyGroup:id,code,name')
            ->when($r->integer('supply_group_id'), fn ($q, $v) => $q->where('supply_group_id', $v))
            ->orderBy('name')
            ->paginate($r->integer('per_page', 25));
    }

    public function store(Request $r)
    {
        return response()->json(Tariff::create($r->validate($this->rules())), 201);
    }

    public function show(Tariff $tariff)
    {
        return $tariff->load('supplyGroup:id,code,name');
    }

    public function update(Request $r, Tariff $tariff)
    {
        $tariff->update($r->validate($this->rules(true)));
        return $tariff;
    }

    public function destroy(Tariff $tariff)
    {
        $tariff->delete();
        return response()->noContent();
    }

    private function rules(bool $patch = false): array
    {
        $req = $patch ? ['sometimes', 'required'] : ['required'];
        return [
            'supply_group_id'   => [...$req, 'integer', 'exists:supply_groups,id'],
            'name'              => [...$req, 'string', 'max:120'],
            'code'              => ['nullable', 'string', 'max:50'],
            'rate_per_kwh'      => [...$req, 'numeric', 'min:0'],
            'currency'          => [...$req, 'string', 'size:3'],
            'max_power_limit_w' => ['nullable', 'integer', 'min:0'],
            'is_active'         => ['boolean'],
        ];
    }
}
