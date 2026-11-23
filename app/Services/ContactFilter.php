<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class ContactFilter
{
    private function __construct()
    {
    }

    /**
     * @return array{period: string, start_date: string|null, end_date: string|null}
     */
    public static function resolveDateFilter(Request $request): array
    {
        $validated = $request->validate([
            'period' => ['nullable', 'in:all,7d,30d,custom'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        return [
            'period' => (string) ($validated['period'] ?? 'all'),
            'start_date' => isset($validated['start_date']) ? (string) $validated['start_date'] : null,
            'end_date' => isset($validated['end_date']) ? (string) $validated['end_date'] : null,
        ];
    }

    /**
     * @return array{q: string|null, city: string|null, status: string, per_page: int}
     */
    public static function resolveUiFilters(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:all,contacted,uncontacted'],
            'per_page' => ['nullable', 'in:10,20,50,100'],
        ]);

        return [
            'q' => isset($validated['q']) ? trim((string) $validated['q']) : null,
            'city' => isset($validated['city']) && trim((string) $validated['city']) !== '' ? trim((string) $validated['city']) : null,
            'status' => (string) ($validated['status'] ?? 'all'),
            'per_page' => (int) ($validated['per_page'] ?? 20),
        ];
    }

    public static function applyDateFilter(Builder $query, array $filters): void
    {
        $period = $filters['period'] ?? 'all';

        if ($period === '7d') {
            $query->where('created_at', '>=', now()->subDays(6)->startOfDay());
            return;
        }

        if ($period === '30d') {
            $query->where('created_at', '>=', now()->subDays(29)->startOfDay());
            return;
        }

        if ($period === 'custom') {
            $startDate = $filters['start_date'] ? Carbon::parse($filters['start_date'])->startOfDay() : null;
            $endDate = $filters['end_date'] ? Carbon::parse($filters['end_date'])->endOfDay() : null;

            if ($startDate && $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
                return;
            }

            if ($startDate) {
                $query->where('created_at', '>=', $startDate);
            }

            if ($endDate) {
                $query->where('created_at', '<=', $endDate);
            }
        }
    }

    /**
     * @param bool $includeLeaderSearch Whether to search by leader name (omit when already scoped to one leader)
     */
    public static function applyListFilters(Builder $query, array $uiFilters, bool $includeLeaderSearch = true): void
    {
        if (! empty($uiFilters['q'])) {
            $keyword = $uiFilters['q'];
            $query->where(function (Builder $builder) use ($keyword, $includeLeaderSearch): void {
                $builder
                    ->where('contact_name', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%")
                    ->orWhere('city', 'like', "%{$keyword}%")
                    ->orWhereHas('subLeader', fn (Builder $q) => $q->where('name', 'like', "%{$keyword}%"));

                if ($includeLeaderSearch) {
                    $builder->orWhereHas('leader', fn (Builder $q) => $q->where('name', 'like', "%{$keyword}%"));
                }
            });
        }

        if (! empty($uiFilters['city'])) {
            $query->where('city', $uiFilters['city']);
        }

        if (($uiFilters['status'] ?? 'all') === 'contacted') {
            $query->where('is_contacted', true);
        }

        if (($uiFilters['status'] ?? 'all') === 'uncontacted') {
            $query->where('is_contacted', false);
        }
    }
}
