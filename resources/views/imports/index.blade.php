<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800">CSV imports</h2>
            <a href="{{ route('imports.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">New import</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                        <tr><th class="px-4 py-3">File</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Imported</th><th class="px-4 py-3">Skipped</th><th class="px-4 py-3">Failed</th><th class="px-4 py-3">By</th><th class="px-4 py-3">When</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($batches as $b)
                            <tr>
                                <td class="px-4 py-3"><a href="{{ route('imports.show', $b) }}" class="text-indigo-600 hover:underline">{{ $b->original_name }}</a></td>
                                <td class="px-4 py-3">{{ ucfirst($b->status) }}</td>
                                <td class="px-4 py-3">{{ $b->imported_rows }}</td>
                                <td class="px-4 py-3">{{ $b->skipped_rows }}</td>
                                <td class="px-4 py-3">{{ $b->failed_rows }}</td>
                                <td class="px-4 py-3">{{ $b->user->name }}</td>
                                <td class="px-4 py-3">{{ $b->created_at->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No imports yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $batches->links() }}
        </div>
    </div>
</x-app-layout>