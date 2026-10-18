{{-- No servers card: it needs pulse:check running all the time, and the host shows that already. --}}
<x-pulse>
    <livewire:pulse.slow-requests cols="6" />

    <livewire:pulse.slow-queries cols="6" />

    <livewire:pulse.exceptions cols="6" />

    <livewire:pulse.slow-jobs cols="6" />

    <livewire:pulse.queues cols="4" />

    <livewire:pulse.usage cols="4" rows="2" />

    <livewire:pulse.cache cols="4" />

    <livewire:pulse.slow-outgoing-requests cols="8" />
</x-pulse>
