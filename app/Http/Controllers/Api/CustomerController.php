<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $r)
    {
        return Customer::query()
            ->when($r->string('q')->trim()->value(), fn ($q, $s) => $q->where(fn ($q) =>
                $q->where('account_number', 'like', "%$s%")
                  ->orWhere('name', 'like', "%$s%")
                  ->orWhere('phone', 'like', "%$s%")
                  ->orWhere('email', 'like', "%$s%")
            ))
            ->orderBy('account_number')
            ->paginate($r->integer('per_page', 25));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'account_number' => ['required', 'string', 'max:60', 'unique:customers,account_number'],
            'name'           => ['required', 'string', 'max:120'],
            'phone'          => ['nullable', 'string', 'max:30'],
            'email'          => ['nullable', 'email', 'max:120'],
            'address'        => ['nullable', 'string', 'max:200'],
            'city'           => ['nullable', 'string', 'max:80'],
            'is_active'      => ['boolean'],
        ]);
        return response()->json(Customer::create($data), 201);
    }

    public function show(Customer $customer)
    {
        return $customer;
    }

    public function update(Request $r, Customer $customer)
    {
        $data = $r->validate([
            'account_number' => ['sometimes', 'required', 'string', 'max:60', Rule::unique('customers', 'account_number')->ignore($customer->id)],
            'name'           => ['sometimes', 'required', 'string', 'max:120'],
            'phone'          => ['nullable', 'string', 'max:30'],
            'email'          => ['nullable', 'email', 'max:120'],
            'address'        => ['nullable', 'string', 'max:200'],
            'city'           => ['nullable', 'string', 'max:80'],
            'is_active'      => ['boolean'],
        ]);
        $customer->update($data);
        return $customer;
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();
        return response()->noContent();
    }
}
