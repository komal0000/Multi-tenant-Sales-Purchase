# SKILL: API Design — REST Controllers & Resources

## Purpose
This skill guides building clean, consistent REST API controllers for the Ledger System.
Use this when generating controllers, API resources, or route definitions.

---

## Route Structure

```php
// routes/api.php

Route::apiResource('parties', PartyController::class);
Route::apiResource('accounts', AccountController::class);
Route::apiResource('sales', SaleController::class)->except(['update']);
Route::apiResource('purchases', PurchaseController::class)->except(['update']);
Route::apiResource('payments', PaymentController::class)->only(['index', 'store', 'show', 'destroy']);

// Balance endpoints
Route::get('parties/{party}/balance', [PartyController::class, 'balance']);
Route::get('parties/{party}/ledger', [PartyController::class, 'ledger']);
Route::get('accounts/{account}/balance', [AccountController::class, 'balance']);
Route::get('accounts/{account}/ledger', [AccountController::class, 'ledger']);
```

> Note: No `update` on Sales/Purchases by design — delete and recreate instead.

---

## Controller Pattern

```php
class SaleController extends Controller
{
    public function __construct(private SaleService $service) {}

    public function index()
    {
        $sales = Sale::with(['party', 'items'])->latest()->paginate(20);
        return SaleResource::collection($sales);
    }

    public function store(StoreSaleRequest $request)
    {
        $sale = $this->service->create($request->validated());
        return new SaleResource($sale->load(['party', 'items']));
    }

    public function show(Sale $sale)
    {
        return new SaleResource($sale->load(['party', 'items']));
    }

    public function destroy(Sale $sale)
    {
        $this->service->delete($sale);
        return response()->json(['message' => 'Sale deleted successfully.']);
    }
}
```

---

## API Resource Pattern

```php
class SaleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'party'      => new PartyResource($this->whenLoaded('party')),
            'items'      => SaleItemResource::collection($this->whenLoaded('items')),
            'total'      => $this->total,
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}

class SaleItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'particular' => $this->particular,
            'qty'        => $this->qty,
            'price'      => $this->price,
            'total'      => $this->total,
        ];
    }
}
```

---

## Balance Response Format

```php
// PartyController@balance
public function balance(Party $party, LedgerService $ledger)
{
    $balance = $ledger->partyBalance($party->id);
    return response()->json([
        'party_id' => $party->id,
        'name'     => $party->name,
        'balance'  => $balance,
        'status'   => $balance > 0 ? 'receivable' : ($balance < 0 ? 'payable' : 'clear'),
    ]);
}
```

---

## Error Handling

Use a consistent error response shape:
```php
// In Handler or BaseController
return response()->json([
    'message' => 'Validation failed.',
    'errors'  => $validator->errors(),
], 422);
```

---

## Pagination Standard

Always paginate lists:
```php
->paginate(20)
```

Response wrapper (Laravel default with resources):
```json
{
  "data": [...],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "total": 42 }
}
```

---

## No-Update Policy on Bills

Sales and Purchases do NOT have an update endpoint.
If asked to implement update, respond:
> "Bills are immutable. Delete the bill and create a new one."

This keeps ledger history clean and auditable.
