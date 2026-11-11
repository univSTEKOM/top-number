<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\ContactChannelHistory;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Expression;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardRecapService
{
    public function getDashboardData(User $user, Request $request): array
    {
        if ($user->role === User::ROLE_SUPERADMIN) {
            return $this->getSuperAdminDashboardData($request);
        }

        if ($user->role === User::ROLE_LEADER) {
            return $this->getLeaderDashboardData($user);
        }

        return $this->getSubLeaderDashboardData($user);
    }

    private function getSuperAdminDashboardData(Request $request): array
    {
        $stats = $this->buildSuperAdminStats();
        $meta = $this->buildSuperAdminMeta();

        $selectedMonth = $this->getSelectedMonth($request);
        [$year, $month] = $this->parseYearAndMonth($selectedMonth);

        $leaderChartDate = $this->getLeaderChartDate($request);
        $subLeaderChartDate = $this->getSubLeaderChartDate($request);

        $leaderComparisonData = $this->getLeaderComparisonDataForDay($leaderChartDate);
        $topmatchComparisonData = $this->getTopmatchLeaderComparisonDataForDay($leaderChartDate);
        $kerjaMalamComparisonData = $this->getKerjaMalamLeaderComparisonDataForDay($leaderChartDate);
        $subLeaderComparisonData = $this->getSubLeaderComparisonDataForDay($subLeaderChartDate);

        $teamComparisonData = $this->getTeamComparisonData($year, $month);
        $monthlyTotalsData = $this->buildMonthlyTotalsData($year, $month);

        return array_merge($this->emptyPayload(), [
            'stats' => $stats,
            'meta' => $meta,
            'leaderComparisonData' => $leaderComparisonData,
            'topmatchComparisonData' => $topmatchComparisonData,
            'kerjaMalamComparisonData' => $kerjaMalamComparisonData,
            'subLeaderComparisonData' => $subLeaderComparisonData,
            'teamComparisonData' => $teamComparisonData,
            'monthlyTotalsData' => $monthlyTotalsData,
            'selectedMonth' => $selectedMonth,
            'leaderChartDate' => $leaderChartDate,
            'subLeaderChartDate' => $subLeaderChartDate,
        ]);
    }

    private function getLeaderDashboardData(User $user): array
    {
        $channel = $user->marketing_channel ?? User::MARKETING_CHANNEL_TOPLOKER;
        $dailyTarget = $user->getDailyTarget();

        if ($user->isSpecialChannel()) {
            return $this->getSpecialChannelLeaderDashboardData($user, $channel, $dailyTarget);
        }

        // --- Toploker (existing behaviour) ---
        $teamContacts = $this->getAllContactsQuery()
            ->when(
                $user->team_id,
                fn ($query) => $query->where('team_id', $user->team_id),
                fn ($query) => $query->whereRaw('1 = 0')
            );

        $contacts = (clone $teamContacts)->count();
        $contacted = (clone $teamContacts)->where('is_contacted', true)->count();
        $contactsThisMonth = (clone $teamContacts)->where('period_key', Contact::activePeriodKey())->count();

        $personalHandled = (clone $teamContacts)
            ->where('is_contacted', true)
            ->where('status_updated_by', $user->id)
            ->count();

        $assistantSubLeaders = User::query()
            ->where('role', User::ROLE_SUB_LEADER)
            ->when(
                $user->team_id,
                fn ($query) => $query->whereHas('teams', fn ($q) => $q->where('teams.id', $user->team_id)),
                fn ($query) => $query->whereRaw('1 = 0')
            )
            ->withCount(['contactsEntered as contacts_entered_count' => fn ($q) => $q->whereDate('created_at', now()->toDateString())])
            ->orderByDesc('contacts_entered_count')
            ->get();

        $assistantChartData = $assistantSubLeaders->map(function ($subLeader) {
            return [
                'label' => $subLeader->name,
                'count' => $subLeader->contacts_entered_count,
            ];
        })->all();

        $mainTargetData = $this->buildMainTargetData($personalHandled, $dailyTarget);
        $stats = $this->buildLeaderStats($contacts, $contacted, $assistantSubLeaders->count(), $mainTargetData['progress'], $dailyTarget, $contactsThisMonth);

        $dateLabels = $this->buildDateLabels(7);
        $mainDailyData = $this->pluckDailyCounts(
            (clone $teamContacts)
                ->where('is_contacted', true)
                ->where('status_updated_by', $user->id)
                ->where(function ($query) {
                    $query->whereNotNull('status_updated_at')
                        ->orWhereNotNull('contacted_at');
                }),
            DB::raw('COALESCE(status_updated_at, contacted_at)'),
            $dateLabels
        );

        $assistantDailyData = $this->pluckDailyCounts(
            (clone $teamContacts)->whereNotNull('sub_leader_id'),
            'created_at',
            $dateLabels
        );

        $mainDailyTargetData = $this->buildDailyTargetData($dailyTarget, 7);
        $assistantDailyTargetData = $this->buildDailyTargetData(User::TARGET_SUB_LEADER, 7);

        return array_merge($this->emptyPayload(), [
            'stats' => array_merge($stats, [
                'daily_labels' => $this->formatDailyLabels($dateLabels),
            ]),
            'assistantChartData' => $assistantChartData,
            'mainTargetData' => $mainTargetData,
            'mainDailyData' => $mainDailyData,
            'mainDailyTargetData' => $mainDailyTargetData,
            'assistantDailyData' => $assistantDailyData,
            'assistantDailyTargetData' => $assistantDailyTargetData,
        ]);
    }

    private function getSpecialChannelLeaderDashboardData(User $user, string $channel, int $dailyTarget): array
    {
        if ($channel === User::MARKETING_CHANNEL_TOPMATCH) {
            $contacts = $this->getAllContactsQuery()->where('marketing_channel', User::MARKETING_CHANNEL_TOPMATCH)->count();
            $contactsThisMonth = $this->getAllContactsQuery()->where('marketing_channel', User::MARKETING_CHANNEL_TOPMATCH)
                ->where('period_key', Contact::activePeriodKey())
                ->count();
            $assistantCount = User::where('role', User::ROLE_SUB_LEADER)
                ->where('marketing_channel', User::MARKETING_CHANNEL_TOPMATCH)
                ->count();
        } else {
            // Total contacts visible (all contacts entered by any sub-leader)
            $contacts = $this->getAllContactsQuery()->whereNotNull('sub_leader_id')->count();
            $contactsThisMonth = $this->getAllContactsQuery()->whereNotNull('sub_leader_id')
                ->where('period_key', Contact::activePeriodKey())
                ->count();
            $assistantCount = 0;
        }

        // Total contacted in this channel
        $contacted = ContactChannelHistory::where('marketing_channel', $channel)
            ->where('is_contacted', true)
            ->count();

        // Today's count by this user in this channel
        $personalHandled = ContactChannelHistory::where('marketing_channel', $channel)
            ->where('marketing_user_id', $user->id)
            ->where('is_contacted', true)
            ->whereDate('status_updated_at', now()->toDateString())
            ->count();

        $mainTargetData = $this->buildMainTargetData($personalHandled, $dailyTarget);
        $stats = $this->buildLeaderStats($contacts, $contacted, $assistantCount, $mainTargetData['progress'], $dailyTarget, $contactsThisMonth);

        $dateLabels = $this->buildDateLabels(7);

        // Build daily main data from channel history
        $mainDailyData = $this->pluckDailyCounts(
            ContactChannelHistory::where('marketing_channel', $channel)
                ->where('marketing_user_id', $user->id)
                ->where('is_contacted', true),
            'status_updated_at',
            $dateLabels
        );

        $mainDailyTargetData = $this->buildDailyTargetData($dailyTarget, 7);

        // Fetch all leaders in the same special channel for peer comparison
        $channelLeaders = User::where('users.role', User::ROLE_LEADER)
            ->where('users.marketing_channel', $channel)
            ->orderBy('users.name')
            ->get();

        $leadersContactedToday = ContactChannelHistory::where('marketing_channel', $channel)
            ->where('is_contacted', true)
            ->whereDate('status_updated_at', now()->toDateString())
            ->select('marketing_user_id', DB::raw('COUNT(*) as contacted_count'))
            ->groupBy('marketing_user_id')
            ->pluck('contacted_count', 'marketing_user_id')
            ->toArray();

        $assistantChartData = $channelLeaders->map(function ($leader) use ($leadersContactedToday) {
            return [
                'label' => $leader->name,
                'count' => $leadersContactedToday[$leader->id] ?? 0,
            ];
        })->all();

        return array_merge($this->emptyPayload(), [
            'stats' => array_merge($stats, [
                'daily_labels' => $this->formatDailyLabels($dateLabels),
            ]),
            'assistantChartData' => $assistantChartData,
            'mainTargetData' => $mainTargetData,
            'mainDailyData' => $mainDailyData,
            'mainDailyTargetData' => $mainDailyTargetData,
            'assistantDailyData' => [],
            'assistantDailyTargetData' => [],
        ]);
    }

    private function getSubLeaderDashboardData(User $user): array
    {
        $contactsTotal = $this->getAllContactsQuery()->where('sub_leader_id', $user->id)->count();
        $contactsThisMonth = $this->getAllContactsQuery()->where('sub_leader_id', $user->id)
            ->where('period_key', Contact::activePeriodKey())
            ->count();

        $stats = [
            'contacts' => $contactsThisMonth,
            'contacts_total' => $contactsTotal,
            'contacts_this_month' => $contactsThisMonth,
            'target' => User::TARGET_SUB_LEADER,
            'progress' => User::TARGET_SUB_LEADER > 0
                ? min(100, (int) round(($contactsThisMonth / User::TARGET_SUB_LEADER) * 100))
                : 0,
        ];

        $dateLabels = $this->buildDateLabels(7);
        $subLeaderDailyData = $this->pluckDailyCounts(
            $this->getAllContactsQuery()->where('sub_leader_id', $user->id),
            'created_at',
            $dateLabels
        );

        $subLeaderDailyTargetData = $this->buildDailyTargetData(User::TARGET_SUB_LEADER, 7);

        return array_merge($this->emptyPayload(), [
            'stats' => array_merge($stats, [
                'daily_labels' => $this->formatDailyLabels($dateLabels),
            ]),
            'subLeaderDailyData' => $subLeaderDailyData,
            'subLeaderDailyTargetData' => $subLeaderDailyTargetData,
        ]);
    }

    private function buildSuperAdminStats(): array
    {
        $leadersCount = User::where('role', User::ROLE_LEADER)->count();
        $subLeadersCount = User::where('role', User::ROLE_SUB_LEADER)->count();
        $contactsCount = $this->getAllContactsQuery()->count();

        return [
            'leaders' => $leadersCount,
            'sub_leaders' => $subLeadersCount,
            'contacts' => $contactsCount,
            'avg_per_sub_leader' => $subLeadersCount > 0
                ? (int) round($contactsCount / $subLeadersCount)
                : 0,
        ];
    }

    private function buildSuperAdminMeta(): array
    {
        // Toploker Leader
        $topLeader = User::where('role', User::ROLE_LEADER)
            ->where(function ($q) {
                $q->whereNull('marketing_channel')
                    ->orWhere('marketing_channel', User::MARKETING_CHANNEL_TOPLOKER);
            })
            ->withCount(['contactsHandled as contacts_handled_count' => function ($query) {
                $query->where('is_contacted', true);
            }])
            ->orderByDesc('contacts_handled_count')
            ->first();

        // Topmatch Leader
        $topTopmatch = User::where('role', User::ROLE_LEADER)
            ->where('marketing_channel', User::MARKETING_CHANNEL_TOPMATCH)
            ->withCount(['channelHistories as contacts_handled_count' => function ($query) {
                $query->where('is_contacted', true);
            }])
            ->orderByDesc('contacts_handled_count')
            ->first();

        // Kerja Malam Leader
        $topKerjaMalam = User::where('role', User::ROLE_LEADER)
            ->where('marketing_channel', User::MARKETING_CHANNEL_KERJA_MALAM)
            ->withCount(['channelHistories as contacts_handled_count' => function ($query) {
                $query->where('is_contacted', true);
            }])
            ->orderByDesc('contacts_handled_count')
            ->first();

        $topSubLeader = User::where('role', User::ROLE_SUB_LEADER)
            ->withCount('contactsEntered')
            ->orderByDesc('contacts_entered_count')
            ->first();

        return [
            'sub_leaders_without_leader' => User::where('role', User::ROLE_SUB_LEADER)
                ->whereNull('leader_id')
                ->count(),
            'top_leader' => $topLeader,
            'top_topmatch' => $topTopmatch,
            'top_kerja_malam' => $topKerjaMalam,
            'top_sub_leader' => $topSubLeader,
        ];
    }

    private function getLeaderComparisonDataForDay(string $date): Collection
    {
        return User::where('users.role', User::ROLE_LEADER)
            ->where(function ($q) {
                $q->whereNull('users.marketing_channel')
                    ->orWhere('users.marketing_channel', User::MARKETING_CHANNEL_TOPLOKER);
            })
            ->leftJoinSub($this->getAllContactsQuery(), 'contacts', function ($join) {
                $join->on('users.id', '=', 'contacts.leader_id')
                    ->orOn('users.id', '=', 'contacts.contacted_by_leader_id');
            })
            ->select('users.id', 'users.name')
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN DATE(contacts.created_at) = ? OR DATE(contacts.status_updated_at) = ? OR DATE(contacts.contacted_at) = ? THEN contacts.id END) as total_count',
                [$date, $date, $date]
            )
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN contacts.is_contacted = 1 AND (DATE(contacts.status_updated_at) = ? OR DATE(contacts.contacted_at) = ?) THEN contacts.id END) as contacted_count',
                [$date, $date]
            )
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('contacted_count')
            ->get()
            ->map(fn ($item) => $this->mapLeaderComparisonRow($item));
    }

    private function mapLeaderComparisonRow(object $item): array
    {
        $totalCount = (int) $item->total_count;
        $contactedCount = (int) $item->contacted_count;

        return [
            'label' => $item->name,
            'total' => $totalCount,
            'contacted' => $contactedCount,
            'uncontacted' => max($totalCount - $contactedCount, 0),
        ];
    }

    private function getTopmatchLeaderComparisonDataForDay(string $date): Collection
    {
        return User::where('users.role', User::ROLE_LEADER)
            ->where('users.marketing_channel', User::MARKETING_CHANNEL_TOPMATCH)
            ->leftJoin('contact_channel_histories', function ($join) use ($date) {
                $join->on('users.id', '=', 'contact_channel_histories.marketing_user_id')
                    ->where('contact_channel_histories.marketing_channel', '=', User::MARKETING_CHANNEL_TOPMATCH)
                    ->where('contact_channel_histories.is_contacted', '=', 1)
                    ->whereRaw('DATE(contact_channel_histories.contacted_at) = ?', [$date]);
            })
            ->select('users.id', 'users.name')
            ->selectRaw('COUNT(DISTINCT contact_channel_histories.id) as contacted_count')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('contacted_count')
            ->get()
            ->map(function ($item) {
                return [
                    'label' => $item->name,
                    'contacted' => (int) $item->contacted_count,
                ];
            });
    }

    private function getKerjaMalamLeaderComparisonDataForDay(string $date): Collection
    {
        return User::where('users.role', User::ROLE_LEADER)
            ->where('users.marketing_channel', User::MARKETING_CHANNEL_KERJA_MALAM)
            ->leftJoin('contact_channel_histories', function ($join) use ($date) {
                $join->on('users.id', '=', 'contact_channel_histories.marketing_user_id')
                    ->where('contact_channel_histories.marketing_channel', '=', User::MARKETING_CHANNEL_KERJA_MALAM)
                    ->where('contact_channel_histories.is_contacted', '=', 1)
                    ->whereRaw('DATE(contact_channel_histories.contacted_at) = ?', [$date]);
            })
            ->select('users.id', 'users.name')
            ->selectRaw('COUNT(DISTINCT contact_channel_histories.id) as contacted_count')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('contacted_count')
            ->get()
            ->map(function ($item) {
                return [
                    'label' => $item->name,
                    'contacted' => (int) $item->contacted_count,
                ];
            });
    }

    private function getSubLeaderComparisonDataForDay(string $date): Collection
    {
        return User::where('users.role', User::ROLE_SUB_LEADER)
            ->leftJoin('users as leaders', 'users.leader_id', '=', 'leaders.id')
            ->leftJoinSub($this->getAllContactsQuery(), 'contacts', 'users.id', '=', 'contacts.sub_leader_id')
            ->select('users.id', 'users.name', 'leaders.name as leader_name')
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN DATE(contacts.created_at) = ? OR DATE(contacts.status_updated_at) = ? OR DATE(contacts.contacted_at) = ? THEN contacts.id END) as total_count',
                [$date, $date, $date]
            )
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN contacts.is_contacted = 1 AND (DATE(contacts.status_updated_at) = ? OR DATE(contacts.contacted_at) = ?) THEN contacts.id END) as contacted_count',
                [$date, $date]
            )
            ->groupBy('users.id', 'users.name', 'leaders.name')
            ->orderByDesc('total_count')
            ->get()
            ->map(function ($item) {
                $totalCount = (int) $item->total_count;
                $contactedCount = (int) $item->contacted_count;

                return [
                    'label' => $item->name,
                    'group' => $item->leader_name ?? 'Tanpa Leader',
                    'total' => $totalCount,
                    'contacted' => $contactedCount,
                    'uncontacted' => max($totalCount - $contactedCount, 0),
                ];
            });
    }

    private function getTeamComparisonData(int $year, int $month): Collection
    {
        if (! Schema::hasTable('teams')) {
            return collect();
        }

        return Team::query()
            ->leftJoin('users', 'teams.id', '=', 'users.team_id')
            ->leftJoinSub($this->getAllContactsQuery(), 'contacts', function ($join) {
                $join->on(function ($query) {
                    $query->on('contacts.leader_id', '=', 'users.id')
                        ->orOn('contacts.sub_leader_id', '=', 'users.id');
                });
            })
            ->select('teams.id', 'teams.name')
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN (YEAR(contacts.created_at) = ? AND MONTH(contacts.created_at) = ?) OR (YEAR(contacts.status_updated_at) = ? AND MONTH(contacts.status_updated_at) = ?) OR (YEAR(contacts.contacted_at) = ? AND MONTH(contacts.contacted_at) = ?) THEN contacts.id END) as total_count',
                [$year, $month, $year, $month, $year, $month]
            )
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN contacts.is_contacted = 1 AND ((YEAR(contacts.status_updated_at) = ? AND MONTH(contacts.status_updated_at) = ?) OR (YEAR(contacts.contacted_at) = ? AND MONTH(contacts.contacted_at) = ?)) THEN contacts.id END) as contacted_count',
                [$year, $month, $year, $month]
            )
            ->groupBy('teams.id', 'teams.name')
            ->orderByDesc('total_count')
            ->get()
            ->map(function ($item) {
                $totalCount = (int) $item->total_count;
                $contactedCount = (int) $item->contacted_count;

                return [
                    'label' => $item->name,
                    'total' => $totalCount,
                    'contacted' => $contactedCount,
                    'uncontacted' => max($totalCount - $contactedCount, 0),
                ];
            });
    }

    private function buildMonthlyTotalsData(int $year, int $month): Collection
    {
        $startMonth = now()->subMonths(11)->startOfMonth();
        $endMonth = now()->endOfMonth();

        $contactedRows = $this->getAllContactsQuery()
            ->where('is_contacted', true)
            ->where(function ($query) {
                $query->whereNotNull('status_updated_at')
                    ->orWhereNotNull('contacted_at');
            })
            ->whereBetween(DB::raw('COALESCE(status_updated_at, contacted_at)'), [$startMonth, $endMonth])
            ->selectRaw('YEAR(COALESCE(status_updated_at, contacted_at)) as year, MONTH(COALESCE(status_updated_at, contacted_at)) as month, COUNT(*) as total_count')
            ->groupBy('year', 'month')
            ->get();

        $inputRows = $this->getAllContactsQuery()
            ->whereNotNull('sub_leader_id')
            ->whereBetween('created_at', [$startMonth, $endMonth])
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as total_count')
            ->groupBy('year', 'month')
            ->get();

        $contactedMap = $this->buildMonthlyMap($contactedRows);
        $inputMap = $this->buildMonthlyMap($inputRows);

        return collect(range(0, 11))
            ->map(function (int $offset) use ($startMonth, $contactedMap, $inputMap) {
                $date = (clone $startMonth)->addMonths($offset);
                $key = $date->format('Y-m');

                return [
                    'key' => $key,
                    'label' => Carbon::create($date->year, $date->month)->translatedFormat('M Y'),
                    'contacted_total' => $contactedMap->get($key, 0),
                    'input_total' => $inputMap->get($key, 0),
                ];
            });
    }

    private function buildMonthlyMap(Collection $rows): Collection
    {
        return $rows->mapWithKeys(function ($row) {
            $key = sprintf('%04d-%02d', (int) $row->year, (int) $row->month);

            return [$key => (int) $row->total_count];
        });
    }

    private function buildMainTargetData(int $contacted, int $target = User::TARGET_LEADER): array
    {
        return [
            'target' => $target,
            'contacted' => $contacted,
            'remaining' => max($target - $contacted, 0),
            'progress' => $target > 0
                ? min(100, (int) round(($contacted / $target) * 100))
                : 0,
        ];
    }

    private function buildLeaderStats(int $contacts, int $contacted, int $subLeadersCount, int $progress, int $target = User::TARGET_LEADER, int $contactsThisMonth = 0): array
    {
        return [
            'contacts' => $contacts,
            'contacts_this_month' => $contactsThisMonth,
            'contacted' => $contacted,
            'sub_leaders' => $subLeadersCount,
            'target' => $target,
            'progress' => $progress,
        ];
    }

    private function buildDateLabels(int $days): Collection
    {
        return collect(range($days - 1, 0))
            ->map(fn (int $offset) => now()->subDays($offset)->format('Y-m-d'));
    }

    private function formatDailyLabels(Collection $dateLabels): array
    {
        return $dateLabels->map(fn (string $date) => Carbon::parse($date)->translatedFormat('d M'))->all();
    }

    private function pluckDailyCounts($query, string|Expression $dateColumn, Collection $dateLabels): array
    {
        $dateColumnSql = $this->expressionToString($dateColumn);

        $dailyCounts = $query
            ->whereBetween($dateColumn, [$dateLabels->first().' 00:00:00', now()->endOfDay()])
            ->selectRaw("DATE({$dateColumnSql}) as date, COUNT(*) as total")
            ->groupBy('date')
            ->pluck('total', 'date');

        return $dateLabels->map(fn (string $date) => (int) ($dailyCounts[$date] ?? 0))->all();
    }

    private function expressionToString(string|Expression $column): string
    {
        if ($column instanceof Expression) {
            return $column->getValue(DB::connection()->getQueryGrammar());
        }

        return $column;
    }

    private function buildDailyTargetData(int $target, int $length): array
    {
        return array_fill(0, $length, $target);
    }

    private function getSelectedMonth(Request $request): string
    {
        return $request->input('leader_chart_month', now()->format('Y-m'));
    }

    private function getLeaderChartDate(Request $request): string
    {
        $date = $request->input('leader_chart_date', now()->format('Y-m-d'));

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : now()->format('Y-m-d');
    }

    private function getSubLeaderChartDate(Request $request): string
    {
        $date = $request->input('sub_leader_chart_date', now()->format('Y-m-d'));

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : now()->format('Y-m-d');
    }

    private function parseYearAndMonth(string $selectedMonth): array
    {
        [$year, $month] = explode('-', $selectedMonth);

        return [(int) $year, (int) $month];
    }

    private function getAllContactsQuery()
    {
        $contacts = DB::table('contacts')
            ->select('id', 'is_contacted', 'created_at', 'status_updated_at', 'contacted_at', 'sub_leader_id', 'leader_id', 'contacted_by_leader_id', 'status_updated_by', 'team_id', 'period_key', 'marketing_channel');

        $histories = DB::table('contact_histories')
            ->select('id', 'is_contacted', 'original_created_at as created_at', 'status_updated_at', 'contacted_at', 'sub_leader_id', 'leader_id', 'contacted_by_leader_id', 'status_updated_by', 'team_id', 'archive_period as period_key', 'marketing_channel');

        return DB::query()->fromSub($contacts->unionAll($histories), 'contacts');
    }

    private function emptyPayload(): array
    {
        return [
            'stats' => [],
            'meta' => [],
            'leaderComparisonData' => collect(),
            'topmatchComparisonData' => collect(),
            'kerjaMalamComparisonData' => collect(),
            'subLeaderComparisonData' => collect(),
            'assistantChartData' => [],
            'mainTargetData' => [
                'target' => 0,
                'contacted' => 0,
                'remaining' => 0,
                'progress' => 0,
            ],
            'mainDailyData' => [],
            'mainDailyTargetData' => [],
            'assistantDailyData' => [],
            'assistantDailyTargetData' => [],
            'subLeaderDailyData' => [],
            'subLeaderDailyTargetData' => [],
            'monthlyTotalsData' => collect(),
            'selectedMonth' => null,
            'leaderChartDate' => null,
            'subLeaderChartDate' => null,
        ];
    }
}
