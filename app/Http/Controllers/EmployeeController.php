<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\PartyCacheService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
                $term = '%' . trim((string) $keyword) . '%';

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

        return view('employees.create', [
            'parties' => $this->partyCache->unassignedForEmployees(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Employee::class);

        $tenantId = (int) ($request->user()?->tenant_id ?? 0);

        $validated = $request->validate([
            'party_id' => [
                'required',
                'integer',
                Rule::exists('parties', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
                Rule::unique('employees', 'party_id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'salary' => ['required', 'numeric', 'min:0'],
        ]);

        $employee = Employee::query()->create($validated);

        $this->partyCache->refreshUnassigned();

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
}
