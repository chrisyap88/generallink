<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

// -------------------------------------------------------
// NEW 3 Aug 2026 — AI Guided Navigation, part 1/4.
// Loads every config/ai_navigation/*.php manifest (plain declarative
// data — see docs/ai-guided-navigation-design.md) and is the ONLY place
// that decides whether a page/element/workflow the AI wants to reference
// actually, genuinely exists and is allowed for the caller's role.
//
// Nothing here is AI-authored or dynamic — a developer writes a manifest
// file, this service just loads/filters/validates it. Adding a new module
// later means adding a new manifest file here, never touching this class.
// -------------------------------------------------------
class NavigationRegistryService
{
    /** @var array|null cached merged manifest data for this request */
    private ?array $manifests = null;

    private function loadAll(): array
    {
        if ($this->manifests !== null) {
            return $this->manifests;
        }

        $merged = ['pages' => [], 'workflows' => []];
        $dir = config_path('ai_navigation');

        if (!File::isDirectory($dir)) {
            $this->manifests = $merged;
            return $merged;
        }

        foreach (File::files($dir) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $data = require $file->getPathname();
            if (!is_array($data)) {
                continue;
            }

            foreach (($data['pages'] ?? []) as $routeName => $page) {
                if (!Route::has($routeName)) {
                    // A manifest referencing a route that doesn't exist is a
                    // developer mistake (typo, renamed route) — fail loudly
                    // in the log rather than silently letting the AI offer
                    // a broken navigation step.
                    Log::warning("[AiNav] Manifest '{$data['module']}' references unknown route '{$routeName}' — skipped.");
                    continue;
                }
                $merged['pages'][$routeName] = $page;
            }

            foreach (($data['workflows'] ?? []) as $goal => $workflow) {
                $merged['workflows'][$goal] = $workflow;
            }
        }

        $this->manifests = $merged;
        return $merged;
    }

    /**
     * Every page + element visible to this role, across all modules.
     * Used to build the (small, filtered) context given to the AI for
     * ad-hoc planning — never the whole app, never unfiltered by role.
     */
    public function pagesForRole(string $role): array
    {
        $all = $this->loadAll();
        $role = strtolower($role);

        return collect($all['pages'])
            ->filter(function ($page) use ($role) {
                $roles = array_map('strtolower', $page['roles'] ?? []);
                return in_array($role, $roles, true) || in_array('*', $roles, true);
            })
            ->all();
    }

    /**
     * A single authored workflow, if one exists for this goal AND the
     * caller's role is permitted to run it. Returns null otherwise — the
     * caller (AiGuidanceService) should treat null as "no authored
     * workflow, consider ad-hoc mode or decline."
     */
    public function workflowFor(string $goal, string $role): ?array
    {
        $all = $this->loadAll();
        $workflow = $all['workflows'][$goal] ?? null;
        if (!$workflow) {
            return null;
        }

        $roles = array_map('strtolower', $workflow['roles'] ?? []);
        if (!in_array(strtolower($role), $roles, true) && !in_array('*', $roles, true)) {
            return null;
        }

        return $workflow;
    }

    /** All authored workflow keys + labels visible to a role (for listing/debugging). */
    public function workflowsForRole(string $role): array
    {
        $all = $this->loadAll();
        $role = strtolower($role);

        return collect($all['workflows'])
            ->filter(function ($wf) use ($role) {
                $roles = array_map('strtolower', $wf['roles'] ?? []);
                return in_array($role, $roles, true) || in_array('*', $roles, true);
            })
            ->all();
    }

    /**
     * Re-validates ONE proposed action against the registry before it is
     * ever allowed to reach the browser. This is the defense-in-depth gate
     * described in the design doc §5.2 — even a fully authored, already-
     * trusted workflow step is checked here, so there is exactly one place
     * in the whole system that can let an invalid action through.
     *
     * @param array $action  e.g. ['action'=>'highlight_element','page'=>'auth.register','element'=>'full-name-input']
     */
    public function validateAction(array $action, string $role): bool
    {
        $page = $action['page'] ?? null;
        $element = $action['element'] ?? null;

        if (!$page) {
            return false;
        }

        $pagesForRole = $this->pagesForRole($role);
        if (!isset($pagesForRole[$page])) {
            Log::warning('[AiNav] validateAction rejected — page not registered for role', ['page' => $page, 'role' => $role]);
            return false;
        }

        if ($element !== null && !isset($pagesForRole[$page]['elements'][$element])) {
            Log::warning('[AiNav] validateAction rejected — element not registered on page', ['page' => $page, 'element' => $element]);
            return false;
        }

        return true;
    }
}
