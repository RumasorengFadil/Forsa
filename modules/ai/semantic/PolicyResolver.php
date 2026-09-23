<?php

declare(strict_types=1);

namespace Forsa\Ai\Semantic;

/**
 * PRD §16 — RBAC data scope, resolved BEFORE the query runs (never as an
 * afterthought filter). `config/role_access.php` only defines `SUPER_ADMIN`
 * today (confirmed against the live config), so every current user sees
 * every SH/AP — this mirrors exactly what the dashboard itself allows,
 * nothing more permissive. The moment a scoped role is added there
 * (PRD's "future role A/B"), this is the one place to teach the AI about it;
 * QueryPlanner already calls this unconditionally so no query path can skip it.
 */
final class PolicyResolver
{
    /**
     * @return array{allowed_company_ids: ?array<int, int>} null = no restriction (sees all SH/AP)
     */
    public function scopeFor(array $user): array
    {
        $roles = $user['roles'] ?? [];
        if (in_array('SUPER_ADMIN', $roles, true)) {
            return ['allowed_company_ids' => null];
        }

        // No non-SUPER_ADMIN role exists in config/role_access.php yet
        // (PRD's "future role A/B") — default to zero access rather than
        // guessing a scope, so a future role is safe-by-default until its
        // real scope is defined here.
        return ['allowed_company_ids' => []];
    }
}
