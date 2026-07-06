<?php

namespace App\Support;

use Illuminate\Support\Facades\Session;

class BreadcrumbStack
{
    private const KEY = 'breadcrumbs';
    private const MAX = 5;

    public static function push(string $label, string $url): void
    {
        $stack = Session::get(self::KEY, []);

        if (! empty($stack) && end($stack)['url'] === $url) {
            return;
        }

        $existingIdx = collect($stack)->search(fn ($crumb) => $crumb['url'] === $url);
        if ($existingIdx !== false) {
            $stack = array_slice($stack, 0, $existingIdx);
        }

        $stack[] = ['label' => $label, 'url' => $url];

        if (count($stack) > self::MAX) {
            $stack = array_slice($stack, -self::MAX);
        }

        Session::put(self::KEY, $stack);
    }

    public static function get(): array
    {
        return Session::get(self::KEY, []);
    }

    public static function reset(): void
    {
        Session::forget(self::KEY);
    }
}
