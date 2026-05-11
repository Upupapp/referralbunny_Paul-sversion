<?php

namespace App\Providers;

use App\Events\CommissionStatusChanged;
use App\Events\DealCreated;
use App\Events\DealExpired;
use App\Events\DealReferrerAssigned;
use App\Events\InviteAcceptedEvent;
use App\Events\ResellerJoined;
use App\Listeners\HandleCommissionStatusChanged;
use App\Listeners\HandleDealCreated;
use App\Listeners\HandleDealExpired;
use App\Listeners\HandleDealReferrerAssigned;
use App\Listeners\HandleInviteAccepted;
use App\Listeners\HandleResellerJoined;
use App\Services\PermissionService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PermissionService::class);
    }

    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
            request()->server->set('HTTPS', 'on');
        }

        // Event → email + in-app notification bindings
        Event::listen(DealCreated::class,              HandleDealCreated::class);
        Event::listen(DealReferrerAssigned::class,     HandleDealReferrerAssigned::class);
        Event::listen(ResellerJoined::class,           HandleResellerJoined::class);
        Event::listen(CommissionStatusChanged::class,  HandleCommissionStatusChanged::class);
        Event::listen(DealExpired::class,              HandleDealExpired::class);
        Event::listen(InviteAcceptedEvent::class,      HandleInviteAccepted::class);

        // View composer — inject partner unread count into all partner views
        View::composer(['partner.*', 'layouts.partner'], function ($view) {
            if ($partner = auth('partner')->user()) {
                $unread = \App\Models\PartnerThread::where('partner_id', $partner->id)->sum('partner_unread');
                $view->with('partnerUnread', (int) $unread);
            } else {
                $view->with('partnerUnread', 0);
            }
        });
    }
}
