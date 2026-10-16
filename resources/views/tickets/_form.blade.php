@php
    $isStaff = auth()->user()->isStaff();
    $editing = $ticket !== null;
    $field = 'mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm';
@endphp

<div class="space-y-4">
    @if (! $editing && $isStaff)
        <div>
            <label class="block text-sm font-medium text-gray-700">Customer</label>
            <select name="customer_id" class="{{ $field }}" required>
                <option value="">Select a customer…</option>
                @foreach ($customers as $c)
                    <option value="{{ $c->id }}" @selected(old('customer_id') == $c->id)>{{ $c->name }} ({{ $c->email }})</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('customer_id')" class="mt-1" />
        </div>
    @endif

    <div>
        <label class="block text-sm font-medium text-gray-700">Subject</label>
        <input type="text" name="subject" value="{{ old('subject', $ticket?->subject) }}" class="{{ $field }}" required>
        <x-input-error :messages="$errors->get('subject')" class="mt-1" />
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700">Description</label>
        <textarea name="description" rows="6" class="{{ $field }}" required>{{ old('description', $ticket?->description) }}</textarea>
        <x-input-error :messages="$errors->get('description')" class="mt-1" />
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        @if (! $editing || $isStaff)
            <div>
                <label class="block text-sm font-medium text-gray-700">Priority</label>
                <select name="priority" class="{{ $field }}" required>
                    @foreach (\App\Enums\TicketPriority::cases() as $p)
                        <option value="{{ $p->value }}" @selected(old('priority', $ticket?->priority?->value ?? 'medium') === $p->value)>{{ $p->label() }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('priority')" class="mt-1" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Category</label>
                <select name="category_id" class="{{ $field }}" required>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}" @selected((string) old('category_id', $ticket?->category_id) === (string) $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('category_id')" class="mt-1" />
            </div>
        @endif

        @if ($editing && $isStaff)
            <div>
                <label class="block text-sm font-medium text-gray-700">Status</label>
                <select name="status" class="{{ $field }}" required>
                    @foreach (\App\Enums\TicketStatus::cases() as $s)
                        <option value="{{ $s->value }}" @selected(old('status', $ticket->status->value) === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('status')" class="mt-1" />
            </div>
        @endif
    </div>

    @if (! $editing)
        <div>
            <label class="block text-sm font-medium text-gray-700">Attachments <span class="font-normal text-gray-500">(max {{ config('tickets.attachments.max_files') }} files, {{ config('tickets.attachments.max_kb') / 1024 }} MB each: {{ implode(', ', config('tickets.attachments.mimes')) }})</span></label>
            <input type="file" name="attachments[]" multiple class="mt-1 block w-full text-sm">
            <x-input-error :messages="$errors->get('attachments')" class="mt-1" />
            <x-input-error :messages="$errors->get('attachments.*')" class="mt-1" />
        </div>
    @endif
</div>