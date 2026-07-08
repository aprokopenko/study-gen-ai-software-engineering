<?php

declare(strict_types=1);

namespace App\Enums\Ticket;

enum Category: string
{
    case AccountAccess = 'account_access';
    case TechnicalIssue = 'technical_issue';
    case BillingQuestion = 'billing_question';
    case FeatureRequest = 'feature_request';
    case BugReport = 'bug_report';
    case Other = 'other';
}
