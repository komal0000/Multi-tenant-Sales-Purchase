<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeSalaryController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');

    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
});

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/settings', [SettingsController::class, 'index'])
        ->middleware('admin')
        ->name('settings.index');
    Route::patch('/settings/payroll', [SettingsController::class, 'updatePayroll'])
        ->middleware('admin')
        ->name('settings.payroll.update');
    Route::post('/settings/users', [SettingsController::class, 'storeUser'])
        ->middleware('admin')
        ->name('settings.users.store');
    Route::patch('/settings/users/{user}', [SettingsController::class, 'updateUser'])
        ->middleware('admin')
        ->name('settings.users.update');
    Route::delete('/settings/users/{user}', [SettingsController::class, 'destroyUser'])
        ->middleware('admin')
        ->name('settings.users.destroy');

    Route::resource('parties', PartyController::class)->only(['index', 'store', 'show']);
    Route::delete('parties/{party}', [PartyController::class, 'destroy'])
        ->middleware('admin')
        ->name('parties.destroy');
    Route::get('parties/{party}/ledger', [PartyController::class, 'ledgerStatement'])->name('parties.ledger');
    Route::patch('parties/{party}/opening-balance', [PartyController::class, 'updateOpeningBalance'])->name('parties.opening-balance.update');

    Route::resource('accounts', AccountController::class)->only(['index', 'create', 'store', 'show']);
    Route::get('accounts/{account}/ledger', [AccountController::class, 'ledgerStatement'])->name('accounts.ledger');
    Route::patch('accounts/{account}/opening-balance', [AccountController::class, 'updateOpeningBalance'])->name('accounts.opening-balance.update');

    Route::resource('items', ItemController::class)->only(['index', 'store', 'edit', 'update', 'destroy']);
    Route::resource('expense-categories', ExpenseCategoryController::class)->only(['index', 'store', 'edit', 'update', 'destroy']);
    Route::get('expense-categories/tree', [ExpenseCategoryController::class, 'getCategoryTree'])
        ->name('expense-categories.tree');

    Route::resource('employees', EmployeeController::class)->only(['index', 'create', 'store', 'show']);

    Route::resource('sales', SaleController::class)->only(['index', 'create', 'store', 'show']);
    Route::delete('sales/{sale}', [SaleController::class, 'destroy'])
        ->middleware('admin')
        ->name('sales.destroy');

    Route::resource('purchases', PurchaseController::class)->only(['index', 'create', 'store', 'show']);
    Route::delete('purchases/{purchase}', [PurchaseController::class, 'destroy'])
        ->middleware('admin')
        ->name('purchases.destroy');

    Route::get('payments/search-sales', [PaymentController::class, 'searchSales'])
        ->name('payments.search-sales');
    Route::get('payments/search-purchases', [PaymentController::class, 'searchPurchases'])
        ->name('payments.search-purchases');

    Route::resource('payments', PaymentController::class)->only(['index', 'create', 'store', 'show']);
    Route::delete('payments/{payment}', [PaymentController::class, 'destroy'])
        ->middleware('admin')
        ->name('payments.destroy');
    Route::resource('employee-salaries', EmployeeSalaryController::class)->only(['index', 'create', 'store', 'show']);
    Route::get('employee-salaries/{employeeSalary}/print', [EmployeeSalaryController::class, 'print'])->name('employee-salaries.print');

    Route::get('reports/sales', [ReportController::class, 'salesReport'])->name('reports.sales');
    Route::get('reports/purchases', [ReportController::class, 'purchaseReport'])->name('reports.purchases');
    Route::get('reports/cashbook', [ReportController::class, 'cashbook'])->name('reports.cashbook');
    Route::get('reports/profit-loss', [ReportController::class, 'profitLoss'])->name('reports.profit-loss');
});
