<?php

namespace Tests\Feature;

use App\Models\Poem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * إدارة مرجع المتون من لوحة المدير (S15) — إضافة فقط، مدير فقط.
 */
class AdminPoemTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'المدير', 'username' => 'admin1', 'password' => Hash::make('secret123'),
            'role' => User::ROLE_ADMIN,
        ]);

        $this->teacher = User::create([
            'name' => 'الأستاذ خالد', 'username' => 'khaled', 'password' => Hash::make('secret123'),
        ]);
    }

    /** @test */
    public function an_admin_can_add_a_new_poem(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/admin/poems', ['name' => 'الرحبية', 'bayt_count' => 100])
            ->assertCreated()
            ->assertJsonPath('data.name', 'الرحبية');

        $this->assertDatabaseHas('poems', ['name' => 'الرحبية', 'bayt_count' => 100]);
    }

    /** @test */
    public function a_teacher_cannot_add_a_poem(): void
    {
        $this->actingAs($this->teacher)
            ->postJson('/admin/poems', ['name' => 'الرحبية', 'bayt_count' => 100])
            ->assertForbidden();
    }

    /** @test */
    public function a_duplicate_poem_name_is_rejected(): void
    {
        $existing = Poem::first();

        $this->actingAs($this->admin)
            ->postJson('/admin/poems', ['name' => $existing->name, 'bayt_count' => 10])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function the_admin_poems_page_lists_the_five_fixed_poems(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/poems')
            ->assertOk()
            ->assertSee('تحفة الأطفال')
            ->assertSee('طيبة النشر');
    }
}
