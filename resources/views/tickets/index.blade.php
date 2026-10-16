<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800">Tickets</h2>
            <a href="{{ route('tickets.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">New ticket</a>
        </div>
    </x-slot>

    @php
        $isStaff = auth()->user()->isStaff();
        $input = 'rounded-md border-gray-300 text-sm shadow-sm';
    @endphp

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">

            {{-- Tabs: active (upcoming) / resolved / all --}}
            <div class="flex flex-wrap gap-2 text-sm">
                @foreach (['active' => 'Active', 'resolved' => 'Resolved / closed', 'all' => 'All'] as $key => $label)
                    <a href="{{ route('tickets.index', array_merge(request()->except(['page', 'status']), ['scope' => $key])) }}"
                       class="rounded-full px-4 py-1 {{ $scope === $key ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 shadow-sm' }}">{{ $label }}</a>
                @endforeach
            </div>

            {{-- Filters --}}
            <form method="GET" action="{{ route('tickets.index') }}" class="grid grid-cols-1 gap-3 rounded-lg bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
                <input type="hidden" name="scope" value="{{ request('scope', 'active') }}">

                <input name="q" value="{{ request('q') }}" placeholder="Ticket ID (TKT-…) or keyword" class="{{ $input }}">

                <select name="status" class="{{ $input }}">
                    <option value="">Any status</option>
                    @foreach (\App\Enums\TicketStatus::cases() as $s)
                        <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>

                <select name="priority" class="{{ $input }}">
                    <option value="">Any priority</option>
                    @foreach (\App\Enums\TicketPriority::cases() as $p)
                        <option value="{{ $p->value }}" @selected(request('priority') === $p->value)>{{ $p->label() }}</option>
                    @endforeach
                </select>

                <select name="category_id" class="{{ $input }}">
                    <option value="">Any category</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}" @selected((string) request('category_id') === (string) $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>

                @if ($isStaff)
                    <select name="agent" class="{{ $input }}">
                        <option value="">Any agent</option>
                        <option value="unassigned" @selected(request('agent') === 'unassigned')>Unassigned</option>
                        @foreach ($agents as $a)
                            <option value="{{ $a->id }}" @selected((string) request('agent') === (string) $a->id)>{{ $a->name }}</option>
                        @endforeach
                    </select>

                    <input name="customer" value="{{ request('customer') }}" placeholder="Customer name or e-mail" class="{{ $input }}">
                @endif

                <select name="sla" class="{{ $input }}">
                    <option value="">Any SLA status</option>
                    <option value="within" @selected(request('sla') === 'within')>Within SLA</option>
                    <option value="approaching" @selected(request('sla') === 'approaching')>Approaching breach</option>
                    <option value="breached" @selected(request('sla') === 'breached')>Breached</option>
                </select>

                <input type="date" name="from" value="{{ request('from') }}" class="{{ $input }}" title="Created from">
                <input type="date" name="to" value="{{ request('to') }}" class="{{ $input }}" title="Created to">

                <div class="flex gap-2">
                    <button class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white">Filter</button>
                    <a href="{{ route('tickets.index') }}" class="px-3 py-2 text-sm text-gray-600">Reset</a>
                </div>
            </form>

            @error('sla') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            {{-- Results --}}
            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Ticket</th>
                            <th class="px-4 py-3">Subject</th>
                            @if ($isStaff) <th class="px-4 py-3">Customer</th> @endif
                            <th class="px-4 py-3">Priority</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">SLA</th>
                            <th class="px-4 py-3">Agent</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($tickets as $t)
                            <tr class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-4 py-3">
                                    <a href="{{ route('tickets.show', $t) }}" class="font-medium text-indigo-600 hover:underline">{{ $t->ticket_number }}</a>
                                </td>
                                <td class="max-w-xs truncate px-4 py-3">{{ $t->subject }}</td>
                                @if ($isStaff) <td class="px-4 py-3">{{ $t->customer->name }}</td> @endif
                                <td class="px-4 py-3"><x-ticket-badge type="priority" :value="$t->priority" /></td>
                                <td class="px-4 py-3"><x-ticket-badge type="status" :value="$t->status" /></td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <x-ticket-badge type="sla" :value="$t->slaStatus()" />
                                    <div class="text-xs text-gray-500">{{ $t->sla_due_at?->format('d M, H:i') }}</div>
                                </td>
                                <td class="px-4 py-3">{{ $t->agent?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No tickets match.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $tickets->links() }}
        </div>
    </div>
</x-app-layout>