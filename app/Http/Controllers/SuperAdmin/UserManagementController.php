<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Team;
use App\Models\User;
use App\Services\ContactImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index(): View
    {
        // Leaders grouped by marketing channel
        $leadersToploker = User::where('role', User::ROLE_LEADER)
            ->where(function ($q) {
                $q->where('marketing_channel', User::MARKETING_CHANNEL_TOPLOKER)
                    ->orWhereNull('marketing_channel');
            })
            ->withCount('subLeaders')
            ->orderBy('name')
            ->get();

        $leadersTopmatch = User::where('role', User::ROLE_LEADER)
            ->where('marketing_channel', User::MARKETING_CHANNEL_TOPMATCH)
            ->orderBy('name')
            ->get();

        $leadersKerjaMalam = User::where('role', User::ROLE_LEADER)
            ->where('marketing_channel', User::MARKETING_CHANNEL_KERJA_MALAM)
            ->orderBy('name')
            ->get();

        $subLeadersToploker = User::where('role', User::ROLE_SUB_LEADER)
            ->where(function ($q) {
                $q->where('marketing_channel', User::MARKETING_CHANNEL_TOPLOKER)
                    ->orWhereNull('marketing_channel');
            })
            ->with(['leader:id,name', 'teams'])
            ->orderBy('name')
            ->get();

        $subLeadersTopmatch = User::where('role', User::ROLE_SUB_LEADER)
            ->where('marketing_channel', User::MARKETING_CHANNEL_TOPMATCH)
            ->with(['leader:id,name'])
            ->orderBy('name')
            ->get();

        return view('superadmin.users.index', [
            'leaders' => $leadersToploker,          // Toploker marketing utama
            'leadersTopmatch' => $leadersTopmatch,          // Topmatch marketing utama
            'leadersKerjaMalam' => $leadersKerjaMalam,        // Kerja Malam marketing utama
            'subLeaders' => $subLeadersToploker,       // Toploker asisten marketing
            'subLeadersToploker' => $subLeadersToploker,
            'subLeadersTopmatch' => $subLeadersTopmatch,       // Topmatch asisten marketing
            'teams' => Schema::hasTable('teams')
                ? Team::withCount(['members', 'leaders', 'subLeaders'])->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function storeTeam(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('teams', 'name')],
        ]);

        Team::create($validated);

        return back()->with('success', 'Tim berhasil dibuat.');
    }

    public function storeLeader(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'marketing_channel' => ['required', Rule::in(User::allMarketingChannels())],
            'team_id' => [
                // Team is required only for Toploker channel
                Rule::when(
                    $request->input('marketing_channel') === User::MARKETING_CHANNEL_TOPLOKER,
                    ['required', Rule::exists('teams', 'id')],
                    ['nullable', Rule::exists('teams', 'id')]
                ),
            ],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'role' => User::ROLE_LEADER,
            'team_id' => $validated['team_id'] ?? null,
            'marketing_channel' => $validated['marketing_channel'],
        ]);

        $channelLabel = match ($validated['marketing_channel']) {
            User::MARKETING_CHANNEL_TOPMATCH => 'Topmatch',
            User::MARKETING_CHANNEL_KERJA_MALAM => 'Kerja Malam',
            default => 'Toploker',
        };

        return back()->with('success', "Marketing Utama {$channelLabel} berhasil dibuat.");
    }

    public function storeSubLeader(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'marketing_channel' => ['required', Rule::in([User::MARKETING_CHANNEL_TOPLOKER, User::MARKETING_CHANNEL_TOPMATCH])],
            'team_ids' => [
                Rule::when(
                    $request->input('marketing_channel') === User::MARKETING_CHANNEL_TOPLOKER,
                    ['required', 'array'],
                    ['nullable', 'array']
                ),
            ],
            'team_ids.*' => [Rule::exists('teams', 'id')],
            'leader_id' => [
                Rule::when(
                    $request->input('marketing_channel') === User::MARKETING_CHANNEL_TOPMATCH,
                    ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', User::ROLE_LEADER)->where('marketing_channel', User::MARKETING_CHANNEL_TOPMATCH))],
                    ['nullable']
                ),
            ],
        ]);

        $channel = $validated['marketing_channel'];
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'role' => User::ROLE_SUB_LEADER,
            'team_id' => $channel === User::MARKETING_CHANNEL_TOPLOKER ? ($validated['team_ids'][0] ?? null) : null,
            'leader_id' => $channel === User::MARKETING_CHANNEL_TOPMATCH ? ($validated['leader_id'] ?? null) : null,
            'marketing_channel' => $channel,
        ]);

        if ($channel === User::MARKETING_CHANNEL_TOPLOKER && ! empty($validated['team_ids'])) {
            $user->teams()->sync($validated['team_ids']);
        }

        $channelLabel = $channel === User::MARKETING_CHANNEL_TOPMATCH ? 'Topmatch' : 'Toploker';

        return back()->with('success', "Asisten Marketing {$channelLabel} berhasil dibuat.");
    }

    public function assignTeam(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'team_id' => ['nullable', Rule::exists('teams', 'id')],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => [Rule::exists('teams', 'id')],
            'leader_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', User::ROLE_LEADER))],
        ]);

        if ($user->isTopmatch() && array_key_exists('leader_id', $validated)) {
            $user->update(['leader_id' => $validated['leader_id']]);

            return back()->with('success', 'Marketing Utama berhasil diperbarui.');
        }

        if ($request->has('team_ids')) {
            $user->teams()->sync($validated['team_ids'] ?? []);
            $user->update(['team_id' => $validated['team_ids'][0] ?? null]);
        } else {
            $user->update(['team_id' => $validated['team_id']]);
        }

        return back()->with('success', 'Tim berhasil diubah.');
    }

    public function importForm(): View
    {
        return view('superadmin.import', [
            'teams' => Team::orderBy('name')->get(),
        ]);
    }

    public function import(Request $request, ContactImportService $contactImportService): RedirectResponse
    {
        $validated = $request->validate([
            'team_id' => ['required', Rule::exists('teams', 'id')],
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
            'leader_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', User::ROLE_LEADER))],
            'sub_leader_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', User::ROLE_SUB_LEADER))],
        ]);

        try {
            $rows = $contactImportService->extractRows($request->file('file'));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        if (empty($rows)) {
            return back()->withErrors(['file' => 'File tidak memiliki data. Pastikan file berisi setidaknya satu baris data selain header.']);
        }

        $summary = $contactImportService->importRows($rows, [
            'team_id' => $validated['team_id'],
            'input_by' => (int) Auth::id(),
            'sub_leader_id' => $validated['sub_leader_id'] ?? null,
            'leader_id' => $validated['leader_id'] ?? null,
            'imported_by_name' => Auth::user()->name,
        ]);

        return back()->with(
            'success',
            "Import selesai. Berhasil: {$summary['created']}, Duplikat: {$summary['skipped_duplicate']}, Tidak valid: {$summary['skipped_invalid']}."
        );
    }

    public function destroy(Request $request, User $user): RedirectResponse|JsonResponse
    {
        // Only allow deleting marketing users (leaders or assistants)
        if (! in_array($user->role, [User::ROLE_LEADER, User::ROLE_SUB_LEADER], true)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Hanya user marketing yang dapat dihapus.',
                ], 403);
            }

            return back()->withErrors(['user' => 'Hanya user marketing yang dapat dihapus.']);
        }

        // If deleting a marketing utama (leader), detach it from its sub-leaders and contacts
        if ($user->role === User::ROLE_LEADER) {
            User::where('leader_id', $user->id)->update(['leader_id' => null]);
            Contact::where('leader_id', $user->id)->update(['leader_id' => null]);
        }

        // If deleting an asisten marketing, detach it from contacts
        if ($user->role === User::ROLE_SUB_LEADER) {
            Contact::where('sub_leader_id', $user->id)->update(['sub_leader_id' => null]);
        }

        $user->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Data user berhasil dihapus.',
            ]);
        }

        return back()->with('success', 'User marketing berhasil dihapus.');
    }
}
