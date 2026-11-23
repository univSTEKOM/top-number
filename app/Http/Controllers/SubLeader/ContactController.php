<?php

namespace App\Http\Controllers\SubLeader;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Team;
use App\Models\User;
use App\Services\ContactImportService;
use App\Support\City;
use App\Support\PhoneNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(): View
    {
        /** @var User $subLeader */
        $subLeader = auth()->user();
        $isTopmatch = $subLeader->isTopmatch();
        $periodKey = Contact::activePeriodKey();

        $query = Contact::where('sub_leader_id', $subLeader->id)
            ->where('period_key', $periodKey);

        if ($isTopmatch) {
            $query->where('marketing_channel', User::MARKETING_CHANNEL_TOPMATCH);
        } else {
            $query->where(function ($q) {
                $q->where('marketing_channel', User::MARKETING_CHANNEL_TOPLOKER)
                    ->orWhereNull('marketing_channel');
            });
        }

        $contactsCount = (clone $query)->count();
        $target = $subLeader->TARGET_SUB_LEADER;

        return view('subleader.contacts.index', [
            'contacts' => $query->latest()->paginate(20),
            'contactsCount' => $contactsCount,
            'target' => $target,
            'progress' => $target > 0 ? min(100, (int) round(($contactsCount / $target) * 100)) : 0,
            'isTopmatch' => $isTopmatch,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $subLeader = auth()->user();

        if ($subLeader->isTopmatch()) {
            return $this->storeTopmatch($request, $subLeader);
        }

        return $this->storeToploker($request, $subLeader);
    }

    private function storeTopmatch(Request $request, User $subLeader): RedirectResponse
    {
        $hasBatch = $request->filled('entries');

        if (! $hasBatch) {
            $validated = $request->validate([
                'company_name' => ['required', 'string', 'min:2', 'max:255'],
                'phone' => ['required', 'string', 'max:255'],
            ], [
                'company_name.required' => 'Nama perusahaan wajib diisi.',
                'company_name.min' => 'Nama perusahaan minimal 2 karakter.',
                'phone.required' => 'Nomor telepon wajib diisi.',
            ]);

            $rawPhone = trim($validated['phone']);
            if (! PhoneNumber::isValid($rawPhone)) {
                return back()
                    ->withErrors(['phone' => PhoneNumber::validationMessage()])
                    ->withInput();
            }

            $normalizedPhone = PhoneNumber::normalize($rawPhone);
            $dedupWeekKey = Contact::activeDedupWeekKey();

            $duplicateExists = Contact::query()
                ->where('dedup_week', $dedupWeekKey)
                ->where('marketing_channel', User::MARKETING_CHANNEL_TOPMATCH)
                ->where('normalized_phone', $normalizedPhone)
                ->exists();

            if ($duplicateExists) {
                return back()
                    ->withErrors(['phone' => 'Nomor telepon ini sudah diinput pada minggu ini untuk channel Topmatch.'])
                    ->withInput();
            }

            Contact::create([
                'contact_name' => trim($validated['company_name']),
                'phone' => $normalizedPhone,
                'normalized_phone' => $normalizedPhone,
                'period_key' => Contact::activePeriodKey(),
                'dedup_week' => $dedupWeekKey,
                'marketing_channel' => User::MARKETING_CHANNEL_TOPMATCH,
                'team_id' => null,
                'sub_leader_id' => $subLeader->id,
                'input_by' => $subLeader->id,
                'leader_id' => $subLeader->leader_id ?? null,
                'status' => Contact::STATUS_UNCONTACTED,
            ]);

            return back()->with('success', 'Kontak Topmatch berhasil disimpan.');
        }

        $rawLines = preg_split('/\r\n|\r|\n/', trim($request->string('entries')->toString())) ?: [];
        $parsedEntries = [];
        $invalidCount = 0;

        foreach ($rawLines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = preg_split('/[,;|]/', $line, 2);
            if (count($parts) < 2) {
                $invalidCount++;

                continue;
            }

            $companyName = trim($parts[0]);
            $rawPhone = trim($parts[1]);

            if ($companyName === '' || mb_strlen($companyName) > 255 || ! PhoneNumber::isValid($rawPhone)) {
                $invalidCount++;

                continue;
            }

            $normalizedPhone = PhoneNumber::normalize($rawPhone);
            if ($normalizedPhone === null) {
                $invalidCount++;

                continue;
            }

            $parsedEntries[] = [
                'company_name' => $companyName,
                'phone' => $normalizedPhone,
            ];
        }

        if (empty($parsedEntries)) {
            return back()
                ->withErrors(['entries' => 'Format baris tidak valid. Pastikan setiap baris berisi: Nama Perusahaan, Nomor HP.'])
                ->withInput();
        }

        $dedupWeekKey = Contact::activeDedupWeekKey();
        $phonesToCheck = array_column($parsedEntries, 'phone');

        $existingNormalized = Contact::query()
            ->where('dedup_week', $dedupWeekKey)
            ->where('marketing_channel', User::MARKETING_CHANNEL_TOPMATCH)
            ->whereIn('normalized_phone', $phonesToCheck)
            ->pluck('normalized_phone')
            ->flip();

        $batchPhones = [];
        $rowsToInsert = [];
        $skippedDuplicate = 0;

        foreach ($parsedEntries as $entry) {
            $normalizedPhone = $entry['phone'];

            if (isset($existingNormalized[$normalizedPhone]) || isset($batchPhones[$normalizedPhone])) {
                $skippedDuplicate++;

                continue;
            }

            $rowsToInsert[] = [
                'contact_name' => $entry['company_name'],
                'phone' => $normalizedPhone,
                'normalized_phone' => $normalizedPhone,
                'period_key' => Contact::activePeriodKey(),
                'dedup_week' => $dedupWeekKey,
                'marketing_channel' => User::MARKETING_CHANNEL_TOPMATCH,
                'team_id' => null,
                'sub_leader_id' => $subLeader->id,
                'input_by' => $subLeader->id,
                'leader_id' => $subLeader->leader_id ?? null,
                'status' => Contact::STATUS_UNCONTACTED,
            ];

            $batchPhones[$normalizedPhone] = true;
        }

        $created = Contact::bulkInsertIgnoringDuplicates($rowsToInsert);
        $skippedDuplicate += count($rowsToInsert) - $created;

        $total = count($parsedEntries) + $invalidCount;

        return back()->with(
            'success',
            "Input massal Topmatch selesai. Total: {$total}, Berhasil: {$created}, Duplikat: {$skippedDuplicate}, Tidak valid: {$invalidCount}."
        );
    }

    private function storeToploker(Request $request, User $subLeader): RedirectResponse
    {
        $hasBatch = $request->filled('entries');
        $errorField = $hasBatch ? 'entries' : 'phone';

        if ($hasBatch) {
            $request->validate([
                'entries' => ['required', 'string', 'max:1000000'],
            ]);
        } else {
            $request->validate([
                'company_name' => ['required', 'string', 'min:2', 'max:255'],
                'phone' => ['required', 'string', 'max:255'],
                'city' => ['required', 'string', 'min:2', 'max:100'],
            ], [
                'company_name.required' => 'Nama perusahaan wajib diisi.',
                'company_name.min' => 'Nama perusahaan minimal 2 karakter.',
                'phone.required' => 'Nomor telepon wajib diisi.',
                'city.required' => 'Kota/Wilayah wajib diisi.',
                'city.min' => 'Kota/Wilayah minimal 2 karakter.',
            ]);
        }

        $teamIds = $subLeader->teams()->pluck('teams.id')->toArray();

        if (empty($teamIds)) {
            return back()
                ->withErrors([$errorField => 'Akun belum memiliki tim. Hubungi superadmin untuk assign tim.'])
                ->withInput();
        }

        if ($hasBatch) {
            [$entries, $invalidCount] = $this->parseTopLokerEntries($request->string('entries')->toString());

            if (empty($entries)) {
                return back()
                    ->withErrors(['entries' => 'Format baris tidak valid. Pastikan setiap baris berisi: Nama Perusahaan, Nomor HP, Kota (bukan provinsi).'])
                    ->withInput();
            }
        } else {
            $rawPhone = trim($request->string('phone')->toString());
            if (! PhoneNumber::isValid($rawPhone)) {
                return back()->withErrors(['phone' => PhoneNumber::validationMessage()])->withInput();
            }

            $rawCity = $request->string('city')->toString();
            if (! City::isValid($rawCity)) {
                return back()->withErrors(['city' => City::validationMessage()])->withInput();
            }

            $entries = [[
                'company_name' => trim($request->string('company_name')->toString()),
                'phone' => PhoneNumber::normalize($rawPhone),
                'city' => City::normalize($rawCity),
            ]];
            $invalidCount = 0;
        }

        $dedupWeekKey = Contact::activeDedupWeekKey();
        // Duplicate check: scoped to current week and toploker channel
        $existingNormalized = Contact::query()
            ->where('dedup_week', $dedupWeekKey)
            ->where(function ($q) {
                $q->where('marketing_channel', User::MARKETING_CHANNEL_TOPLOKER)
                    ->orWhereNull('marketing_channel');
            })
            ->whereIn('normalized_phone', array_column($entries, 'phone'))
            ->pluck('normalized_phone')
            ->flip();

        if (! $hasBatch && isset($existingNormalized[$entries[0]['phone']])) {
            return back()
                ->withErrors(['phone' => 'Nomor telepon ini sudah diinput pada minggu ini.'])
                ->withInput();
        }

        $batchPhones = [];
        $rowsToInsert = [];
        $skippedDuplicate = 0;

        $totalTeams = count($teamIds);
        $teamIndex = 0;
        $distributionCount = array_fill_keys($teamIds, 0);

        foreach ($entries as $entry) {
            $normalizedPhone = $entry['phone'];

            if (isset($existingNormalized[$normalizedPhone]) || isset($batchPhones[$normalizedPhone])) {
                $skippedDuplicate++;

                continue;
            }

            $currentTeamId = $teamIds[$teamIndex % $totalTeams];
            $rowsToInsert[] = $this->contactAttributes($subLeader, $normalizedPhone, $entry['company_name'], $entry['city'], $currentTeamId);

            $batchPhones[$normalizedPhone] = true;
            $distributionCount[$currentTeamId]++;
            $teamIndex++;
        }

        $created = Contact::bulkInsertIgnoringDuplicates($rowsToInsert);
        $skippedDuplicate += count($rowsToInsert) - $created;

        $distributionText = '';
        if (! empty($distributionCount)) {
            $teamNames = Team::whereIn('id', array_keys($distributionCount))->pluck('name', 'id');
            $distParts = [];
            foreach ($distributionCount as $tid => $count) {
                if ($count > 0) {
                    $tname = $teamNames[$tid] ?? "Tim {$tid}";
                    $distParts[] = "{$tname}: {$count}";
                }
            }
            if (! empty($distParts)) {
                $distributionText = ' | Distribusi: '.implode(', ', $distParts);
            }
        }

        if (! $hasBatch) {
            if ($created === 0) {
                return back()
                    ->withErrors(['phone' => 'Nomor telepon ini sudah diinput pada minggu ini.'])
                    ->withInput();
            }

            return back()->with('success', 'Kontak berhasil disimpan.'.$distributionText);
        }

        $totalInput = count($entries) + $invalidCount;

        return back()->with(
            'success',
            "Input selesai. Total: {$totalInput}, Berhasil: {$created}, Duplikat: {$skippedDuplicate}, Tidak valid: {$invalidCount}.".$distributionText
        );
    }

    public function import(Request $request, ContactImportService $contactImportService): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:20480'],
        ]);

        $subLeader = auth()->user();

        if ($subLeader->isTopmatch()) {
            try {
                $rows = $contactImportService->extractRows($request->file('file'));
            } catch (\RuntimeException $e) {
                return back()->withErrors(['file' => $e->getMessage()]);
            }

            if (empty($rows)) {
                return back()->withErrors(['file' => 'File tidak memiliki data. Pastikan file berisi setidaknya satu baris data selain header.']);
            }

            $summary = $contactImportService->importRows($rows, [
                'marketing_channel' => User::MARKETING_CHANNEL_TOPMATCH,
                'require_company_name' => true,
                'team_ids' => [],
                'input_by' => $subLeader->id,
                'sub_leader_id' => $subLeader->id,
                'leader_id' => $subLeader->leader_id ?? null,
            ]);

            return back()->with(
                'success',
                'Import Topmatch selesai. Total: '.count($rows).", Berhasil: {$summary['created']}, Duplikat: {$summary['skipped_duplicate']}, Tidak valid / tanpa nama perusahaan: {$summary['skipped_invalid']}."
            );
        }

        $teamIds = $subLeader->teams()->pluck('teams.id')->toArray();

        if (empty($teamIds)) {
            return back()->withErrors([
                'file' => 'Akun belum memiliki tim. Hubungi superadmin untuk assign tim.',
            ]);
        }

        try {
            $rows = $contactImportService->extractRows($request->file('file'));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        if (empty($rows)) {
            return back()->withErrors(['file' => 'File tidak memiliki data. Pastikan file berisi setidaknya satu baris data selain header.']);
        }

        $summary = $contactImportService->importRows($rows, [
            'marketing_channel' => User::MARKETING_CHANNEL_TOPLOKER,
            'require_company_name' => true,
            'require_city' => true,
            'team_ids' => $teamIds,
            'input_by' => $subLeader->id,
            'sub_leader_id' => $subLeader->id,
            'leader_id' => null,
        ]);

        $distributionText = '';
        if (isset($summary['distribution']) && ! empty($summary['distribution'])) {
            $teamNames = Team::whereIn('id', array_keys($summary['distribution']))->pluck('name', 'id');
            $distParts = [];
            foreach ($summary['distribution'] as $tid => $count) {
                if ($count > 0) {
                    $tname = $teamNames[$tid] ?? "Tim {$tid}";
                    $distParts[] = "{$tname}: {$count}";
                }
            }
            if (! empty($distParts)) {
                $distributionText = ' | Distribusi: '.implode(', ', $distParts);
            }
        }

        return back()->with(
            'success',
            'Import selesai. Total: '.count($rows).", Berhasil: {$summary['created']}, Duplikat: {$summary['skipped_duplicate']}, Tidak valid / data tidak lengkap: {$summary['skipped_invalid']}.".$distributionText
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function contactAttributes(User $subLeader, string $normalizedPhone, string $companyName, string $city, int $teamId): array
    {
        return [
            'contact_name' => $companyName,
            'city' => $city,
            'phone' => $normalizedPhone,
            'normalized_phone' => $normalizedPhone,
            'period_key' => Contact::activePeriodKey(),
            'dedup_week' => Contact::activeDedupWeekKey(),
            'marketing_channel' => User::MARKETING_CHANNEL_TOPLOKER,
            'team_id' => $teamId,
            'sub_leader_id' => $subLeader->id,
            'input_by' => $subLeader->id,
            'leader_id' => null,
            'status' => Contact::STATUS_UNCONTACTED,
        ];
    }

    /**
     * Parse batch lines in the format "Nama Perusahaan, Nomor HP, Kota".
     * Separators: comma, semicolon, or pipe. The last part is the city and the
     * second-to-last is the phone, so company names containing commas
     * (e.g. "PT Maju, Tbk") are still parsed correctly.
     *
     * @return array{0: array<int, array{company_name: string, phone: string, city: string}>, 1: int}
     */
    private function parseTopLokerEntries(string $value): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($value)) ?: [];

        $entries = [];
        $invalidCount = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', preg_split('/[,;|]/', $line) ?: []);
            if (count($parts) < 3) {
                $invalidCount++;

                continue;
            }

            $rawCity = array_pop($parts);
            $rawPhone = array_pop($parts);
            $companyName = trim(implode(', ', $parts));

            if (mb_strlen($companyName) < 2 || mb_strlen($companyName) > 255 || ! PhoneNumber::isValid($rawPhone) || ! City::isValid($rawCity)) {
                $invalidCount++;

                continue;
            }

            $entries[] = [
                'company_name' => $companyName,
                'phone' => PhoneNumber::normalize($rawPhone),
                'city' => City::normalize($rawCity),
            ];
        }

        return [$entries, $invalidCount];
    }
}
