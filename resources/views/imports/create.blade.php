<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">Import tickets from CSV</h2></x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 text-sm text-gray-700 shadow-sm">
                <p class="font-medium">Required columns:</p>
                <code class="block rounded bg-gray-100 p-2 text-xs">subject, description, priority, category, customer_email</code>
                <p class="mt-3 font-medium">Optional columns:</p>
                <code class="block rounded bg-gray-100 p-2 text-xs">external_ref, status, created_at</code>
                <p class="mt-3 text-gray-500">Rows with an <code>external_ref</code> that already exists are skipped. Invalid rows are reported, not imported.</p>
            </div>

            <form method="POST" action="{{ route('imports.store') }}" enctype="multipart/form-data" class="space-y-4 rounded-lg bg-white p-6 shadow-sm">
                @csrf
                <input type="file" name="file" accept=".csv,.txt" required class="block w-full text-sm">
                <x-input-error :messages="$errors->get('file')" />
                <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">Upload and import</button>
            </form>
        </div>
    </div>
</x-app-layout>