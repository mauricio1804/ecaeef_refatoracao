<?php

namespace Tests\Feature;

use App\Models\CourseClass;
use App\Models\Equipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EquipmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_inventory_page_is_accessible_by_authenticated_user(): void
    {
        $this->actingAs($this->user)
            ->get(route('inventory.index'))
            ->assertStatus(200);
    }

    public function test_inventory_page_is_not_accessible_by_guest(): void
    {
        $this->get(route('inventory.index'))
            ->assertRedirect(route('login'));
    }

    public function test_can_create_equipment_with_existing_class(): void
    {
        $courseClass = CourseClass::factory()->create();

        Livewire::actingAs($this->user)
            ->test('pages::inventory.index')
            ->set('newEquipmentName', 'Test Equipment')
            ->set('newEquipmentAsset', 'TEST-001')
            ->set('class_id', $courseClass->id)
            ->set('quantity', 5)
            ->call('saveNewEquipment');

        $this->assertDatabaseHas('equipments', [
            'name' => 'Test Equipment',
            'asset_number' => 'TEST-001',
        ]);

        $equipment = Equipment::where('asset_number', 'TEST-001')->first();

        $this->assertDatabaseHas('class_equipments', [
            'equipment_id' => $equipment->id,
            'class_id' => $courseClass->id,
            'quantity' => 5,
        ]);
    }

    public function test_can_create_equipment_with_new_class(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::inventory.index')
            ->set('newEquipmentName', 'New Eq')
            ->set('newEquipmentAsset', 'NEW-001')
            ->set('isCreatingClass', true)
            ->set('newClassName', 'New Class')
            ->set('quantity', 10)
            ->call('saveNewEquipment');

        $this->assertDatabaseHas('equipments', [
            'name' => 'New Eq',
            'asset_number' => 'NEW-001',
        ]);

        $this->assertDatabaseHas('classes', [
            'name' => 'New Class',
        ]);

        $equipment = Equipment::where('asset_number', 'NEW-001')->first();
        $courseClass = CourseClass::where('name', 'New Class')->first();

        $this->assertDatabaseHas('class_equipments', [
            'equipment_id' => $equipment->id,
            'class_id' => $courseClass->id,
            'quantity' => 10,
        ]);
    }

    public function test_can_update_equipment(): void
    {
        $equipment = Equipment::factory()->create(['name' => 'Old Name', 'asset_number' => 'OLD-001']);
        $courseClass = CourseClass::factory()->create();
        $equipment->classes()->attach($courseClass, ['quantity' => 1]);

        $assignment = $equipment->classes()->first()->pivot;

        Livewire::actingAs($this->user)
            ->test('pages::inventory.index')
            ->call('editEquipment', $equipment->id, $assignment->id)
            ->set('newEquipmentName', 'Updated Name')
            ->set('newEquipmentAsset', 'UPDATED-001')
            ->set('quantity', 20)
            ->call('updateEquipment');

        $this->assertDatabaseHas('equipments', [
            'id' => $equipment->id,
            'name' => 'Updated Name',
            'asset_number' => 'UPDATED-001',
        ]);

        $this->assertDatabaseHas('class_equipments', [
            'equipment_id' => $equipment->id,
            'class_id' => $courseClass->id,
            'quantity' => 20,
        ]);
    }

    public function test_can_delete_equipment(): void
    {
        $equipment = Equipment::factory()->create();

        Livewire::actingAs($this->user)
            ->test('pages::inventory.index')
            ->call('deleteEquipment', $equipment->id);

        $this->assertSoftDeleted('equipments', [
            'id' => $equipment->id,
        ]);
    }

    public function test_can_create_class(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::inventory.index')
            ->set('newClassName', 'Test Class')
            ->call('saveClass');

        $this->assertDatabaseHas('classes', [
            'name' => 'Test Class',
        ]);
    }

    public function test_can_update_class(): void
    {
        $courseClass = CourseClass::factory()->create(['name' => 'Old Class']);

        Livewire::actingAs($this->user)
            ->test('pages::inventory.index')
            ->call('editClass', $courseClass->id)
            ->set('newClassName', 'Updated Class')
            ->call('saveClass');

        $this->assertDatabaseHas('classes', [
            'id' => $courseClass->id,
            'name' => 'Updated Class',
        ]);
    }

    public function test_can_delete_class(): void
    {
        $courseClass = CourseClass::factory()->create();

        Livewire::actingAs($this->user)
            ->test('pages::inventory.index')
            ->call('deleteClass', $courseClass->id);

        $this->assertSoftDeleted('classes', [
            'id' => $courseClass->id,
        ]);
    }

    public function test_can_search_equipments(): void
    {
        $eq1 = Equipment::factory()->create(['name' => 'Matching Equipment']);
        $eq2 = Equipment::factory()->create(['name' => 'Other Equipment']);

        Livewire::actingAs($this->user)
            ->test('pages::inventory.index')
            ->set('search', 'Matching')
            ->assertSee('Matching Equipment')
            ->assertDontSee('Other Equipment');
    }
}
