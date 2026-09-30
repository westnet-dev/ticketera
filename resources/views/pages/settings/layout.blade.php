<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-55">
        <flux:navlist aria-label="{{ __('Settings') }}">
            <flux:navlist.item :href="route('profile.edit')" wire:navigate>{{
                __("Perfil")
            }}</flux:navlist.item>
            <flux:navlist.item :href="route('security.edit')" wire:navigate>{{
                __("Seguridad")
            }}</flux:navlist.item>
            <flux:navlist.item :href="route('appearance.edit')" wire:navigate>{{
                __("Apariencia")
            }}</flux:navlist.item>
            @if (auth()->user()->isAdmin())
                <flux:navlist.item :href="route('tickets.settings')" wire:navigate>{{
                    __("Tickets")
                }}</flux:navlist.item>
            @endif
        </flux:navlist>
    </div>

    <x-panel class="w-full flex-1 self-stretch p-4 max-md:mt-6 sm:p-6">
        <flux:heading>{{ $heading ?? "" }}</flux:heading>
        <flux:subheading>{{ $subheading ?? "" }}</flux:subheading>

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </x-panel>
</div>
