<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The dashboard navigation map.
 *
 * Defined once and read in three places: routes/web.php registers one route per
 * leaf, components/dashboard/nav.blade.php renders the sidebar from it, and each
 * section page takes its heading and description from it. Adding a section means
 * adding an entry here plus a matching block in lang/ro/dashboard.php — nothing
 * else.
 *
 * Ported from Hey Roger's App\Support\DashboardNav, minus the role gating: that
 * project hides sections a user's role cannot open, and this one has no roles
 * yet.
 *
 * @phpstan-type DashboardLeaf array{key: string, path: string, route: string, icon?: string}
 * @phpstan-type DashboardItem array{type: 'link'|'group', key: string, icon: string, path?: string, route?: string, children?: list<DashboardLeaf>}
 */
final class DashboardNav
{
    /**
     * The sidebar, top to bottom. `link` items are a section of their own;
     * `group` items collapse a set of related sections behind one label.
     *
     * @return list<DashboardItem>
     */
    public static function items(): array
    {
        return [
            [
                'type' => 'link',
                'key' => 'overview',
                'icon' => 'grid',
                'path' => '/',
                'route' => 'dashboard.overview',
            ],
            [
                'type' => 'link',
                'key' => 'calculator',
                'icon' => 'calculator',
                'path' => 'calculator',
                'route' => 'dashboard.calculator',
            ],
            [
                'type' => 'link',
                'key' => 'routes',
                'icon' => 'route',
                'path' => 'rute',
                'route' => 'dashboard.routes',
            ],
            [
                'type' => 'link',
                'key' => 'report',
                'icon' => 'document',
                'path' => 'raport',
                'route' => 'dashboard.report',
            ],
            [
                'type' => 'group',
                'key' => 'fleet',
                'icon' => 'truck',
                'children' => [
                    [
                        'key' => 'vehicles',
                        'path' => 'flota/vehicule',
                        'route' => 'dashboard.fleet.vehicles',
                    ],
                    [
                        'key' => 'defaults',
                        'path' => 'flota/valori-implicite',
                        'route' => 'dashboard.fleet.defaults',
                    ],
                ],
            ],
        ];
    }

    /**
     * Whether the current request is inside this item. A group is current when
     * any of its children is.
     *
     * @param  array<string, mixed>  $item
     */
    public static function isCurrent(array $item): bool
    {
        if (($item['type'] ?? 'link') === 'group') {
            foreach ($item['children'] ?? [] as $child) {
                if (self::isCurrent($child)) {
                    return true;
                }
            }

            return false;
        }

        $route = $item['route'] ?? null;

        return $route !== null && request()->routeIs($route, $route.'.*');
    }

    /**
     * Sidebar label for a nav key.
     */
    public static function label(string $key): string
    {
        return (string) __("dashboard.nav.{$key}");
    }

    /**
     * Page heading for a section key. Falls back to the sidebar label so a
     * half-translated section still renders something sensible.
     */
    public static function heading(string $key): string
    {
        $heading = __("dashboard.sections.{$key}.title");

        return is_string($heading) && $heading !== "dashboard.sections.{$key}.title"
            ? $heading
            : self::label($key);
    }

    /**
     * One-line description under the page heading.
     */
    public static function description(string $key): string
    {
        $description = __("dashboard.sections.{$key}.description");

        return is_string($description) && $description !== "dashboard.sections.{$key}.description"
            ? $description
            : '';
    }
}
