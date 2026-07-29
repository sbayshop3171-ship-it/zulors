@props([
    'type' => 'default',
    'typeOptions' => [
        'default' => 'text-lab-sc',
        'success' => 'text-green-900',
        'error' => 'text-red-900',
    ],
])

<div class="{{ $typeOptions[$type] }} text-par-m">
	{{ $slot}}
</div>
