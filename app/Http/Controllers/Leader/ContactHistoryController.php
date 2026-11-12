<?php

namespace App\Http\Controllers\Leader;

use App\Http\Controllers\Controller;
use App\Models\ContactHistory;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ContactHistoryController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = auth()->user();
        $channel = $user->marketing_channel ?? User::MARKETING_CHANNEL_TOPLOKER;

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'archive_period' => ['nullable', 'string', 'max:7', 'regex:/^\d{4}-\d{2}$/'],
            'sub_leader_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'in:10,20,50,100'],
        ]);

        $q = isset($validated['q']) ? trim((string) $validated['q']) : null;
        $archivePeriod = $validated['archive_period'] ?? null;
        $subLeaderId = isset($validated['sub_leader_id']) ? (int) $validated['sub_leader_id'] : null;
        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = ContactHistory::query()
            ->with(['subLeader:id,name'])
            ->orderByDesc('archive_period')
            ->orderByDesc('id');

        // Scope by role/channel:
        // Toploker leader → only contacts belonging to their team and toploker channel
        // Topmatch leader → contacts belonging to topmatch channel
        // Kerja Malam → contacts entered through subleaders
        if ($user->isToploker()) {
            if ($user->team_id) {
                $query->where('team_id', $user->team_id)
                    ->where(function ($q) {
                        $q->where('marketing_channel', User::MARKETING_CHANNEL_TOPLOKER)
                            ->orWhereNull('marketing_channel');
                    });
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($user->isTopmatch()) {
            $query->where(function (Builder $q): void {
                $q->where('marketing_channel', User::MARKETING_CHANNEL_TOPMATCH)
                    ->orWhereNotNull('sub_leader_id');
            });
        } else {
            // Other special channels: all contacts that had a sub_leader
            $query->whereNotNull('sub_leader_id');
        }

        // Search by name or phone
        if ($q !== null && $q !== '') {
            $query->where(function (Builder $builder) use ($q): void {
                $builder
                    ->where('contact_name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('normalized_phone', 'like', "%{$q}%");
            });
        }

        // Filter by archive period
        if ($archivePeriod !== null && $archivePeriod !== '') {
            $query->where('archive_period', $archivePeriod);
        }

        // Filter by sub-leader
        if ($subLeaderId !== null && $subLeaderId > 0) {
            $query->where('sub_leader_id', $subLeaderId);
        }

        $histories = $query->paginate($perPage)->withQueryString();

        // Sub-leaders dropdown
        $subLeaders = collect();
        if ($user->isToploker() && $user->team_id) {
            $subLeaders = User::where('role', User::ROLE_SUB_LEADER)
                ->where('team_id', $user->team_id)
                ->orderBy('name')
                ->get(['id', 'name']);
        } elseif ($user->isTopmatch()) {
            $subLeaders = User::where('role', User::ROLE_SUB_LEADER)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        // Distinct archive periods
        $archivePeriods = ContactHistory::query()
            ->when($user->isToploker() && $user->team_id, fn ($q) => $q->where('team_id', $user->team_id))
            ->when($user->isTopmatch(), fn ($q) => $q->where(function (Builder $sq): void {
                $sq->where('marketing_channel', User::MARKETING_CHANNEL_TOPMATCH)
                    ->orWhereNotNull('sub_leader_id');
            }))
            ->when(! $user->isToploker() && ! $user->isTopmatch(), fn ($q) => $q->whereNotNull('sub_leader_id'))
            ->select('archive_period')
            ->distinct()
            ->orderByDesc('archive_period')
            ->pluck('archive_period');

        return view('leader.contact-histories.index', [
            'histories' => $histories,
            'subLeaders' => $subLeaders,
            'archivePeriods' => $archivePeriods,
            'isToploker' => $user->isToploker(),
            'channelLabel' => $user->marketingChannelLabel(),
            'filters' => [
                'q' => $q,
                'archive_period' => $archivePeriod,
                'sub_leader_id' => $subLeaderId,
                'per_page' => $perPage,
            ],
        ]);
    }
}
