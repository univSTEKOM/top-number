<?php

namespace Tests\Feature\SubLeader;

use App\Models\Contact;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ContactImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_sub_leader_can_import_contacts_from_csv(): void
    {
        $team = Team::create(['name' => 'Tim Import']);

        $subLeader = User::factory()->create([
            'role' => User::ROLE_SUB_LEADER,
            'team_id' => $team->id,
            'leader_id' => null,
        ]);
        $subLeader->teams()->attach($team->id);

        Contact::create([
            'contact_name' => 'Existing',
            'phone' => '628111111001',
            'normalized_phone' => '628111111001',
            'period_key' => Contact::activePeriodKey(),
            'dedup_week' => Contact::activeDedupWeekKey(),
            'team_id' => $team->id,
            'sub_leader_id' => $subLeader->id,
            'input_by' => $subLeader->id,
            'leader_id' => null,
        ]);

        $csv = implode("\n", [
            'nama_perusahaan,phone,kota',
            'PT Budi,628111111002,bekasi',
            'PT Siti,628111111001,Solo',
            'PT Invalid,ABCDEF,Solo',
            'PT Rani,628111111003,Semarang',
            'PT DupInFile,628111111003,Semarang',
            'PT Provinsi,628111111004,Jawa Tengah',
            ',628111111005,Solo',
            'PT TanpaKota,628111111006,',
        ]);

        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

        $response = $this->actingAs($subLeader)
            ->post(route('subleader.contacts.import'), [
                'file' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertEquals(3, Contact::count());
        $this->assertDatabaseHas('contacts', [
            'contact_name' => 'PT Budi',
            'city' => 'Bekasi',
            'normalized_phone' => '628111111002',
        ]);
    }

    public function test_import_fails_without_team(): void
    {
        $subLeader = User::factory()->create([
            'role' => User::ROLE_SUB_LEADER,
            'team_id' => null,
            'leader_id' => null,
        ]);

        $csv = implode("\n", [
            'name,phone',
            'Budi,628111111999',
        ]);

        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

        $response = $this->actingAs($subLeader)
            ->post(route('subleader.contacts.import'), [
                'file' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('file');
        $this->assertEquals(0, Contact::count());
    }

    public function test_import_still_saves_contacts_when_target_is_already_reached(): void
    {
        $team = Team::create(['name' => 'Tim Import']);

        $subLeader = User::factory()->create([
            'role' => User::ROLE_SUB_LEADER,
            'team_id' => $team->id,
            'leader_id' => null,
        ]);
        $subLeader->teams()->attach($team->id);

        for ($index = 1; $index <= User::TARGET_SUB_LEADER; $index++) {
            Contact::create([
                'phone' => '628111111'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'normalized_phone' => '628111111'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'period_key' => Contact::activePeriodKey(),
                'team_id' => $team->id,
                'sub_leader_id' => $subLeader->id,
                'input_by' => $subLeader->id,
                'leader_id' => null,
            ]);
        }

        $csv = implode("\n", [
            'nama_perusahaan,phone,kota',
            'PT OverTarget,628333333333,Bekasi',
        ]);

        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

        $response = $this->actingAs($subLeader)
            ->post(route('subleader.contacts.import'), [
                'file' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('contacts', [
            'phone' => '628333333333',
            'normalized_phone' => '628333333333',
            'team_id' => $team->id,
            'sub_leader_id' => $subLeader->id,
            'input_by' => $subLeader->id,
            'period_key' => Contact::activePeriodKey(),
        ]);
        $this->assertEquals(User::TARGET_SUB_LEADER + 1, Contact::count());
    }
}
