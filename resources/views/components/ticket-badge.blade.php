@props(['type', 'value'])

@php
    $v = $value instanceof \BackedEnum ? $value->value : $value;

    $colors = [
        'status' => [
            'open' => 'bg-blue-100 text-blue-800',
            'in_progress' => 'bg-indigo-100 text-indigo-800',
            'waiting_for_customer' => 'bg-yellow-100 text-yellow-800',
            'resolved' => 'bg-green-100 text-green-800',
            'closed' => 'bg-gray-200 text-gray-700',
        ],
        'priority' => [
            'low' => 'bg-gray-100 text-gray-700',
            'medium' => 'bg-blue-100 text-blue-800',
            'high' => 'bg-orange-100 text-orange-800',
            'critical' => 'bg-red-100 text-red-800',
        ],
        'sla' => [
            'within' => 'bg-green-100 text-green-800',
            'approaching' => 'bg-yellow-100 text-yellow-800',
            'breached' => 'bg-red-100 text-red-800',
            'met' => 'bg-gray-100 text-gray-700',
        ],
    ];

    $class = $colors[$type][$v] ?? 'bg-gray-100 text-gray-700';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium {$class}"]) }}>
    {{ ucwords(str_replace('_', ' ', $v)) }}
</span>