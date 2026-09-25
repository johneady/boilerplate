<?php

namespace App\Auth;

/**
 * Every discrete thing a role may be granted.
 *
 * A permission is the unit policies and gates check, so a new capability is
 * added here and granted to roles in Role::permissions() -- not checked as a
 * role name at the call site. Checking roles directly is what makes an
 * authorization layer impossible to change later: `if ($user->isAdmin())`
 * scattered through the code has to be found and rewritten the day a third
 * role appears, while `$user->can(Permission::DeleteUsers)` keeps working.
 *
 * The backing values are namespaced strings ("users.delete") so they read
 * clearly in a Gate::before dump or a database column, and so an unrelated
 * permission cannot collide with them.
 */
enum Permission: string
{
    case ViewUsers = 'users.view';

    case CreateUsers = 'users.create';

    case UpdateUsers = 'users.update';

    case DeleteUsers = 'users.delete';

    case ViewPages = 'pages.view';

    case CreatePages = 'pages.create';

    case UpdatePages = 'pages.update';

    case DeletePages = 'pages.delete';

    case ViewContactSubmissions = 'contact-submissions.view';

    case UpdateContactSubmissions = 'contact-submissions.update';

    case DeleteContactSubmissions = 'contact-submissions.delete';

    case ManageSettings = 'settings.manage';

    case ViewLogs = 'logs.view';

    case ViewMedia = 'media.view';

    case DeleteMedia = 'media.delete';

    case ViewAuditLog = 'audit-log.view';

    case AccessAdminPanel = 'admin-panel.access';

    case ViewPayments = 'payments.view';

    case RefundPayments = 'payments.refund';

    /** Capturing and voiding authorized payments (holds). */
    case CapturePayments = 'payments.capture';

    case RecordManualPayments = 'payments.record-manual';

    case ManagePaymentLinks = 'payment-links.manage';

    /** The Payments settings tab, gateway credentials, tax rates and webhook events. */
    case ManagePaymentSettings = 'payment-settings.manage';

    /** Subscription plans and their prices, and syncing them to the gateways. */
    case ManagePlans = 'plans.manage';

    /** Cancelling and resuming customers' subscriptions. Viewing them is ViewPayments. */
    case ManageSubscriptions = 'subscriptions.manage';

    /**
     * The label shown wherever a permission is listed for a human.
     */
    public function label(): string
    {
        return match ($this) {
            self::ViewUsers => 'View users',
            self::CreateUsers => 'Create users',
            self::UpdateUsers => 'Update users',
            self::DeleteUsers => 'Delete users',
            self::ViewPages => 'View public content pages',
            self::CreatePages => 'Create public content pages',
            self::UpdatePages => 'Update public content pages',
            self::DeletePages => 'Delete public content pages',
            self::ViewContactSubmissions => 'View contact form submissions',
            self::UpdateContactSubmissions => 'Mark contact form submissions handled',
            self::DeleteContactSubmissions => 'Delete contact form submissions',
            self::ManageSettings => 'Manage application settings',
            self::ViewLogs => 'View application logs',
            self::ViewMedia => 'View uploaded files',
            self::DeleteMedia => 'Delete uploaded files',
            self::ViewAuditLog => 'View the audit log',
            self::AccessAdminPanel => 'Access the admin panel',
            self::ViewPayments => 'View payments and refunds',
            self::RefundPayments => 'Refund payments',
            self::CapturePayments => 'Capture or void held payments',
            self::RecordManualPayments => 'Record payments received outside the site',
            self::ManagePaymentLinks => 'Create and edit payment links',
            self::ManagePaymentSettings => 'Manage payment settings, credentials and tax rates',
            self::ManagePlans => 'Create and edit subscription plans',
            self::ManageSubscriptions => 'Cancel and resume customers\' subscriptions',
        };
    }
}
