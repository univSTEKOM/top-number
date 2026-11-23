<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_reset_marketing_user_password_to_default(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $leader = User::factory()->create([
            'role' => User::ROLE_LEADER,
            'password' => 'old-password-123',
        ]);

        $this->actingAs($superAdmin)
            ->patchJson(route('superadmin.users.reset-password', $leader))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertTrue(Hash::check('toploker@01', $leader->fresh()->password));
    }

    public function test_superadmin_cannot_reset_non_marketing_user_password(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $otherAdmin = User::factory()->create([
            'role' => User::ROLE_SUPERADMIN,
            'password' => 'old-password-123',
        ]);

        $this->actingAs($superAdmin)
            ->patchJson(route('superadmin.users.reset-password', $otherAdmin))
            ->assertForbidden();

        $this->assertTrue(Hash::check('old-password-123', $otherAdmin->fresh()->password));
    }

    public function test_non_superadmin_cannot_reset_password(): void
    {
        $leader = User::factory()->create(['role' => User::ROLE_LEADER]);
        $subLeader = User::factory()->create(['role' => User::ROLE_SUB_LEADER]);

        $this->actingAs($leader)
            ->patchJson(route('superadmin.users.reset-password', $subLeader))
            ->assertForbidden();
    }
}
