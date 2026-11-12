<?php

namespace App\Http\Controllers\Leader;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactChannelHistory;
use App\Models\User;
use App\Services\ContactFilter;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        $channel = $user->marketing_channel ?? User::MARKETING_CHANNEL_TOPLOKER;

        $filters = ContactFilter::resolveDateFilter($request);
        $uiFilters = ContactFilter::resolveUiFilters($request);
        $selectedSubLeaderId = $request->integer('sub_leader_id');

        // Sub-leaders in Toploker or Topmatch channel
        $subLeaders = collect();
        if ($user->isToploker()) {
            $subLeaders = User::query()
                ->where('role', User::ROLE_SUB_LEADER)
                ->when(
                    $user->team_id,
                    fn (Builder $query) => $query->where('team_id', $user->team_id),
                    fn (Builder $query) => $query->whereRaw('1 = 0')
                )
                ->withCount([
                    'contactsEntered as contacts_entered_count' => function (Builder $query) use ($user, $filters, $uiFilters): void {
                        $this->applyLeaderContactScope($query, $user);
                        ContactFilter::applyDateFilter($query, $filters);
                        ContactFilter::applyListFilters($query, $uiFilters, false);
                    },
                ])
                ->orderBy('name')
                ->get();
        } elseif ($user->isTopmatch()) {
            $subLeaders = User::query()
                ->where('role', User::ROLE_SUB_LEADER)
                ->withCount([
                    'contactsEntered as contacts_entered_count' => function (Builder $query) use ($user, $filters, $uiFilters): void {
                        $this->applyLeaderContactScope($query, $user);
                        ContactFilter::applyDateFilter($query, $filters);
                        ContactFilter::applyListFilters($query, $uiFilters, false);
                    },
                ])
                ->orderBy('name')
                ->get();
        }

        $allowedSubLeaderIds = $subLeaders->pluck('id')->all();
        if ($selectedSubLeaderId > 0 && ! in_array($selectedSubLeaderId, $allowedSubLeaderIds, true)) {
            $selectedSubLeaderId = null;
        }

        $contactsQuery = $this->scopedContacts($user)
            ->with(['subLeader:id,name', 'inputBy:id,name,role'])
            ->latest();
        ContactFilter::applyDateFilter($contactsQuery, $filters);

        // For special channels, apply status filter based on channel history
        if ($user->isSpecialChannel()) {
            $this->applySpecialChannelStatusFilter($contactsQuery, $uiFilters, $channel);
        } else {
            ContactFilter::applyListFilters($contactsQuery, $uiFilters, false);
        }

        if ($selectedSubLeaderId) {
            $contactsQuery->where('sub_leader_id', $selectedSubLeaderId);
        }

        $perPage = (int) $uiFilters['per_page'];

        // --- Statistics ---
        $monthlyContactedData = $this->getMonthlyContactedData($user, $channel);

        // --- Channel-specific contacted map for the current page ---
        $contacts = $contactsQuery->paginate($perPage)->withQueryString();
        $channelContactedMap = $this->buildChannelContactedMap($contacts->items(), $user, $channel);
        $channelContactedDateMap = $this->buildChannelContactedDateMap($contacts->items(), $user, $channel);

        return view('leader.contacts.index', [
            'subLeaders' => $subLeaders,
            'selectedSubLeaderId' => $selectedSubLeaderId,
            'filters' => $filters,
            'uiFilters' => $uiFilters,
            'contacts' => $contacts,
            'monthlyContactedData' => $monthlyContactedData,
            'channelContactedMap' => $channelContactedMap,
            'channelContactedDateMap' => $channelContactedDateMap,
            'isSpecialChannel' => $user->isSpecialChannel(),
            'channelLabel' => $user->marketingChannelLabel(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = auth()->user();
        $channel = $user->marketing_channel ?? User::MARKETING_CHANNEL_TOPLOKER;

        $filters = ContactFilter::resolveDateFilter($request);
        $uiFilters = ContactFilter::resolveUiFilters($request);
        $selectedSubLeaderId = $request->integer('sub_leader_id');

        $allowedSubLeaderIds = [];
        if ($user->isToploker()) {
            $allowedSubLeaderIds = User::query()
                ->where('role', User::ROLE_SUB_LEADER)
                ->when(
                    $user->team_id,
                    fn (Builder $query) => $query->where('team_id', $user->team_id),
                    fn (Builder $query) => $query->whereRaw('1 = 0')
                )
                ->pluck('id')
                ->all();
        } elseif ($user->isTopmatch()) {
            $allowedSubLeaderIds = User::query()
                ->where('role', User::ROLE_SUB_LEADER)
                ->pluck('id')
                ->all();
        }

        if ($selectedSubLeaderId > 0 && ! in_array($selectedSubLeaderId, $allowedSubLeaderIds, true)) {
            $selectedSubLeaderId = null;
        }

        $fileName = 'rekap-kontak-leader-'.$user->id.'-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($user, $channel, $selectedSubLeaderId, $filters, $uiFilters) {
            $handle = fopen('php://output', 'w');
            $nameHeader = $user->isTopmatch() ? 'Nama Perusahaan' : 'Nama Kontak';
            fputcsv($handle, [$nameHeader, 'Nomor', 'Asisten Marketing', 'Status', 'Tanggal Input']);

            $contactsQuery = $this->scopedContacts($user)
                ->with('subLeader:id,name')
                ->orderByDesc('created_at');

            ContactFilter::applyDateFilter($contactsQuery, $filters);

            if ($user->isSpecialChannel()) {
                $this->applySpecialChannelStatusFilter($contactsQuery, $uiFilters, $channel);
            } else {
                ContactFilter::applyListFilters($contactsQuery, $uiFilters, false);
            }

            if ($selectedSubLeaderId) {
                $contactsQuery->where('sub_leader_id', $selectedSubLeaderId);
            }

            $contactsQuery->chunk(200, function ($contacts) use ($handle, $channel, $user) {
                // Build channel contacted map for this chunk
                $contactedMap = $this->buildChannelContactedMap($contacts->all(), $user, $channel);

                foreach ($contacts as $contact) {
                    $isContacted = $contactedMap[$contact->id] ?? $contact->isContacted();
                    $statusLabel = $isContacted ? 'Sudah Dihubungi' : 'Belum Dihubungi';

                    fputcsv($handle, [
                        $contact->contact_name ?? '-',
                        $contact->phone,
                        $contact->subLeader?->name ?? '-',
                        $statusLabel,
                        $contact->created_at->format('Y-m-d H:i:s'),
                    ]);
                }
            });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function whatsapp(Contact $contact): RedirectResponse
    {
        $user = auth()->user();

        abort_unless($this->leaderCanAccessContact($user, $contact), 404);

        return redirect()->away($contact->whatsapp_url);
    }

    public function updateStatus(Contact $contact, Request $request): JsonResponse
    {
        $user = auth()->user();

        abort_unless($this->leaderCanAccessContact($user, $contact), 404);

        $validated = $request->validate([
            'is_contacted' => ['required', 'boolean'],
        ]);

        $contact->setChannelContacted($user, (bool) $validated['is_contacted']);

        $channel = $user->marketing_channel ?? User::MARKETING_CHANNEL_TOPLOKER;
        $isContactedNow = $this->resolveContactedStatus($contact, $channel);

        return response()->json([
            'ok' => true,
            'is_contacted' => $isContactedNow,
            'label' => $isContactedNow ? 'Sudah Dihubungi' : 'Belum Dihubungi',
        ]);
    }

    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $user = auth()->user();
        $channel = $user->marketing_channel ?? User::MARKETING_CHANNEL_TOPLOKER;

        $validated = $request->validate([
            'contact_ids' => ['required', 'array', 'min:1'],
            'contact_ids.*' => ['integer', 'distinct'],
        ]);

        $ids = array_map('intval', $validated['contact_ids']);

        if ($user->isToploker()) {
            // Toploker: update legacy is_contacted column + channel history
            $updated = Contact::query()
                ->whereIn('id', $ids)
                ->where('team_id', $user->team_id)
                ->update([
                    'is_contacted' => true,
                    'status' => Contact::STATUS_CONTACTED,
                    'status_updated_by' => $user->id,
                    'status_updated_at' => now(),
                    'contacted_at' => now(),
                    'contacted_by_leader_id' => $user->id,
                ]);

            // Also write channel history
            $validIds = Contact::whereIn('id', $ids)
                ->where('team_id', $user->team_id)
                ->pluck('id')
                ->toArray();

            ContactChannelHistory::bulkMarkContactedForChannel($validIds, $user, $channel);
        } else {
            // Special channels: only update channel history
            // Verify the contacts exist (accessible to this user)
            $validIds = $this->scopedContacts($user)->whereIn('contacts.id', $ids)->pluck('contacts.id')->toArray();
            $updated = count($validIds);

            ContactChannelHistory::bulkMarkContactedForChannel($validIds, $user, $channel);
        }

        return response()->json([
            'ok' => true,
            'updated' => $updated ?? 0,
        ]);
    }

    // -------------------------------------------------------------------------
    // Scoping helpers
    // -------------------------------------------------------------------------

    private function scopedContacts(User $user): Builder
    {
        $query = Contact::query();
        $this->applyLeaderContactScope($query, $user);

        return $query;
    }

    private function applyLeaderContactScope(Builder $query, User $user): void
    {
        // Only show contacts from the current active month
        $query->where('contacts.period_key', Contact::activePeriodKey());

        if ($user->isToploker()) {
            // Toploker: see contacts scoped to their team
            if (! $user->team_id) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where('contacts.team_id', $user->team_id)
                ->where(function ($q) {
                    $q->where('contacts.marketing_channel', User::MARKETING_CHANNEL_TOPLOKER)
                        ->orWhereNull('contacts.marketing_channel');
                });

            return;
        }

        if ($user->isTopmatch()) {
            // Topmatch: see contacts for Topmatch channel OR any contacts input by sub-leaders (including Toploker)
            $query->where(function (Builder $q): void {
                $q->where('contacts.marketing_channel', User::MARKETING_CHANNEL_TOPMATCH)
                    ->orWhereNotNull('contacts.sub_leader_id');
            });

            return;
        }

        // Kerja Malam: see ALL contacts that have been input by any sub-leader
        // (no team restriction — they share the entire contact pool)
        $query->whereNotNull('contacts.sub_leader_id');
    }

    private function leaderCanAccessContact(User $user, Contact $contact): bool
    {
        if ($user->isToploker()) {
            if (! $user->team_id || ! $contact->team_id) {
                return false;
            }

            return (int) $contact->team_id === (int) $user->team_id;
        }

        if ($user->isTopmatch()) {
            return $contact->marketing_channel === User::MARKETING_CHANNEL_TOPMATCH
                || $contact->sub_leader_id !== null;
        }

        // Special channels (Kerja Malam): can access any contact entered by a sub-leader
        return $contact->sub_leader_id !== null;
    }

    // -------------------------------------------------------------------------
    // Channel-specific status filter
    // -------------------------------------------------------------------------

    /**
     * Apply a status filter for Topmatch / KerjaMalam based on contact_channel_histories.
     *
     * @param  array<string, mixed>  $uiFilters
     */
    private function applySpecialChannelStatusFilter(Builder $query, array $uiFilters, string $channel): void
    {
        $status = $uiFilters['status'] ?? 'all';

        if ($status === 'contacted') {
            $query->whereExists(function ($sub) use ($channel) {
                $sub->from('contact_channel_histories')
                    ->whereColumn('contact_channel_histories.contact_id', 'contacts.id')
                    ->where('contact_channel_histories.marketing_channel', $channel)
                    ->where('contact_channel_histories.is_contacted', true);
            });
        } elseif ($status === 'uncontacted') {
            $query->whereNotExists(function ($sub) use ($channel) {
                $sub->from('contact_channel_histories')
                    ->whereColumn('contact_channel_histories.contact_id', 'contacts.id')
                    ->where('contact_channel_histories.marketing_channel', $channel)
                    ->where('contact_channel_histories.is_contacted', true);
            });
        }

        // Apply search (q) filter
        if (! empty($uiFilters['q'])) {
            $q = $uiFilters['q'];
            $query->where(function (Builder $subQ) use ($q) {
                $subQ->where('contacts.contact_name', 'like', "%{$q}%")
                    ->orWhere('contacts.phone', 'like', "%{$q}%")
                    ->orWhere('contacts.normalized_phone', 'like', "%{$q}%");
            });
        }
    }

    // -------------------------------------------------------------------------
    // Statistics helpers
    // -------------------------------------------------------------------------

    private function getChannelContactedCount(User $user, string $channel): int
    {
        if ($user->isToploker()) {
            return $this->scopedContacts($user)->where('is_contacted', true)->count();
        }

        return ContactChannelHistory::where('marketing_channel', $channel)
            ->where('is_contacted', true)
            ->whereExists(function ($sub) {
                $sub->from('contacts')
                    ->whereColumn('contacts.id', 'contact_channel_histories.contact_id')
                    ->where(function (Builder $q): void {
                        $q->whereNotNull('contacts.sub_leader_id')
                            ->orWhere('contacts.marketing_channel', User::MARKETING_CHANNEL_TOPMATCH);
                    });
            })
            ->count();
    }

    private function getChannelContactedThisMonth(User $user, string $channel): int
    {
        if ($user->isToploker()) {
            return $this->scopedContacts($user)
                ->where('is_contacted', true)
                ->where(function (Builder $query) {
                    $query->where(function (Builder $query) {
                        $query->whereYear('status_updated_at', now()->year)
                            ->whereMonth('status_updated_at', now()->month);
                    })->orWhere(function (Builder $query) {
                        $query->whereNull('status_updated_at')
                            ->whereYear('contacted_at', now()->year)
                            ->whereMonth('contacted_at', now()->month);
                    });
                })
                ->count();
        }

        return ContactChannelHistory::where('marketing_channel', $channel)
            ->where('is_contacted', true)
            ->whereYear('status_updated_at', now()->year)
            ->whereMonth('status_updated_at', now()->month)
            ->count();
    }

    private function getTodayContactedCount(User $user, string $channel): int
    {
        if ($user->isToploker()) {
            return $user->team_id
                ? Contact::query()
                    ->where('team_id', $user->team_id)
                    ->where('is_contacted', true)
                    ->where('contacted_by_leader_id', $user->id)
                    ->whereDate('status_updated_at', now()->toDateString())
                    ->count()
                : 0;
        }

        return ContactChannelHistory::where('marketing_channel', $channel)
            ->where('marketing_user_id', $user->id)
            ->where('is_contacted', true)
            ->whereDate('status_updated_at', now()->toDateString())
            ->count();
    }

    /**
     * Build monthly contacted chart data for the user's channel.
     *
     * @return Collection<int, array{label: string, count: int}>
     */
    private function getMonthlyContactedData(User $user, string $channel): Collection
    {
        if ($user->isToploker()) {
            return $this->scopedContacts($user)
                ->where('is_contacted', true)
                ->where(function (Builder $query) {
                    $query->whereNotNull('status_updated_at')
                        ->orWhereNotNull('contacted_at');
                })
                ->get(['status_updated_at', 'contacted_at'])
                ->groupBy(fn ($contact) => ($contact->status_updated_at ?? $contact->contacted_at)->format('Y-m'))
                ->sortKeys()
                ->map(fn ($contacts, $key) => [
                    'label' => Carbon::createFromFormat('Y-m', $key)->format('M Y'),
                    'count' => $contacts->count(),
                ])
                ->values();
        }

        return ContactChannelHistory::where('marketing_channel', $channel)
            ->where('is_contacted', true)
            ->whereNotNull('status_updated_at')
            ->get(['status_updated_at'])
            ->groupBy(fn ($h) => $h->status_updated_at->format('Y-m'))
            ->sortKeys()
            ->map(fn ($items, $key) => [
                'label' => Carbon::createFromFormat('Y-m', $key)->format('M Y'),
                'count' => $items->count(),
            ])
            ->values();
    }

    // -------------------------------------------------------------------------
    // Channel contacted map (for view rendering)
    // -------------------------------------------------------------------------

    /**
     * Build a map of [contact_id => bool] indicating channel-specific contacted status
     * for the given set of contacts.
     *
     * @param  array<Contact>  $contactItems
     * @return array<int, bool>
     */
    private function buildChannelContactedMap(array $contactItems, User $user, string $channel): array
    {
        if (empty($contactItems)) {
            return [];
        }

        $contactIds = array_map(fn ($c) => $c->id, $contactItems);

        if ($user->isToploker()) {
            // For Toploker, use the is_contacted field directly
            $map = [];
            foreach ($contactItems as $contact) {
                $map[$contact->id] = (bool) $contact->is_contacted;
            }

            return $map;
        }

        // For special channels, read from contact_channel_histories
        $historyMap = ContactChannelHistory::query()
            ->whereIn('contact_id', $contactIds)
            ->where('marketing_channel', $channel)
            ->pluck('is_contacted', 'contact_id')
            ->toArray();

        $map = [];
        foreach ($contactIds as $id) {
            $map[$id] = (bool) ($historyMap[$id] ?? false);
        }

        return $map;
    }

    /**
     * Build a map of [contact_id => Carbon/string/null] indicating channel-specific contacted date
     * for the given set of contacts.
     *
     * @param  array<Contact>  $contactItems
     * @return array<int, mixed>
     */
    private function buildChannelContactedDateMap(array $contactItems, User $user, string $channel): array
    {
        if (empty($contactItems)) {
            return [];
        }

        $contactIds = array_map(fn ($c) => $c->id, $contactItems);

        if ($user->isToploker()) {
            $map = [];
            foreach ($contactItems as $contact) {
                $map[$contact->id] = $contact->is_contacted ? ($contact->status_updated_at ?? $contact->contacted_at) : null;
            }

            return $map;
        }

        $historyMap = ContactChannelHistory::query()
            ->whereIn('contact_id', $contactIds)
            ->where('marketing_channel', $channel)
            ->where('is_contacted', true)
            ->pluck('contacted_at', 'contact_id')
            ->toArray();

        $map = [];
        foreach ($contactIds as $id) {
            $map[$id] = $historyMap[$id] ?? null;
        }

        return $map;
    }

    /**
     * Resolve the current contacted status for a single contact within a channel.
     */
    private function resolveContactedStatus(Contact $contact, string $channel): bool
    {
        if ($channel === User::MARKETING_CHANNEL_TOPLOKER) {
            return (bool) $contact->fresh()->is_contacted;
        }

        return ContactChannelHistory::where('contact_id', $contact->id)
            ->where('marketing_channel', $channel)
            ->where('is_contacted', true)
            ->exists();
    }
}
