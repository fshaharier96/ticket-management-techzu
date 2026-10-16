<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">Import: {{ $batch->original_name }}</h2></x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-5">
                @foreach ([['Status', ucfirst($batch->status)], ['Processed', $batch->processed_rows], ['Imported', $batch->imported_rows], ['Skipped (duplicates)', $batch->skipped_rows], ['Failed (invalid)', $batch->failed_rows]] as [$label, $value])
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <div class="text-xs font-medium uppercase text-gray-500">{{ $label }}</div>
                        <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $value }}</div>
                    </div>
                @endforeach
            </div>

            @if ($batch->error_message)
                <div class="rounded-md bg-red-100 p-3 text-sm text-red-800">{{ $batch->error_message }}</div>
            @endif

            @if ($batch->failed_rows > 0)
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800">Invalid rows</h3>
                    <a href="{{ route('imports.errors', $batch) }}" class="text-sm text-indigo-600 hover:underline">Download as CSV</a>
                </div>

                <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                            <tr><th class="px-4 py-3">Row</th><th class="px-4 py-3">Problems</th><th class="px-4 py-3">Data</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($rowErrors as $error)
                                <tr>
                                    <td class="px-4 py-3">{{ $error->row_number }}</td>
                                    <td class="px-4 py-3 text-red-700">{{ collect($error->errors)->flatten()->implode(' ') }}</td>
                                    <td class="max-w-md truncate px-4 py-3 text-xs text-gray-500">{{ json_encode($error->row_data, JSON_UNESCAPED_UNICODE) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $rowErrors->links() }}
            @endif
        </div>
    </div>

    @if ($batch->isRunning())
        <script>setTimeout(() => window.location.reload(), 4000);</script>
    @endif
</x-app-layout>