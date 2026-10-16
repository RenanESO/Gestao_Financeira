<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Contact;
use App\Models\FinancialAccount;
use App\Models\Notification;
use App\Models\PaymentType;
use App\Models\Receipt;
use App\Models\SettlementReversal;
use App\Models\Title;
use App\Models\TitleInstallment;
use App\Models\TitleSettlement;
use App\Models\TitleSettlementMethod;
use App\Policies\FinancialRecordPolicy;
use App\Policies\TitlePolicy;
use App\Policies\TitleSettlementPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([Category::class, FinancialAccount::class, Contact::class, PaymentType::class,
            TitleInstallment::class, TitleSettlement::class, TitleSettlementMethod::class,
            Receipt::class, Notification::class, SettlementReversal::class] as $model) {
            Gate::policy($model, FinancialRecordPolicy::class);
        }

        Gate::policy(Title::class, TitlePolicy::class);
        Gate::policy(TitleSettlement::class, TitleSettlementPolicy::class);
    }
}
