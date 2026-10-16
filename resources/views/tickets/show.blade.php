<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="text-xl font-semibold text-gray-800">{{ $ticket->ticket_number }}</h2>
            <x-ticket-badge type="status" :value="$ticket->status" />
            <x-ticket-badge type="priority" :value="$ticket->priority" />
            <x-ticket-badge type="sla" :value="$ticket->slaStatus()" />
            @if ($ticket->escalated_at)
                <span class="rounded-full bg-red-600 px-2 py-0.5 text-xs font-medium text-white">Escalated</span>
            @endif
        </div>
    </x-slot>

    @php $user = auth()->user(); @endphp

    <div class="py-8">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-3 lg:px-8">

            {{-- ============ Main column ============ --}}
            <div class="space-y-6 lg:col-span-2">

                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">{{ $ticket->subject }}</h3>
                    <p class="mt-3 whitespace-pre-line text-sm text-gray-700">{{ $ticket->description }}</p>

                    @if ($ticketAttachments->isNotEmpty())
                        <div class="mt-4 border-t pt-3 text-sm">
                            <div class="mb-1 font-medium text-gray-700">Attachments</div>
                            <ul class="space-y-1">
                                @foreach ($ticketAttachments as $file)
                                    <li><a href="{{ route('attachments.download', $file) }}" class="text-indigo-600 hover:underline">{{ $file->original_name }}</a>
                                        <span class="text-xs text-gray-500">({{ number_format($file->size / 1024, 1) }} KB)</span></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                {{-- Conversation --}}
                <div id="conversation" class="space-y-3">
                    <h3 class="font-semibold text-gray-800">Conversation</h3>

                    @forelse ($replies as $reply)
                        <div class="rounded-lg p-4 text-sm shadow-sm {{ $reply->is_internal ? 'border border-yellow-300 bg-yellow-50' : 'bg-white' }}">
                            <div class="mb-1 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500">
                                <span class="font-medium text-gray-800">{{ $reply->user->name }}
                                    <span class="font-normal text-gray-500">({{ $reply->user->role->value }})</span>
                                    @if ($reply->is_internal)
                                        <span class="ml-1 rounded bg-yellow-200 px-1.5 py-0.5 font-medium text-yellow-900">Internal note</span>
                                    @endif
                                </span>
                                <span>{{ $reply->created_at->format('d M Y, H:i') }}</span>
                            </div>
                            <p class="whitespace-pre-line text-gray-800">{{ $reply->body }}</p>

                            @foreach ($reply->attachments as $file)
                                <div class="mt-2 text-xs">
                                    📎 <a href="{{ route('attachments.download', $file) }}" class="text-indigo-600 hover:underline">{{ $file->original_name }}</a>
                                    <span class="text-gray-500">({{ number_format($file->size / 1024, 1) }} KB)</span>
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No replies yet.</p>
                    @endforelse

                    {{ $replies->links() }}
                </div>

                {{-- Reply form --}}
                @can('reply', $ticket)
                    <form method="POST" action="{{ route('tickets.replies.store', $ticket) }}" enctype="multipart/form-data" class="space-y-3 rounded-lg bg-white p-4 shadow-sm">
                        @csrf
                        <label class="block text-sm font-medium text-gray-700">Add a reply</label>
                        <textarea name="body" rows="4" class="w-full rounded-md border-gray-300 text-sm shadow-sm" required>{{ old('body') }}</textarea>
                        <x-input-error :messages="$errors->get('body')" />

                        <input type="file" name="attachments[]" multiple class="block w-full text-sm">
                        <x-input-error :messages="$errors->get('attachments')" />
                        <x-input-error :messages="$errors->get('attachments.*')" />

                        @can('addInternalNote', $ticket)
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="is_internal" value="1" class="rounded border-gray-300">
                                Internal note (never visible to the customer)
                            </label>
                        @endcan

                        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">Send</button>
                    </form>
                @endcan
            </div>

            {{-- ============ Sidebar ============ --}}
            <aside class="space-y-6">

                <div class="rounded-lg bg-white p-4 text-sm shadow-sm">
                    <h3 class="mb-3 font-semibold text-gray-800">Details</h3>
                    <dl class="space-y-2">
                        <div class="flex justify-between"><dt class="text-gray-500">Customer</dt><dd>{{ $ticket->customer->name }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Category</dt><dd>{{ $ticket->category->name }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Assigned to</dt><dd>{{ $ticket->agent?->name ?? 'Unassigned' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Created</dt><dd>{{ $ticket->created_at->format('d M Y, H:i') }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">SLA due</dt><dd>{{ $ticket->sla_due_at?->format('d M Y, H:i') ?? '—' }}</dd></div>
                        @if ($ticket->resolved_at)
                            <div class="flex justify-between"><dt class="text-gray-500">Resolved</dt><dd>{{ $ticket->resolved_at->format('d M Y, H:i') }}</dd></div>
                        @endif
                    </dl>
                </div>

                {{-- Actions. Every button is also protected by the policy on the server. --}}
                <div class="space-y-3 rounded-lg bg-white p-4 text-sm shadow-sm">
                    <h3 class="font-semibold text-gray-800">Actions</h3>

                    @can('accept', $ticket)
                        <form method="POST" action="{{ route('tickets.accept', $ticket) }}">
                            @csrf
                            <button class="w-full rounded-md bg-indigo-600 px-3 py-2 font-medium text-white hover:bg-indigo-500">Accept this ticket</button>
                        </form>
                    @endcan

                    @can('assign', $ticket)
                        <form method="POST" action="{{ route('tickets.assign', $ticket) }}" class="flex gap-2">
                            @csrf
                            <select name="agent_id" class="w-full rounded-md border-gray-300 text-sm" required>
                                <option value="">Choose agent…</option>
                                @foreach ($agents as $agent)
                                    <option value="{{ $agent->id }}" @selected($ticket->assigned_agent_id === $agent->id)>{{ $agent->name }}</option>
                                @endforeach
                            </select>
                            <button class="rounded-md bg-gray-800 px-3 py-2 text-white">Assign</button>
                        </form>
                        <form method="POST" action="{{ route('tickets.auto-assign', $ticket) }}">
                            @csrf
                            <button class="w-full rounded-md border border-gray-300 px-3 py-2 text-gray-700 hover:bg-gray-50">Auto-assign</button>
                        </form>
                    @endcan

                    @can('resolve', $ticket)
                        <form method="POST" action="{{ route('tickets.resolve', $ticket) }}">
                            @csrf
                            <button class="w-full rounded-md bg-green-600 px-3 py-2 font-medium text-white hover:bg-green-500">Mark as resolved</button>
                        </form>
                    @endcan

                    @can('close', $ticket)
                        <form method="POST" action="{{ route('tickets.close', $ticket) }}">
                            @csrf
                            <button class="w-full rounded-md border border-gray-300 px-3 py-2 text-gray-700 hover:bg-gray-50">Close ticket</button>
                        </form>
                    @endcan

                    @can('update', $ticket)
                        <a href="{{ route('tickets.edit', $ticket) }}" class="block text-center text-indigo-600 hover:underline">Edit ticket</a>
                    @endcan
                </div>

                {{-- Audit trail: staff only (customers must not see assignment or internal-note events) --}}
                @if ($user->isStaff())
                    <div class="rounded-lg bg-white p-4 text-sm shadow-sm">
                        <h3 class="mb-3 font-semibold text-gray-800">Activity</h3>
                        <ol class="space-y-2 border-l-2 border-gray-200 pl-3">
                            @foreach ($activities as $activity)
                                <li>
                                    <div class="text-xs text-gray-500">{{ $activity->created_at->format('d M, H:i') }}</div>
                                    <div class="text-gray-800">{{ $activity->description() }}</div>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endif
            </aside>
        </div>
    </div>
</x-app-layout>