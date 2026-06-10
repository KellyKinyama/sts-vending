<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupplyGroup;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplyGroupController extends Controller
{
    public function index(Request $r)
    {
        return SupplyGroup::query()
            ->when($r->string('q')->trim()->value(), fn ($q, $s) => $q->where('code', 'like', "%$s%")->orWhere('name', 'like', "%$s%"))
            ->orderBy('code')
            ->paginate($r->integer('per_page', 25));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'code'      => ['required', 'string', 'size:6', 'regex:/^\d{6}$/', 'unique:supply_groups,code'],
            'name'      => ['required', 'string', 'max:120'],
            'utility'   => ['nullable', 'string', 'max:120'],
            'region'    => ['nullable', 'string', 'max:120'],
            'is_active' => ['boolean'],
        ]);
        return response()->json(SupplyGroup::create($data), 201);
    }

    public function show(SupplyGroup $supplyGroup)
    {
        return $supplyGroup;
    }

    public function update(Request $r, SupplyGroup $supplyGroup)
    {
        $data = $r->validate([
            'code'      => ['sometimes', 'required', 'string', 'size:6', 'regex:/^\d{6}$/', Rule::unique('supply_groups', 'code')->ignore($supplyGroup->id)],
            'name'      => ['sometimes', 'required', 'string', 'max:120'],
            'utility'   => ['nullable', 'string', 'max:120'],
            'region'    => ['nullable', 'string', 'max:120'],
            'is_active' => ['boolean'],
        ]);
        $supplyGroup->update($data);
        return $supplyGroup;
    }

    public function destroy(SupplyGroup $supplyGroup)
    {
        $supplyGroup->delete();
        return response()->noContent();
    }
}
