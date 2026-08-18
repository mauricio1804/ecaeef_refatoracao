<?php

namespace Tests\Feature;

use App\Enums\LoanItemStatus;
use App\Models\Equipment;
use App\Models\EquipmentLoan;
use App\Models\EquipmentLoanItem;
use App\Models\Loanee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EquipmentLoanTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_loans_page_is_accessible_by_authenticated_user(): void
    {
        $this->actingAs($this->user)
            ->get(route('loans.index'))
            ->assertStatus(200);
    }

    public function test_loans_page_is_not_accessible_by_guest(): void
    {
        $this->get(route('loans.index'))
            ->assertRedirect(route('login'));
    }

    public function test_can_create_loanee(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::loans.index')
            ->set('loaneeName', 'Carlos Silva')
            ->set('loaneeDocument', '111.222.333-44')
            ->set('loaneeContact', '(11) 99999-8888')
            ->call('saveLoanee');

        $this->assertDatabaseHas('loanees', [
            'name' => 'Carlos Silva',
            'document_number' => '111.222.333-44',
            'contact' => '(11) 99999-8888',
        ]);
    }

    public function test_can_update_loanee(): void
    {
        $loanee = Loanee::factory()->create([
            'name' => 'Nome Antigo',
            'contact' => '1234',
        ]);

        Livewire::actingAs($this->user)
            ->test('pages::loans.index')
            ->call('editLoanee', $loanee->id)
            ->set('loaneeName', 'Nome Atualizado')
            ->set('loaneeContact', '5678')
            ->call('saveLoanee');

        $this->assertDatabaseHas('loanees', [
            'id' => $loanee->id,
            'name' => 'Nome Atualizado',
            'contact' => '5678',
        ]);
    }

    public function test_can_delete_loanee(): void
    {
        $loanee = Loanee::factory()->create();

        Livewire::actingAs($this->user)
            ->test('pages::loans.index')
            ->call('deleteLoanee', $loanee->id);

        $this->assertSoftDeleted('loanees', [
            'id' => $loanee->id,
        ]);
    }

    public function test_can_create_equipment_loan_with_existing_loanee(): void
    {
        $loanee = Loanee::factory()->create();
        $eq1 = Equipment::factory()->create();
        $eq2 = Equipment::factory()->create();

        Livewire::actingAs($this->user)
            ->test('pages::loans.index')
            ->set('loanee_id', $loanee->id)
            ->set('loaned_at', '2026-08-18')
            ->set('returns_at', '2026-08-25')
            ->set('loanItems', [
                ['equipment_id' => $eq1->id, 'quantity' => 2],
                ['equipment_id' => $eq2->id, 'quantity' => 1],
            ])
            ->call('saveLoan');

        $this->assertDatabaseHas('equipment_loans', [
            'loanee_id' => $loanee->id,
            'loaned_at' => '2026-08-18 00:00:00',
            'returns_at' => '2026-08-25 00:00:00',
        ]);

        $loan = EquipmentLoan::where('loanee_id', $loanee->id)->first();

        $this->assertDatabaseHas('equipment_loan_items', [
            'loan_id' => $loan->id,
            'equipment_id' => $eq1->id,
            'quantity' => 2,
            'status' => LoanItemStatus::BORROWED->value,
        ]);

        $this->assertDatabaseHas('equipment_loan_items', [
            'loan_id' => $loan->id,
            'equipment_id' => $eq2->id,
            'quantity' => 1,
            'status' => LoanItemStatus::BORROWED->value,
        ]);
    }

    public function test_can_create_equipment_loan_with_new_loanee(): void
    {
        $eq = Equipment::factory()->create();

        Livewire::actingAs($this->user)
            ->test('pages::loans.index')
            ->set('isCreatingLoanee', true)
            ->set('newLoaneeName', 'Ana Souza')
            ->set('newLoaneeDocument', '999.888.777-66')
            ->set('newLoaneeContact', 'ana@email.com')
            ->set('loaned_at', '2026-08-18')
            ->set('loanItems', [
                ['equipment_id' => $eq->id, 'quantity' => 3],
            ])
            ->call('saveLoan');

        $this->assertDatabaseHas('loanees', [
            'name' => 'Ana Souza',
            'document_number' => '999.888.777-66',
        ]);

        $loanee = Loanee::where('name', 'Ana Souza')->first();

        $this->assertDatabaseHas('equipment_loans', [
            'loanee_id' => $loanee->id,
        ]);
    }

    public function test_can_update_item_status_and_return_all_items(): void
    {
        $loan = EquipmentLoan::factory()->create();
        $eq1 = Equipment::factory()->create();
        $eq2 = Equipment::factory()->create();

        $item1 = EquipmentLoanItem::factory()->create([
            'loan_id' => $loan->id,
            'equipment_id' => $eq1->id,
            'status' => LoanItemStatus::BORROWED,
        ]);

        $item2 = EquipmentLoanItem::factory()->create([
            'loan_id' => $loan->id,
            'equipment_id' => $eq2->id,
            'status' => LoanItemStatus::BORROWED,
        ]);

        Livewire::actingAs($this->user)
            ->test('pages::loans.index')
            ->call('updateItemStatus', $item1->id, LoanItemStatus::DAMAGED->value);

        $this->assertDatabaseHas('equipment_loan_items', [
            'id' => $item1->id,
            'status' => LoanItemStatus::DAMAGED->value,
        ]);

        Livewire::actingAs($this->user)
            ->test('pages::loans.index')
            ->call('returnAllItems', $loan->id);

        $this->assertDatabaseHas('equipment_loan_items', [
            'id' => $item2->id,
            'status' => LoanItemStatus::RETURNED->value,
        ]);
    }

    public function test_can_delete_equipment_loan(): void
    {
        $loan = EquipmentLoan::factory()->create();
        $eq = Equipment::factory()->create();
        $item = EquipmentLoanItem::factory()->create([
            'loan_id' => $loan->id,
            'equipment_id' => $eq->id,
        ]);

        Livewire::actingAs($this->user)
            ->test('pages::loans.index')
            ->call('deleteLoan', $loan->id);

        $this->assertSoftDeleted('equipment_loans', [
            'id' => $loan->id,
        ]);

        $this->assertSoftDeleted('equipment_loan_items', [
            'id' => $item->id,
        ]);
    }

    public function test_can_search_loans_by_loanee_and_equipment(): void
    {
        $loanee1 = Loanee::factory()->create(['name' => 'Maria Oliveira']);
        $loanee2 = Loanee::factory()->create(['name' => 'João Pereira']);

        $eq1 = Equipment::factory()->create(['name' => 'Caneleira 5kg']);
        $eq2 = Equipment::factory()->create(['name' => 'Cinto de Tração']);

        $loan1 = EquipmentLoan::factory()->create(['loanee_id' => $loanee1->id]);
        EquipmentLoanItem::factory()->create(['loan_id' => $loan1->id, 'equipment_id' => $eq1->id]);

        $loan2 = EquipmentLoan::factory()->create(['loanee_id' => $loanee2->id]);
        EquipmentLoanItem::factory()->create(['loan_id' => $loan2->id, 'equipment_id' => $eq2->id]);

        Livewire::actingAs($this->user)
            ->test('pages::loans.index')
            ->set('search', 'Maria')
            ->assertSee('Maria Oliveira')
            ->assertDontSee('João Pereira');

        Livewire::actingAs($this->user)
            ->test('pages::loans.index')
            ->set('search', 'Cinto')
            ->assertSee('João Pereira')
            ->assertDontSee('Maria Oliveira');
    }
}
