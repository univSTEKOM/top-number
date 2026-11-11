<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ContactImportService
{
    /**
     * @return array<int, array{contact_name: string|null, phone: string}>
     */
    public function extractRows(UploadedFile $file): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (! in_array($extension, ['csv', 'txt', 'xlsx', 'xls'], true)) {
            throw new \RuntimeException(
                "Format file tidak didukung: .{$extension}. Format yang diterima: .csv, .txt, .xlsx, .xls."
            );
        }

        $rawRows = match ($extension) {
            'csv', 'txt' => $this->extractCsvRows($file),
            'xlsx', 'xls' => $this->extractSpreadsheetRows($file),
        };

        return $this->normalizeRows($rawRows);
    }

    /**
     * @param  array<int, array{contact_name: string|null, phone: string}>  $rows
     * @param  array{team_id?:int,team_ids?:array<int>,input_by:int,sub_leader_id:?int,leader_id:?int,period_key?:string,marketing_channel?:string,require_company_name?:bool}  $context
     * @return array{created:int,skipped_duplicate:int,skipped_invalid:int,distribution:array<int,int>}
     */
    public function importRows(array $rows, array $context): array
    {
        $periodKey = $context['period_key'] ?? Contact::activePeriodKey();
        $dedupWeekKey = Contact::activeDedupWeekKey();
        $marketingChannel = $context['marketing_channel'] ?? User::MARKETING_CHANNEL_TOPLOKER;
        $requireCompanyName = (bool) ($context['require_company_name'] ?? false);

        // Duplicate check: scoped to the current week and channel.
        $existingNormalized = Contact::query()
            ->where('dedup_week', $dedupWeekKey)
            ->where('marketing_channel', $marketingChannel)
            ->whereNotNull('normalized_phone')
            ->pluck('normalized_phone')
            ->flip();

        $teamIds = $context['team_ids'] ?? (isset($context['team_id']) ? [$context['team_id']] : []);
        if (empty($teamIds) && $marketingChannel === User::MARKETING_CHANNEL_TOPLOKER) {
            throw new \InvalidArgumentException('team_ids or team_id must be provided for Toploker channel.');
        }

        $batchPhones = [];
        $created = 0;
        $skippedDuplicate = 0;
        $skippedInvalid = 0;

        $distributionCount = ! empty($teamIds) ? array_fill_keys($teamIds, 0) : [];
        $teamIndex = 0;
        $totalTeams = count($teamIds);

        foreach ($rows as $row) {
            $rawPhone = (string) ($row['phone'] ?? '');
            if (! PhoneNumber::isValid($rawPhone)) {
                $skippedInvalid++;

                continue;
            }

            $normalizedPhone = PhoneNumber::normalize($rawPhone);
            if ($normalizedPhone === null) {
                $skippedInvalid++;

                continue;
            }

            $contactName = isset($row['contact_name']) && trim((string) $row['contact_name']) !== ''
                ? trim((string) $row['contact_name'])
                : null;

            // If company name is required (TopMatch), skip if empty
            if ($requireCompanyName && ($contactName === null || $contactName === '')) {
                $skippedInvalid++;

                continue;
            }

            if (isset($existingNormalized[$normalizedPhone]) || isset($batchPhones[$normalizedPhone])) {
                $skippedDuplicate++;

                continue;
            }

            $currentTeamId = $totalTeams > 0 ? $teamIds[$teamIndex % $totalTeams] : null;

            Contact::create([
                'contact_name' => $contactName,
                'phone' => $normalizedPhone,
                'normalized_phone' => $normalizedPhone,
                'period_key' => $periodKey,
                'dedup_week' => $dedupWeekKey,
                'marketing_channel' => $marketingChannel,
                'team_id' => $currentTeamId,
                'sub_leader_id' => $context['sub_leader_id'] ?? null,
                'input_by' => $context['input_by'],
                'leader_id' => $context['leader_id'] ?? null,
                'status' => Contact::STATUS_UNCONTACTED,
            ]);

            $batchPhones[$normalizedPhone] = true;
            if ($currentTeamId !== null) {
                $distributionCount[$currentTeamId]++;
                $teamIndex++;
            }
            $created++;
        }

        return [
            'created' => $created,
            'skipped_duplicate' => $skippedDuplicate,
            'skipped_invalid' => $skippedInvalid,
            'distribution' => $distributionCount,
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function extractCsvRows(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if (! $handle) {
            throw new \RuntimeException('Gagal membaca file. Pastikan file tidak rusak dan dapat dibuka.');
        }

        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            $rows[] = $data;
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function extractSpreadsheetRows(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();

        return $sheet->toArray(null, true, true, false);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rawRows
     * @return array<int, array{contact_name: string|null, phone: string}>
     */
    private function normalizeRows(array $rawRows): array
    {
        if (empty($rawRows)) {
            return [];
        }

        $header = array_map(
            fn ($value) => strtolower(trim((string) $value)),
            $rawRows[0]
        );

        $phoneIndex = null;
        $nameIndex = null;

        foreach ($header as $index => $column) {
            if (in_array($column, ['phone', 'nomor', 'no hp', 'nohp', 'number', 'no_telp', 'notelp', 'telepon'], true)) {
                $phoneIndex = $index;
            }

            if (in_array($column, ['name', 'nama', 'contact_name', 'kontak', 'nama_perusahaan', 'nama perusahaan'], true)) {
                $nameIndex = $index;
            }
        }

        if ($phoneIndex === null) {
            $foundColumns = array_filter($header, fn ($col) => $col !== '');
            $foundDisplay = ! empty($foundColumns)
                ? implode(', ', $foundColumns)
                : '(tidak ada kolom terdeteksi)';

            throw new \RuntimeException(
                'Kolom nomor telepon tidak ditemukan. '.
                "Kolom yang ditemukan di file: {$foundDisplay}. ".
                'Kolom yang dibutuhkan (salah satu): phone, nomor, no hp, no_telp, atau number.'
            );
        }

        $rows = [];
        foreach (array_slice($rawRows, 1) as $row) {
            $rows[] = [
                'contact_name' => $nameIndex !== null ? (string) ($row[$nameIndex] ?? '') : null,
                'phone' => (string) ($row[$phoneIndex] ?? ''),
            ];
        }

        return $rows;
    }
}
