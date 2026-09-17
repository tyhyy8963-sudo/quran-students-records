<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        \App\Models\Student::class => \App\Policies\StudentPolicy::class,
        \App\Models\Circle::class => \App\Policies\CirclePolicy::class,
        \App\Models\RecitationLog::class => \App\Policies\RecitationLogPolicy::class,
        \App\Models\PoemRecitationLog::class => \App\Policies\PoemRecitationLogPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        //
    }
}
