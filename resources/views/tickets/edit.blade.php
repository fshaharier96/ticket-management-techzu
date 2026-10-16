<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">Edit {{ $ticket->ticket_number }}</h2></x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('tickets.update', $ticket) }}" class="space-y-6 rounded-lg bg-white p-6 shadow-sm">
                @csrf
                @method('PUT')
                @include('tickets._form')
                <div class="flex gap-3">
                    <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">Save changes</button>
                    <a href="{{ route('tickets.show', $ticket) }}" class="px-4 py-2 text-sm text-gray-600">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>