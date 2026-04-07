<?php

use App\Models\Equipment;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Collection;

new #[Title('Equipamentos')] class extends Component {
    public string $search = '';
    public ?Equipment $editing = null;
    public string $name = '';
    public string $asset_number = '';

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'asset_number' => 'required|string|max:255|unique:equipments,asset_number,' . ($this->editing?->id ?? 'NULL'),
        ];
    }

    public function create()
    {
        $this->editing = null;
        $this->reset(['name', 'asset_number']);
        $this->modal('equipment-form')->show();
    }

    public function edit(Equipment $equipment)
    {
        $this->editing = $equipment;
        $this->name = $equipment->name;
        $this->asset_number = $equipment->asset_number;
        $this->modal('equipment-form')->show();
    }

    public function save()
    {
        $validated = $this->validate();

        if ($this->editing) {
            $this->editing->update($validated);
        } else {
            Equipment::create($validated);
        }

        $this->modal('equipment-form')->close();
        $this->reset(['name', 'asset_number', 'editing']);
    }

    public function delete(Equipment $equipment)
    {
        $equipment->delete();
    }

    public function getEquipmentsProperty(): Collection
    {
        return Equipment::query()
            ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%')
                ->orWhere('asset_number', 'like', '%' . $this->search . '%'))
            ->latest()
            ->get();
    }
}; ?>

<section class="w-full">
    <div class="flex justify-between items-center mb-6">
        <div>
            <flux:heading size="xl">{{ __('Equipamentos') }}</flux:heading>
            <flux:subheading>{{ __('Gerencie os equipamentos do sistema.') }}</flux:subheading>
        </div>
        <flux:button wire:click="create" variant="primary" icon="plus">{{ __('Novo Equipamento') }}</flux:button>
    </div>

    <div class="mb-4">
        <flux:input wire:model.live="search" placeholder="Pesquisar equipamentos..." icon="magnifying-glass" />
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Nome') }}</flux:table.column>
            <flux:table.column>{{ __('Identificador') }}</flux:table.column>
            <flux:table.column>{{ __('Criado em') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Ações') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->equipments as $equipment)
                <flux:table.row :key="$equipment->id">
                    <flux:table.cell font="medium">{{ $equipment->name }}</flux:table.cell>
                    <flux:table.cell>{{ $equipment->asset_number ?? '-' }}</flux:cell>
                    <flux:table.cell>{{ $equipment->created_at->format('d/m/Y H:i') }}</flux:cell>
                    <flux:table.cell align="end">
                        <flux:button wire:click="edit({{ $equipment->id }})" variant="ghost" icon="pencil" size="xs" />
                        <flux:button wire:confirm="Tem certeza que deseja excluir?" wire:click="delete({{ $equipment->id }})" variant="ghost" icon="trash" size="xs" color="danger" />
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:modal name="equipment-form" class="min-w-[400px]">
        <form wire:submit="save">
            <flux:heading size="lg">{{ $editing ? __('Editar Equipamento') : __('Novo Equipamento') }}</flux:heading>
            
            <div class="space-y-6 mt-6">
                <flux:input wire:model="name" :label="__('Nome')" required />
                <flux:input wire:model="asset_number" :label="__('Identificador')" required />
            </div>

            <div class="flex mt-6 gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Salvar') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
