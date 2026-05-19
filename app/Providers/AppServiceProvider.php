<?php

namespace App\Providers;

use App\Events\CommissionStatusChanged;
use App\Events\DealAmountUpdated;
use App\Events\DealCreated;
use App\Events\DealDeclined;
use App\Events\DealExpired;
use App\Events\DealExtensionApproved;
use App\Events\DealReferrerAssigned;
use App\Events\DealStageMoved;
use App\Events\InviteAcceptedEvent;
use App\Events\ResellerJoined;
use App\Listeners\HandleCommissionStatusChanged;
use App\Listeners\HandleDealAmountUpdated;
use App\Listeners\HandleDealCreated;
use App\Listeners\HandleDealDeclined;
use App\Listeners\HandleDealExpired;
use App\Listeners\HandleDealExtensionApproved;
use App\Listeners\HandleDealReferrerAssigned;
use App\Listeners\HandleDealStageMoved;
use App\Listeners\HandleInviteAccepted;
use App\Listeners\HandleResellerJoined;
use App\Models\Lead;
use App\Models\Task;
use App\Observers\LeadGoogleCalendarObserver;
use App\Observers\TaskGoogleCalendarObserver;
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
        Event::listen(DealStageMoved::class,           HandleDealStageMoved::class);
        Event::listen(DealAmountUpdated::class,        HandleDealAmountUpdated::class);
        Event::listen(DealExtensionApproved::class,    HandleDealExtensionApproved::class);
        Event::listen(DealDeclined::class,             HandleDealDeclined::class);

        // Google Calendar sync observers
        Task::observe(TaskGoogleCalendarObserver::class);
        Lead::observe(LeadGoogleCalendarObserver::class);

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
