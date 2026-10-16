{{-- <x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    {{ __("You're logged in!") }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout> --}}


{{-- New code  --}}

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Dashboard</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            @php
                $cards = [
                    ['Open tickets', $stats['open_total'], route('tickets.index'), 'text-gray-900'],
                    ['Unassigned', $stats['unassigned'], route('tickets.index', ['agent' => 'unassigned']), 'text-orange-600'],
                    ['Critical', $stats['critical'], route('tickets.index', ['priority' => 'critical']), 'text-red-600'],
                    ['SLA breached', $stats['sla_breached'], route('tickets.index', ['sla' => 'breached']), 'text-red-600'],
                    ['Approaching SLA', $stats['sla_approaching'], route('tickets.index', ['sla' => 'approaching']), 'text-yellow-600'],
                    ['Resolved / closed', $stats['resolved'], route('tickets.index', ['scope' => 'resolved']), 'text-green-600'],
                ];
            @endphp

            <div class="grid grid-cols-2 gap-4 lg:grid-cols-6">
                @foreach ($cards as [$label, $value, $url, $color])
                    <a href="{{ $url }}" class="rounded-lg bg-white p-4 shadow-sm hover:shadow">
                        <div class="text-xs font-medium uppercase text-gray-500">{{ $label }}</div>
                        <div class="mt-1 text-3xl font-semibold {{ $color }}">{{ $value }}</div>
                    </a>
                @endforeach
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <h3 class="mb-3 font-semibold text-gray-800">Active tickets by priority</h3>
                    <ul class="space-y-2 text-sm">
                        @foreach (\App\Enums\TicketPriority::cases() as $p)
                            <li class="flex items-center justify-between">
                                <x-ticket-badge type="priority" :value="$p" />
                                <span class="font-medium">{{ $byPriority[$p->value] ?? 0 }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                @if (auth()->user()->isStaff())
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <h3 class="mb-3 font-semibold text-gray-800">Active tickets by agent</h3>
                        <ul class="space-y-2 text-sm">
                            @forelse ($byAgent as $row)
                                <li class="flex justify-between"><span>{{ $row->name }}</span><span class="font-medium">{{ $row->total }}</span></li>
                            @empty
                                <li class="text-gray-500">No assigned tickets.</li>
                            @endforelse
                        </ul>
                    </div>
                @endif

                <div class="rounded-lg bg-white p-4 shadow-sm lg:col-span-1">
                    <h3 class="mb-3 font-semibold text-gray-800">Needs attention</h3>
                    <ul class="space-y-3 text-sm">
                        @forelse ($attention as $t)
                            <li>
                                <a href="{{ route('tickets.show', $t) }}" class="font-medium text-indigo-600 hover:underline">{{ $t->ticket_number }}</a>
                                <x-ticket-badge type="sla" :value="$t->slaStatus()" />
                                <div class="truncate text-gray-600">{{ $t->subject }}</div>
                                <div class="text-xs text-gray-500">Due {{ $t->sla_due_at->format('d M, H:i') }} · {{ $t->agent?->name ?? 'Unassigned' }}</div>
                            </li>
                        @empty
                            <li class="text-gray-500">Nothing is close to breaching its SLA.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
