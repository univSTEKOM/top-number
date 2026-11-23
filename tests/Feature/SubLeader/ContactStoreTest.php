<?php

namespace Tests\Feature\SubLeader;

use App\Models\Contact;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactStoreTest extends TestCase
{
    use RefreshDatabase;

    private function createTeamUsers(): array
    {
        $team = Team::create(['name' => 'Tim Test']);

        $leader = User::factory()->create([
            'role' => User::ROLE_LEADER,
            'team_id' => $team->id,
            'leader_id' => null,
        ]);

        $subLeader = User::factory()->create([
            'role' => User::ROLE_SUB_LEADER,
            'team_id' => $team->id,
            'leader_id' => null,
        ]);
        $subLeader->teams()->attach($team->id);

        return [$team, $leader, $subLeader];
    }

    public function test_sub_leader_can_store_single_contact_with_company_phone_and_city(): void
    {
        [$team, , $subLeader] = $this->createTeamUsers();

        $response = $this->actingAs($subLeader)
            ->post(route('subleader.contacts.store'), [
                'company_name' => 'PT Sumber Makmur',
                'phone' => '081234567890',
                'city' => '  bekasi ',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('contacts', [
            'contact_name' => 'PT Sumber Makmur',
            'city' => 'Bekasi',
            'phone' => '6281234567890',
            'normalized_phone' => '6281234567890',
            'marketing_channel' => User::MARKETING_CHANNEL_TOPLOKER,
            'team_id' => $team->id,
            'sub_leader_id' => $subLeader->id,
            'input_by' => $subLeader->id,
            'leader_id' => null,
            'period_key' => Contact::activePeriodKey(),
        ]);
    }

    public function test_all_three_fields_are_required(): void
    {
        [, , $subLeader] = $this->createTeamUsers();

        $response = $this->actingAs($subLeader)
            ->post(route('subleader.contacts.store'), [
                'phone' => '081234567890',
            ]);

        $response->assertSessionHasErrors(['company_name', 'city']);
        $this->assertEquals(0, Contact::count());
    }

    public function test_province_name_is_rejected_as_city(): void
    {
        [, , $subLeader] = $this->createTeamUsers();

        $response = $this->actingAs($subLeader)
            ->post(route('subleader.contacts.store'), [
                'company_name' => 'PT Sumber Makmur',
                'phone' => '081234567890',
                'city' => 'Jawa Tengah',
            ]);

        $response->assertSessionHasErrors('city');
        $this->assertEquals(0, Contact::count());
    }

    public function test_sub_leader_can_store_batch_lines(): void
    {
        [, , $subLeader] = $this->createTeamUsers();

        Contact::create([
            'contact_name' => 'Existing',
            'phone' => '628111111001',
            'normalized_phone' => '628111111001',
            'period_key' => Contact::activePeriodKey(),
            'dedup_week' => Contact::activeDedupWeekKey(),
            'team_id' => $subLeader->team_id,
            'sub_leader_id' => $subLeader->id,
            'input_by' => $subLeader->id,
            'leader_id' => null,
        ]);

        $entries = implode("\n", [
            'PT Duplikat, 628111111001, Bekasi',   // duplicate
            'PT Maju, Tbk, 081234567890, Solo',     // company containing comma
            'CV Abadi; 628999888777; Semarang',
            'PT Provinsi, 628999888111, Jawa Barat', // province -> invalid
            'Tanpa Kota, 628999888222',              // missing city -> invalid
        ]);

        $response = $this->actingAs($subLeader)
            ->post(route('subleader.contacts.store'), ['entries' => $entries]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('contacts', [
            'contact_name' => 'PT Maju, Tbk',
            'city' => 'Solo',
            'normalized_phone' => '6281234567890',
        ]);
        $this->assertDatabaseHas('contacts', [
            'contact_name' => 'CV Abadi',
            'city' => 'Semarang',
            'normalized_phone' => '628999888777',
        ]);
        $this->assertEquals(3, Contact::count());
    }

    public function test_same_number_can_be_added_in_different_dedup_week(): void
    {
        [, , $subLeader] = $this->createTeamUsers();

        Contact::create([
            'phone' => '628123456789',
            'normalized_phone' => '628123456789',
            'period_key' => now()->subMonth()->format('Y-m'),
            'dedup_week' => now()->subWeek()->format('o-\WW'),
            'team_id' => $subLeader->team_id,
            'sub_leader_id' => $subLeader->id,
            'input_by' => $subLeader->id,
            'leader_id' => null,
        ]);

        $response = $this->actingAs($subLeader)
            ->post(route('subleader.contacts.store'), [
                'company_name' => 'PT Baru',
                'phone' => '628123456789',
                'city' => 'Semarang',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertEquals(2, Contact::count());
    }

    public function test_duplicate_detected_across_phone_formats_in_same_dedup_week(): void
    {
        [, , $subLeader] = $this->createTeamUsers();

        Contact::create([
            'phone' => '628123456789',
            'normalized_phone' => '628123456789',
            'period_key' => Contact::activePeriodKey(),
            'dedup_week' => Contact::activeDedupWeekKey(),
            'team_id' => $subLeader->team_id,
            'sub_leader_id' => $subLeader->id,
            'input_by' => $subLeader->id,
            'leader_id' => null,
        ]);

        $response = $this->actingAs($subLeader)
            ->post(route('subleader.contacts.store'), [
                'company_name' => 'PT Baru',
                'phone' => '08123456789',
                'city' => 'Semarang',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('phone');
        $this->assertEquals(1, Contact::count());
    }

    public function test_store_fails_without_team(): void
    {
        $subLeader = User::factory()->create([
            'role' => User::ROLE_SUB_LEADER,
            'team_id' => null,
            'leader_id' => null,
        ]);

        $response = $this->actingAs($subLeader)
            ->post(route('subleader.contacts.store'), [
                'company_name' => 'PT Baru',
                'phone' => '628111111999',
                'city' => 'Bekasi',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('phone');
        $this->assertEquals(0, Contact::count());
    }
}
