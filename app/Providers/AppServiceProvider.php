<?php

namespace App\Providers;

use App\Helpers\DateHelper;
use App\Models\Account;
use App\Models\BillLineItem;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\Party;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Observers\BillLineItemObserver;
use App\Policies\AccountPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\EmployeeSalaryPolicy;
use App\Policies\PartyPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PurchasePolicy;
use App\Policies\SalePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        BillLineItem::observe(BillLineItemObserver::class);

        Gate::policy(Account::class, AccountPolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(EmployeeSalary::class, EmployeeSalaryPolicy::class);
        Gate::policy(Party::class, PartyPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Purchase::class, PurchasePolicy::class);
        Gate::policy(Sale::class, SalePolicy::class);

        if ($this->app->environment('production') && config('app.timezone') !== 'Asia/Kathmandu') {
            throw new RuntimeException('APP_TIMEZONE must be set to Asia/Kathmandu in production.');
        }

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip().'|'.(string) $request->input('phone'));
        });

        View::share('bsDateConfig', [
            'years' => DateHelper::getSupportedYears(),
            'months' => DateHelper::getMonthOptions(),
            'monthMap' => DateHelper::getBsMonthMap(),
            'today' => DateHelper::getCurrentBS(),
            'startEnglishDate' => DateHelper::START_ENGLISH_DATE,
            'startNepaliYear' => DateHelper::MIN_YEAR_BS,
            'weekdays' => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
        ]);
    }
}
