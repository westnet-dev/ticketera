<x-layouts::app :title="__('Triage')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <x-page-header :title="__('Triage')" :subtitle="__('Tickets nuevos que esperan aprobación antes de entrar a la cola de trabajo')" />

        <livewire:tickets.admin-triage-list />
    </div>
</x-layouts::app>
