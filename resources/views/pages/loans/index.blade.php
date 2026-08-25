<?php

use App\Enums\LoanItemStatus;
use App\Models\ClassEquipment;
use App\Models\CourseClass;
use App\Models\Equipment;
use App\Models\EquipmentLoan;
use App\Models\EquipmentLoanItem;
use App\Models\Loanee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Empréstimos')] class extends Component {
    public string $tab = 'loans';
    public string $search = '';

    // Loan creation properties
    public ?int $loanee_id = null;
    public string $loaned_at = '';
    public string $returns_at = '';
    public bool $isCreatingLoanee = false;
    public string $newLoaneeName = '';
    public string $newLoaneeDocument = '';
    public string $newLoaneeContact = '';

    /**
     * Dynamic items array: [['equipment_id' => int|null, 'quantity' => int]]
     */
    public array $loanItems = [];

    // Loanee management properties
    public ?Loanee $editingLoanee = null;
    public string $loaneeName = '';
    public string $loaneeDocument = '';
    public string $loaneeContact = '';

    // Loan Return & Details modal
    public ?EquipmentLoan $selectedLoan = null;

    public function mount(): void
    {
        $this->loaned_at = now()->format('Y-m-d');
        $this->returns_at = now()->addDays(7)->format('Y-m-d');
        $this->resetLoanItems();
    }

    public function resetLoanItems(): void
    {
        $this->loanItems = [
            ['equipment_id' => '', 'quantity' => 1]
        ];
    }

    public function addLoanItem(): void
    {
        $this->loanItems[] = ['equipment_id' => '', 'quantity' => 1];
    }

    public function removeLoanItem(int $index): void
    {
        if (count($this->loanItems) > 1) {
            unset($this->loanItems[$index]);
            $this->loanItems = array_values($this->loanItems);
        }
    }

    public function toggleLoaneeCreation(): void
    {
        $this->isCreatingLoanee = !$this->isCreatingLoanee;
        $this->reset(['newLoaneeName', 'newLoaneeDocument', 'newLoaneeContact', 'loanee_id']);
    }

    public function createLoan(): void
    {
        $this->reset(['loanee_id', 'newLoaneeName', 'newLoaneeDocument', 'newLoaneeContact', 'isCreatingLoanee']);
        $this->loaned_at = now()->format('Y-m-d');
        $this->returns_at = now()->addDays(7)->format('Y-m-d');
        $this->resetLoanItems();
        $this->modal('loan-form')->show();
    }

    public function saveLoan(): void
    {
        $rules = [
            'loaned_at' => 'required|date',
            'returns_at' => 'nullable|date|after_or_equal:loaned_at',
            'loanItems' => 'required|array|min:1',
            'loanItems.*.equipment_id' => 'required|exists:equipments,id',
            'loanItems.*.quantity' => 'required|integer|min:1',
        ];

        if ($this->isCreatingLoanee) {
            $rules['newLoaneeName'] = 'required|string|max:255';
            $rules['newLoaneeDocument'] = 'nullable|string|max:255';
            $rules['newLoaneeContact'] = 'nullable|string|max:255';
        } else {
            $rules['loanee_id'] = 'required|exists:loanees,id';
        }

        $this->validate($rules);

        // Group total requested quantities by equipment
        $requestedTotals = [];
        foreach ($this->loanItems as $item) {
            $eqId = (int) $item['equipment_id'];
            $qty = (int) $item['quantity'];
            $requestedTotals[$eqId] = ($requestedTotals[$eqId] ?? 0) + $qty;
        }

        // Validate stock availability
        $hasStockError = false;
        foreach ($this->loanItems as $index => $item) {
            $eqId = (int) $item['equipment_id'];
            $available = (int) ClassEquipment::where('equipment_id', $eqId)->sum('quantity');
            $requested = $requestedTotals[$eqId];

            if ($requested > $available) {
                $equipment = Equipment::find($eqId);
                $name = $equipment ? $equipment->name : 'Equipamento';
                $this->addError("loanItems.{$index}.quantity", "Estoque insuficiente para '{$name}'. Disponível: {$available}.");
                $hasStockError = true;
            }
        }

        if ($hasStockError) {
            return;
        }

        DB::transaction(function () {
            // 1. Resolve Loanee
            $loaneeId = $this->loanee_id;
            if ($this->isCreatingLoanee) {
                $loanee = Loanee::create([
                    'name' => $this->newLoaneeName,
                    'document_number' => $this->newLoaneeDocument ?: null,
                    'contact' => $this->newLoaneeContact ?: null,
                ]);
                $loaneeId = $loanee->id;
            }

            // 2. Create Equipment Loan
            $loan = EquipmentLoan::create([
                'loanee_id' => $loaneeId,
                'loaned_at' => $this->loaned_at,
                'returns_at' => $this->returns_at ?: null,
            ]);

            // 3. Create Items and deduct stock
            foreach ($this->loanItems as $item) {
                $eqId = (int) $item['equipment_id'];
                $qty = (int) $item['quantity'];

                EquipmentLoanItem::create([
                    'loan_id' => $loan->id,
                    'equipment_id' => $eqId,
                    'quantity' => $qty,
                    'status' => LoanItemStatus::BORROWED,
                ]);

                $remaining = $qty;
                $assignments = ClassEquipment::where('equipment_id', $eqId)
                    ->where('quantity', '>', 0)
                    ->get();

                foreach ($assignments as $assignment) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $deduct = min($assignment->quantity, $remaining);
                    $assignment->decrement('quantity', $deduct);
                    $remaining -= $deduct;
                }
            }
        });

        $this->modal('loan-form')->close();
        $this->reset(['loanee_id', 'newLoaneeName', 'newLoaneeDocument', 'newLoaneeContact', 'isCreatingLoanee']);
        $this->resetLoanItems();
    }

    public function viewLoan(EquipmentLoan $loan): void
    {
        $this->selectedLoan = $loan->load(['loanee', 'items.equipment']);
        $this->modal('loan-details')->show();
    }

    public function updateItemStatus(int $itemId, int $statusValue): void
    {
        $item = EquipmentLoanItem::find($itemId);
        if (!$item) {
            return;
        }

        $newStatus = LoanItemStatus::tryFrom($statusValue);
        if ($newStatus === null || $item->status === $newStatus) {
            return;
        }

        $prevStatus = $item->status;

        DB::transaction(function () use ($item, $prevStatus, $newStatus) {
            // Se foi marcado como DEVOLVIDO a partir de outro status -> retorna ao estoque
            if ($prevStatus !== LoanItemStatus::RETURNED && $newStatus === LoanItemStatus::RETURNED) {
                $assignment = ClassEquipment::where('equipment_id', $item->equipment_id)->first();
                if ($assignment) {
                    $assignment->increment('quantity', $item->quantity);
                } else {
                    $defaultClass = CourseClass::first();
                    if ($defaultClass) {
                        ClassEquipment::create([
                            'equipment_id' => $item->equipment_id,
                            'class_id' => $defaultClass->id,
                            'quantity' => $item->quantity,
                        ]);
                    }
                }
            }
            // Se voltou de DEVOLVIDO para outro status -> deduz do estoque novamente
            elseif ($prevStatus === LoanItemStatus::RETURNED && $newStatus !== LoanItemStatus::RETURNED) {
                $remaining = (int) $item->quantity;
                $assignments = ClassEquipment::where('equipment_id', $item->equipment_id)
                    ->where('quantity', '>', 0)
                    ->get();

                foreach ($assignments as $assignment) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $deduct = min($assignment->quantity, $remaining);
                    $assignment->decrement('quantity', $deduct);
                    $remaining -= $deduct;
                }
            }

            $item->update(['status' => $newStatus]);
        });

        if ($this->selectedLoan) {
            $this->selectedLoan->refresh()->load(['loanee', 'items.equipment']);
        }
    }

    public function returnAllItems(int $loanId): void
    {
        $items = EquipmentLoanItem::where('loan_id', $loanId)
            ->where('status', '!=', LoanItemStatus::RETURNED)
            ->get();

        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                $assignment = ClassEquipment::where('equipment_id', $item->equipment_id)->first();
                if ($assignment) {
                    $assignment->increment('quantity', $item->quantity);
                } else {
                    $defaultClass = CourseClass::first();
                    if ($defaultClass) {
                        ClassEquipment::create([
                            'equipment_id' => $item->equipment_id,
                            'class_id' => $defaultClass->id,
                            'quantity' => $item->quantity,
                        ]);
                    }
                }

                $item->update(['status' => LoanItemStatus::RETURNED]);
            }
        });

        if ($this->selectedLoan) {
            $this->selectedLoan->refresh()->load(['loanee', 'items.equipment']);
        }
    }

    public function deleteLoan(EquipmentLoan $loan): void
    {
        DB::transaction(function () use ($loan) {
            foreach ($loan->items as $item) {
                if ($item->status !== LoanItemStatus::RETURNED) {
                    $assignment = ClassEquipment::where('equipment_id', $item->equipment_id)->first();
                    if ($assignment) {
                        $assignment->increment('quantity', $item->quantity);
                    }
                }
                $item->delete();
            }
            $loan->delete();
        });
    }

    public function createLoanee(): void
    {
        $this->editingLoanee = null;
        $this->loaneeName = '';
        $this->loaneeDocument = '';
        $this->loaneeContact = '';
        $this->modal('loanee-form')->show();
    }

    public function editLoanee(Loanee $loanee): void
    {
        $this->editingLoanee = $loanee;
        $this->loaneeName = $loanee->name;
        $this->loaneeDocument = $loanee->document_number ?? '';
        $this->loaneeContact = $loanee->contact ?? '';
        $this->modal('loanee-form')->show();
    }

    public function saveLoanee(): void
    {
        $this->validate([
            'loaneeName' => 'required|string|max:255',
            'loaneeDocument' => 'nullable|string|max:255',
            'loaneeContact' => 'nullable|string|max:255',
        ]);

        if ($this->editingLoanee) {
            $this->editingLoanee->update([
                'name' => $this->loaneeName,
                'document_number' => $this->loaneeDocument ?: null,
                'contact' => $this->loaneeContact ?: null,
            ]);
        } else {
            Loanee::create([
                'name' => $this->loaneeName,
                'document_number' => $this->loaneeDocument ?: null,
                'contact' => $this->loaneeContact ?: null,
            ]);
        }

        $this->modal('loanee-form')->close();
        $this->reset(['editingLoanee', 'loaneeName', 'loaneeDocument', 'loaneeContact']);
    }

    public function deleteLoanee(Loanee $loanee): void
    {
        $loanee->delete();
    }

    public function getLoansProperty(): Collection
    {
        return EquipmentLoan::with(['loanee', 'items.equipment'])
            ->when($this->search, function ($query) {
                $query->whereHas('loanee', function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('document_number', 'like', '%' . $this->search . '%')
                      ->orWhere('contact', 'like', '%' . $this->search . '%');
                })->orWhereHas('items.equipment', function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('asset_number', 'like', '%' . $this->search . '%');
                });
            })
            ->latest('loaned_at')
            ->get();
    }

    public function getLoaneesProperty(): Collection
    {
        return Loanee::withCount('loans')
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('document_number', 'like', '%' . $this->search . '%')
                      ->orWhere('contact', 'like', '%' . $this->search . '%');
            })
            ->orderBy('name')
            ->get();
    }

    public function getEquipmentsListProperty(): Collection
    {
        return Equipment::with('classes')->orderBy('name')->get();
    }
}; ?>

<section class="w-full">
    <div class="flex justify-between items-center mb-6">
        <div>
            <flux:heading size="xl">{{ __('Empréstimos de Equipamentos') }}</flux:heading>
            <flux:subheading>{{ __('Gerencie tomadores, empréstimos e devoluções de materiais.') }}</flux:subheading>
        </div>
        
        @if($tab === 'loans')
            <flux:button wire:click="createLoan()" variant="primary" icon="plus">{{ __('Novo Empréstimo') }}</flux:button>
        @else
            <flux:button wire:click="createLoanee()" variant="primary" icon="plus">{{ __('Cadastrar Tomador') }}</flux:button>
        @endif
    </div>

    <div class="flex items-center justify-between mb-6">
        <flux:radio.group wire:model.live="tab" variant="segmented">
            <flux:radio value="loans" icon="arrows-right-left">{{ __('Empréstimos') }}</flux:radio>
            <flux:radio value="loanees" icon="users">{{ __('Tomadores') }}</flux:radio>
        </flux:radio.group>

        <div class="w-72">
            <flux:input wire:model.live="search" placeholder="Pesquisar..." icon="magnifying-glass" size="sm" />
        </div>
    </div>

    @if ($tab === 'loans')
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Tomador') }}</flux:table.column>
                <flux:table.column>{{ __('Itens Emprestados') }}</flux:table.column>
                <flux:table.column>{{ __('Data Empréstimo') }}</flux:table.column>
                <flux:table.column>{{ __('Previsão Devolução') }}</flux:table.column>
                <flux:table.column align="center">{{ __('Status') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Ações') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->loans as $loan)
                    <flux:table.row :key="$loan->id">
                        <flux:table.cell font="medium">
                            <div>
                                <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $loan->loanee->name ?? '-' }}</div>
                                @if($loan->loanee?->contact)
                                    <div class="text-xs text-zinc-500">{{ $loan->loanee->contact }}</div>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1">
                                @foreach($loan->items as $item)
                                    <flux:badge size="xs" variant="subtle" color="{{ $item->status->color() }}">
                                        {{ $item->quantity }}x {{ $item->equipment->name ?? 'Item' }}
                                    </flux:badge>
                                @endforeach
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $loan->loaned_at ? $loan->loaned_at->format('d/m/Y') : '-' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($loan->returns_at)
                                <span class="{{ $loan->isOverdue() ? 'text-red-500 font-semibold' : '' }}">
                                    {{ $loan->returns_at->format('d/m/Y') }}
                                </span>
                            @else
                                <span class="text-zinc-400">-</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="center">
                            @if($loan->isFullyReturned())
                                <flux:badge size="sm" color="green" variant="pill">{{ __('Devolvido') }}</flux:badge>
                            @elseif($loan->isOverdue())
                                <flux:badge size="sm" color="red" variant="pill">{{ __('Atrasado') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="blue" variant="pill">{{ __('Em Aberto') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                <flux:button wire:click="viewLoan({{ $loan->id }})" variant="ghost" icon="eye" size="xs" title="Detalhes e Devolução" />
                                <flux:button wire:confirm="Excluir este registro de empréstimo?" wire:click="deleteLoan({{ $loan->id }})" variant="ghost" icon="trash" size="xs" color="danger" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        @if($this->loans->isEmpty())
            <div class="flex flex-col items-center justify-center p-12 border border-dashed border-zinc-200 rounded-lg mt-4 dark:border-zinc-700">
                <flux:icon icon="arrows-right-left" class="size-8 text-zinc-400 mb-2" />
                <flux:text variant="subtle">{{ __('Nenhum empréstimo encontrado.') }}</flux:text>
            </div>
        @endif

    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Nome') }}</flux:table.column>
                <flux:table.column>{{ __('Documento') }}</flux:table.column>
                <flux:table.column>{{ __('Contato') }}</flux:table.column>
                <flux:table.column align="center">{{ __('Empréstimos Realizados') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Ações') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->loanees as $loanee)
                    <flux:table.row :key="$loanee->id">
                        <flux:table.cell font="medium">{{ $loanee->name }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge variant="ghost" size="sm">{{ $loanee->document_number ?? '-' }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $loanee->contact ?? '-' }}</flux:table.cell>
                        <flux:table.cell align="center">
                            <flux:badge size="sm" color="zinc" variant="subtle">{{ $loanee->loans_count }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                <flux:button wire:click="editLoanee({{ $loanee->id }})" variant="ghost" icon="pencil-square" size="xs" />
                                <flux:button wire:confirm="Excluir este tomador?" wire:click="deleteLoanee({{ $loanee->id }})" variant="ghost" icon="trash" size="xs" color="danger" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        @if($this->loanees->isEmpty())
            <div class="flex flex-col items-center justify-center p-12 border border-dashed border-zinc-200 rounded-lg mt-4 dark:border-zinc-700">
                <flux:icon icon="users" class="size-8 text-zinc-400 mb-2" />
                <flux:text variant="subtle">{{ __('Nenhum tomador cadastrado.') }}</flux:text>
            </div>
        @endif
    @endif

    <!-- New Loan Modal -->
    <flux:modal name="loan-form" class="min-w-[600px]">
        <form wire:submit="saveLoan">
            <flux:heading size="lg">{{ __('Novo Empréstimo') }}</flux:heading>
            
            <div class="space-y-6 mt-6">
                <!-- Loanee Selection / Creation -->
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <flux:label>{{ __('Tomador / Solicitante') }}</flux:label>
                        <flux:button wire:click="toggleLoaneeCreation" variant="ghost" size="xs" color="{{ $isCreatingLoanee ? 'danger' : 'primary' }}">
                            {{ $isCreatingLoanee ? __('Selecionar Existente') : __('Novo Tomador') }}
                        </flux:button>
                    </div>

                    @if($isCreatingLoanee)
                        <div class="space-y-3 p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg border border-zinc-200 dark:border-zinc-700">
                            <flux:input wire:model="newLoaneeName" :label="__('Nome')" placeholder="Nome completo" required />
                            <div class="grid grid-cols-2 gap-3">
                                <flux:input wire:model="newLoaneeDocument" :label="__('CPF / Documento')" placeholder="Ex: 123.456.789-00" />
                                <flux:input wire:model="newLoaneeContact" :label="__('Contato / Telefone')" placeholder="Ex: (11) 98765-4321" />
                            </div>
                        </div>
                    @else
                        <flux:select wire:model="loanee_id" placeholder="Selecione o tomador..." required>
                            @foreach ($this->loanees as $loanee)
                                <flux:select.option :value="$loanee->id">{{ $loanee->name }} {{ $loanee->document_number ? "({$loanee->document_number})" : '' }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif
                </div>

                <!-- Dates -->
                <div class="grid grid-cols-2 gap-4">
                    <flux:input type="date" wire:model="loaned_at" :label="__('Data do Empréstimo')" required />
                    <flux:input type="date" wire:model="returns_at" :label="__('Previsão de Devolução')" />
                </div>

                <!-- Items to loan -->
                <div class="space-y-3 border-t pt-4 dark:border-zinc-700">
                    <div class="flex justify-between items-center">
                        <flux:label>{{ __('Itens a Emprestar') }}</flux:label>
                        <flux:button wire:click="addLoanItem" variant="ghost" size="xs" icon="plus" color="primary">
                            {{ __('Adicionar Item') }}
                        </flux:button>
                    </div>

                    @foreach($loanItems as $index => $item)
                        <div class="flex items-center gap-3 p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg border border-zinc-200 dark:border-zinc-700">
                            <div class="flex-1">
                                <flux:select wire:model="loanItems.{{ $index }}.equipment_id" placeholder="Selecione o equipamento..." required>
                                    @foreach ($this->equipmentsList as $equipment)
                                        <flux:select.option :value="$equipment->id">{{ $equipment->name }} ({{ $equipment->asset_number ?? '-' }}) - {{ __('Estoque') }}: {{ $equipment->classes->sum('pivot.quantity') }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>
                            <div class="w-24">
                                <flux:input type="number" wire:model="loanItems.{{ $index }}.quantity" min="1" placeholder="Qtd" required />
                            </div>
                            @if(count($loanItems) > 1)
                                <flux:button wire:click="removeLoanItem({{ $index }})" variant="ghost" icon="trash" size="sm" color="danger" />
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex mt-8 gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Salvar Empréstimo') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Loan Details & Return Modal -->
    <flux:modal name="loan-details" class="min-w-[600px]">
        @if($selectedLoan)
            <div class="space-y-6">
                <div class="flex justify-between items-start">
                    <div>
                        <flux:heading size="lg">{{ __('Detalhes do Empréstimo') }}</flux:heading>
                        <flux:subheading>{{ __('Tomador:') }} {{ $selectedLoan->loanee->name ?? '-' }} ({{ $selectedLoan->loanee->contact ?? 'Sem contato' }})</flux:subheading>
                    </div>
                    @if(!$selectedLoan->isFullyReturned())
                        <flux:button wire:click="returnAllItems({{ $selectedLoan->id }})" variant="primary" size="sm" icon="check">
                            {{ __('Devolver Todos') }}
                        </flux:button>
                    @endif
                </div>

                <div class="grid grid-cols-2 gap-4 p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg text-sm">
                    <div>
                        <span class="text-zinc-500">{{ __('Data Empréstimo:') }}</span>
                        <span class="font-medium ml-1">{{ $selectedLoan->loaned_at ? $selectedLoan->loaned_at->format('d/m/Y') : '-' }}</span>
                    </div>
                    <div>
                        <span class="text-zinc-500">{{ __('Previsão Devolução:') }}</span>
                        <span class="font-medium ml-1">{{ $selectedLoan->returns_at ? $selectedLoan->returns_at->format('d/m/Y') : '-' }}</span>
                    </div>
                </div>

                <div class="space-y-3">
                    <flux:heading size="sm">{{ __('Itens e Status de Devolução') }}</flux:heading>
                    
                    <div class="divide-y divide-zinc-200 dark:divide-zinc-700 border rounded-lg overflow-hidden dark:border-zinc-700">
                        @foreach($selectedLoan->items as $item)
                            <div class="p-3 flex items-center justify-between bg-white dark:bg-zinc-800">
                                <div>
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item->equipment->name ?? '-' }}</div>
                                    <div class="text-xs text-zinc-500">
                                        Patrimônio: {{ $item->equipment->asset_number ?? '-' }} | Quantidade: {{ $item->quantity }}
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <flux:badge size="sm" color="{{ $item->status->color() }}">
                                        {{ $item->status->label() }}
                                    </flux:badge>
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button variant="ghost" icon="chevron-down" size="xs" />
                                        <flux:menu>
                                            <flux:menu.item wire:click="updateItemStatus({{ $item->id }}, 0)">{{ __('Marcar como Emprestado') }}</flux:menu.item>
                                            <flux:menu.item wire:click="updateItemStatus({{ $item->id }}, 1)">{{ __('Marcar como Devolvido') }}</flux:menu.item>
                                            <flux:menu.item wire:click="updateItemStatus({{ $item->id }}, 2)">{{ __('Marcar como Danificado') }}</flux:menu.item>
                                            <flux:menu.item wire:click="updateItemStatus({{ $item->id }}, 3)">{{ __('Marcar como Extraviado') }}</flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-4">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Fechar') }}</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>

    <!-- Loanee Modal -->
    <flux:modal name="loanee-form" class="min-w-[450px]">
        <form wire:submit="saveLoanee">
            <flux:heading size="lg">{{ $editingLoanee ? __('Editar Tomador') : __('Novo Tomador') }}</flux:heading>
            
            <div class="space-y-4 mt-6">
                <flux:input wire:model="loaneeName" :label="__('Nome')" placeholder="Nome completo" required />
                <flux:input wire:model="loaneeDocument" :label="__('CPF / Documento')" placeholder="Ex: 123.456.789-00" />
                <flux:input wire:model="loaneeContact" :label="__('Contato / Telefone / E-mail')" placeholder="Ex: (11) 98765-4321" />
            </div>

            <div class="flex mt-8 gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Salvar') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
