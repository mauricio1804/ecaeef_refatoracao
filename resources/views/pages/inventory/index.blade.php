<?php

use App\Models\ClassEquipment;
use App\Models\CourseClass;
use App\Models\Equipment;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Collection;

new #[Title('Equipamentos')] class extends Component {
    public string $search = '';

    // Form properties (for assignment)
    public ?ClassEquipment $editingAssignment = null;
    public ?int $equipment_id = null;
    public ?int $class_id = null;
    public int $quantity = 1;

    // Creation toggles
    public bool $isCreatingEquipment = false;
    public bool $isCreatingClass = false;

    // New Entity Properties
    public string $newEquipmentName = '';
    public string $newEquipmentAsset = '';
    public string $newClassName = '';

    public function rules()
    {
        $rules = [
            'quantity' => 'required|integer|min:1',
        ];

        if ($this->isCreatingEquipment) {
            $rules['newEquipmentName'] = 'required|string|max:255';
            $rules['newEquipmentAsset'] = 'required|string|max:255|unique:equipments,asset_number';
        } else {
            $rules['equipment_id'] = 'required|exists:equipments,id';
        }

        if ($this->isCreatingClass) {
            $rules['newClassName'] = 'required|string|max:255';
        } else {
            $rules['class_id'] = 'required|exists:classes,id';
        }

        return $rules;
    }

    public function toggleEquipmentCreation()
    {
        $this->isCreatingEquipment = !$this->isCreatingEquipment;
        $this->reset(['newEquipmentName', 'newEquipmentAsset', 'equipment_id']);
    }

    public function toggleClassCreation()
    {
        $this->isCreatingClass = !$this->isCreatingClass;
        $this->reset(['newClassName', 'class_id']);
    }

    public function createAssignment(?int $equipmentId = null)
    {
        $this->editingAssignment = null;
        $this->isCreatingEquipment = false;
        $this->isCreatingClass = false;
        $this->reset(['equipment_id', 'class_id', 'quantity', 'newEquipmentName', 'newEquipmentAsset', 'newClassName']);
        
        if ($equipmentId) {
            $this->equipment_id = $equipmentId;
        }
        
        $this->modal('assignment-form')->show();
    }

    public function editAssignment(ClassEquipment $assignment)
    {
        $this->editingAssignment = $assignment;
        $this->equipment_id = $assignment->equipment_id;
        $this->class_id = $assignment->class_id;
        $this->quantity = $assignment->quantity;
        $this->isCreatingEquipment = false;
        $this->isCreatingClass = false;
        $this->modal('assignment-form')->show();
    }

    public function save()
    {
        $this->validate();

        // 1. Handle New Equipment
        if ($this->isCreatingEquipment) {
            $equipment = Equipment::create([
                'name' => $this->newEquipmentName,
                'asset_number' => $this->newEquipmentAsset,
            ]);
            $this->equipment_id = $equipment->id;
        }

        // 2. Handle New Class
        if ($this->isCreatingClass) {
            $class = CourseClass::create([
                'name' => $this->newClassName,
            ]);
            $this->class_id = $class->id;
        }

        // 3. Save Assignment
        if ($this->editingAssignment) {
            $this->editingAssignment->update([
                'equipment_id' => $this->equipment_id,
                'class_id' => $this->class_id,
                'quantity' => $this->quantity,
            ]);
        } else {
            $existing = ClassEquipment::where('equipment_id', $this->equipment_id)
                ->where('class_id', $this->class_id)
                ->first();

            if ($existing) {
                $existing->update(['quantity' => $existing->quantity + $this->quantity]);
            } else {
                ClassEquipment::create([
                    'equipment_id' => $this->equipment_id,
                    'class_id' => $this->class_id,
                    'quantity' => $this->quantity,
                ]);
            }
        }

        $this->modal('assignment-form')->close();
        $this->reset(['equipment_id', 'class_id', 'quantity', 'editingAssignment', 'isCreatingEquipment', 'isCreatingClass', 'newEquipmentName', 'newEquipmentAsset', 'newClassName']);
    }

    public function deleteAssignment(ClassEquipment $assignment)
    {
        $assignment->delete();
    }

    public function deleteEquipment(Equipment $equipment)
    {
        $equipment->delete();
    }

    public function getEquipmentsListProperty(): Collection
    {
        $equipments = Equipment::with(['classes'])
            ->when($this->search, function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('asset_number', 'like', '%' . $this->search . '%')
                  ->orWhereHas('classes', fn($query) => $query->where('name', 'like', '%' . $this->search . '%'));
            })
            ->latest()
            ->get();

        $rows = collect();
        foreach ($equipments as $equipment) {
            if ($equipment->classes->isEmpty()) {
                $rows->push((object)[
                    'id' => 'eq-' . $equipment->id,
                    'equipment' => $equipment,
                    'assignment' => null,
                    'class_name' => '-',
                    'quantity' => 0,
                ]);
            } else {
                foreach ($equipment->classes as $class) {
                    $assignment = ClassEquipment::where('equipment_id', $equipment->id)
                        ->where('class_id', $class->id)
                        ->first();
                    $rows->push((object)[
                        'id' => 'as-' . ($assignment?->id ?? uniqid()),
                        'equipment' => $equipment,
                        'assignment' => $assignment,
                        'class_name' => $class->name,
                        'quantity' => $class->pivot->quantity,
                    ]);
                }
            }
        }
        return $rows;
    }

    public function getEquipmentsProperty(): Collection
    {
        return Equipment::orderBy('name')->get();
    }

    public function getClassesProperty(): Collection
    {
        return CourseClass::orderBy('name')->get();
    }
}; ?>

<section class="w-full">
    <div class="flex justify-between items-center mb-6">
        <div>
            <flux:heading size="xl">{{ __('Equipamentos') }}</flux:heading>
            <flux:subheading>{{ __('Gerencie todos os equipamentos e suas distribuições detalhadas.') }}</flux:subheading>
        </div>
        <flux:button wire:click="createAssignment()" variant="primary" icon="plus">{{ __('Novo Vínculo / Equipamento') }}</flux:button>
    </div>

    <div class="mb-4">
        <flux:input wire:model.live="search" placeholder="Pesquisar por equipamento, identificador ou classe..." icon="magnifying-glass" />
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Equipamento') }}</flux:table.column>
            <flux:table.column>{{ __('Identificador') }}</flux:table.column>
            <flux:table.column>{{ __('Classe') }}</flux:table.column>
            <flux:table.column align="center">{{ __('Quantidade') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Ações') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->equipmentsList as $row)
                <flux:table.row :key="$row->id">
                    <flux:table.cell font="medium">
                        {{ $row->equipment->name }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge variant="ghost" size="sm">{{ $row->equipment->asset_number ?? '-' }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if($row->assignment)
                            {{ $row->class_name }}
                        @else
                            <flux:badge size="xs" color="orange" variant="ghost">{{ __('Sem vínculo') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="center">
                        @if($row->assignment)
                            <span class="font-semibold">{{ $row->quantity }}</span>
                        @else
                            <span class="text-zinc-400">-</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <div class="flex justify-end gap-2">
                            @if($row->assignment)
                                <flux:button wire:click="editAssignment({{ $row->assignment->id }})" variant="ghost" icon="pencil" size="xs" />
                                <flux:button wire:confirm="Remover vínculo?" wire:click="deleteAssignment({{ $row->assignment->id }})" variant="ghost" icon="trash" size="xs" color="danger" />
                            @else
                                <flux:button wire:click="createAssignment({{ $row->equipment->id }})" variant="ghost" size="xs" icon="plus">{{ __('Vincular') }}</flux:button>
                                <flux:button wire:confirm="Excluir equipamento?" wire:click="deleteEquipment({{ $row->equipment->id }})" variant="ghost" icon="trash" size="xs" color="danger" />
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    @if($this->equipmentsList->isEmpty())
        <div class="flex flex-col items-center justify-center p-12 border border-dashed border-zinc-200 rounded-lg mt-4">
            <flux:icon icon="layout-grid" class="size-8 text-zinc-400 mb-2" />
            <flux:text color="zinc">{{ __('Nenhum equipamento encontrado.') }}</flux:text>
        </div>
    @endif

    <!-- Assignment Modal -->
    <flux:modal name="assignment-form" class="min-w-[500px]">
        <form wire:submit="save">
            <flux:heading size="lg">{{ $editingAssignment ? __('Editar Vínculo') : __('Novo Vínculo') }}</flux:heading>
            
            <div class="space-y-8 mt-6">
                <!-- Equipment Selection / Creation -->
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <flux:label>{{ __('Equipamento') }}</flux:label>
                        @if(!$editingAssignment)
                            <flux:button wire:click="toggleEquipmentCreation" variant="ghost" size="xs" color="{{ $isCreatingEquipment ? 'danger' : 'primary' }}">
                                {{ $isCreatingEquipment ? __('Cancelar') : __('Novo Equipamento') }}
                            </flux:button>
                        @endif
                    </div>

                    @if($isCreatingEquipment)
                        <div class="grid grid-cols-2 gap-4 p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg border border-zinc-200 dark:border-zinc-700">
                            <flux:input wire:model="newEquipmentName" :label="__('Nome')" placeholder="Ex: Monitor Dell" required />
                            <flux:input wire:model="newEquipmentAsset" :label="__('Identificador')" placeholder="Ex: MN-001" required />
                        </div>
                    @else
                        <flux:select wire:model="equipment_id" placeholder="Selecione um equipamento..." required :disabled="$editingAssignment !== null">
                            @foreach ($this->equipments as $equipment)
                                <flux:select.option :value="$equipment->id">{{ $equipment->name }} ({{ $equipment->asset_number }})</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif
                </div>

                <!-- Class Selection / Creation -->
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <flux:label>{{ __('Classe') }}</flux:label>
                        @if(!$editingAssignment)
                            <flux:button wire:click="toggleClassCreation" variant="ghost" size="xs" color="{{ $isCreatingClass ? 'danger' : 'primary' }}">
                                {{ $isCreatingClass ? __('Cancelar') : __('Nova Classe') }}
                            </flux:button>
                        @endif
                    </div>

                    @if($isCreatingClass)
                        <div class="p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg border border-zinc-200 dark:border-zinc-700">
                            <flux:input wire:model="newClassName" :label="__('Nome da Classe')" placeholder="Ex: Laboratório A" required />
                        </div>
                    @else
                        <flux:select wire:model="class_id" placeholder="Selecione uma classe..." required :disabled="$editingAssignment !== null">
                            @foreach ($this->classes as $class)
                                <flux:select.option :value="$class->id">{{ $class->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif
                </div>

                <flux:input type="number" wire:model="quantity" min="1" :label="__('Quantidade')" required />
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
