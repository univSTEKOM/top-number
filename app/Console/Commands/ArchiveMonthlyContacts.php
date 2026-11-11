<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\ContactHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ArchiveMonthlyContacts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contacts:archive-monthly
                            {--dry-run : Show how many contacts would be archived without doing it}
                            {--period= : Override the archive_period (e.g. 2026-06). Defaults to the previous month.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Archive all active contacts into contact_histories and clear the contacts table.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Determine which period we are archiving.
        $currentPeriod = Contact::activePeriodKey();
        $periodOverride = $this->option('period');

        $query = Contact::query();
        if ($periodOverride) {
            $query->where('period_key', $periodOverride);
            $this->info("Archive target : period {$periodOverride}");
        } else {
            $query->where('period_key', '<', $currentPeriod);
            $this->info("Archive target : periods older than {$currentPeriod}");
        }

        $totalContacts = (clone $query)->count();

        $this->info("Contacts to archive: {$totalContacts}");

        if ($dryRun) {
            $this->comment('[Dry-run] No changes made.');
            return self::SUCCESS;
        }

        if ($totalContacts === 0) {
            $this->info('No old contacts to archive. Exiting.');
            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($query, $totalContacts): void {
                $this->info('Starting archival...');
                $archivedCount = 0;
                $now           = now();

                (clone $query)
                    ->orderBy('id')
                    ->chunkById(500, function ($contacts) use ($now, &$archivedCount): void {
                        $rows = [];

                        foreach ($contacts as $contact) {
                            $rows[] = [
                                'contact_id'             => $contact->id,
                                'contact_name'           => $contact->contact_name,
                                'phone'                  => $contact->phone,
                                'normalized_phone'       => $contact->normalized_phone,
                                'period_key'             => $contact->period_key,
                                'team_id'                => $contact->team_id,
                                'marketing_channel'      => $contact->marketing_channel ?? 'toploker',
                                'sub_leader_id'          => $contact->sub_leader_id,
                                'leader_id'              => $contact->leader_id,
                                'input_by'               => $contact->input_by,
                                'status'                 => $contact->status,
                                'is_contacted'           => $contact->is_contacted ? 1 : 0,
                                'status_updated_by'      => $contact->status_updated_by,
                                'status_updated_at'      => $contact->status_updated_at,
                                'contacted_at'           => $contact->contacted_at,
                                'contacted_by_leader_id' => $contact->contacted_by_leader_id,
                                'archive_period'         => date('Y-m', strtotime((string) $contact->created_at)), // Dynamically use the contact's period!
                                'archived_at'            => $now,
                                'original_created_at'    => $contact->created_at,
                                'created_at'             => $now,
                                'updated_at'             => $now,
                            ];
                        }

                        ContactHistory::insert($rows);
                        $archivedCount += count($rows);
                    });

                // Verify all contacts were archived
                $archivedInDb = ContactHistory::where('archived_at', '>=', $now)->count();

                if ($archivedInDb < $totalContacts) {
                    throw new \RuntimeException(
                        "Archival mismatch: expected {$totalContacts} archived records, found {$archivedInDb}."
                    );
                }

                // Safe to clear only the archived contacts
                (clone $query)->delete();

                $this->info("Successfully archived {$archivedCount} old contacts.");
                Log::info("contacts:archive-monthly: archived {$archivedCount} contacts.");
            });
        } catch (\Throwable $e) {
            $this->error("Archive failed: {$e->getMessage()}");
            Log::error("contacts:archive-monthly failed: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
