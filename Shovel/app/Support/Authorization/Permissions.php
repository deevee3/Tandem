<?php

namespace App\Support\Authorization;

final class Permissions
{
    public const DASHBOARD_VIEW = 'dashboard.view';
    public const USERS_MANAGE = 'users.manage';
    public const QUEUES_MANAGE = 'queues.manage';
    public const CONVERSATIONS_REVIEW = 'conversations.review';
    public const GUARDRAILS_ADJUST = 'guardrails.adjust';
    public const API_KEYS_MANAGE = 'api-keys.manage';
    public const WEBHOOKS_MANAGE = 'webhooks.manage';
    public const AUDIT_LOGS_VIEW = 'audit-logs.view';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::DASHBOARD_VIEW,
            self::USERS_MANAGE,
            self::QUEUES_MANAGE,
            self::CONVERSATIONS_REVIEW,
            self::GUARDRAILS_ADJUST,
            self::API_KEYS_MANAGE,
            self::WEBHOOKS_MANAGE,
            self::AUDIT_LOGS_VIEW,
        ];
    }
}
