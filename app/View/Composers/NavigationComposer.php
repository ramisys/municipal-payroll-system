<?php

namespace App\View\Composers;

use App\Services\AuthorizationService;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\View\View;

// The application shell renders one sidebar for every authenticated screen, so
// the permission flags it needs have to be available on every screen. Binding
// them here rather than adding six `can*` keys to twelve controllers' view data
// keeps the controllers about their own use case.
//
// This decides visibility only. AC-6.2.2 requires that a function absent from a
// role still be refused when invoked directly, and that refusal remains where it
// already is — AuthorizationService, called from inside each controller action.
// Hiding a link here is a courtesy to the user, never the enforcement.
class NavigationComposer
{
    public function __construct(
        private readonly AuthorizationService $authorizationService,
        private readonly Guard $auth,
    ) {}

    public function compose(View $view): void
    {
        $user = $this->auth->user();

        if ($user === null) {
            return;
        }

        $view->with([
            'navUser' => $user,
            'navCan' => [
                'employees' => $this->authorizationService->can($user, 'employees.manage'),
                'attendance' => $this->authorizationService->can($user, 'attendance.import'),
                'payrollRuns' => $this->authorizationService->can($user, 'payroll_run.create_import'),
                'payrollRecords' => $this->authorizationService->can($user, 'payroll_records.search'),
                'users' => $this->authorizationService->can($user, 'users.manage'),
                'auditLog' => $this->authorizationService->can($user, 'audit_log.view'),
                'organization' => $this->authorizationService->can($user, 'organization.manage'),
                'referenceData' => $this->authorizationService->can($user, 'reference_data.manage'),
            ],
        ]);
    }
}
