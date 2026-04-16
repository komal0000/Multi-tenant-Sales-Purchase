<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\Party;
use App\Services\PartyCacheService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    public function __construct(private readonly PartyCacheService $partyCache) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Employee::class);

        $filters = $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
        ]);

        $employees = Employee::query()
            ->select('employees.*')
            ->join('parties', 'parties.id', '=', 'employees.party_id')
            ->with('party')
            ->when($filters['keyword'] ?? null, function ($query, $keyword) {
                $term = '%'.trim((string) $keyword).'%';

                $query->where(function ($subQuery) use ($term) {
                    $subQuery
                        ->whereHas('party', function ($partyQuery) use ($term) {
                            $partyQuery
                                ->where('name', 'like', $term)
                                ->orWhere('phone', 'like', $term);
                        });
                });
            })
            ->orderBy('parties.name')
            ->paginate(20)
            ->withQueryString();

        return view('employees.index', [
            'employees' => $employees,
            'filters' => [
                'keyword' => $filters['keyword'] ?? null,
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Employee::class);

        return view('employees.create-direct');
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $this->authorize('create', Employee::class);

        $validated = $request->validated();

        $employee = DB::transaction(function () use ($validated): Employee {
            $party = Party::query()->create([
                'name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'opening_balance' => 0,
                'opening_balance_side' => 'dr',
            ]);

            return Employee::query()->create([
                'party_id' => $party->id,
                'salary' => $validated['salary'],
            ]);
        });

        $this->partyCache->refreshAll();

        return redirect()
            ->route('employees.show', $employee)
            ->with('success', 'Employee created successfully.');
    }

    public function show(Employee $employee): View
    {
        $this->authorize('view', $employee);

        return view('employees.show', [
            'employee' => $employee->load('party'),
        ]);
    }

    public function edit(Employee $employee): View
    {
        $this->authorize('update', $employee);

        return view('employees.edit', [
            'employee' => $employee->load('party'),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        $validated = $request->validated();

        DB::transaction(function () use ($employee, $validated): void {
            $employee->party()->update([
                'name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
            ]);

            $employee->update([
                'salary' => $validated['salary'],
            ]);
        });

        $this->partyCache->refreshAll();

        return redirect()
            ->route('employees.show', $employee)
            ->with('success', 'Employee updated successfully.');
    }
}
