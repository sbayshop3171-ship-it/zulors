@props([
    'variant' => 'default',
    'variants' => [
        'strong' => 'text-lab-pr',
        'default' => 'text-lab-pr2',
        'weak' => 'text-lab-pr3',
        'muted' => 'text-lab-sc',
        'money' => 'text-mint',
    ],
    'weight' => 'normal',
    'weights' => [
        'normal' => 'font-normal',
        'medium' => 'font-medium',
        'bold' => 'font-bold',
    ],
    'numeric' => false,
])

<td class="h-14 align-middle leading-snug text-par-n whitespace-nowrap truncate max-w-32 border-b border-b-bord-tr px-2 break-words first:pl-4 last:pr-4 {{ $variants[$variant] }} {{ $weights[$weight] }} {{ $numeric ? 'font-mono' : '' }}" {{ $attributes }}>
    {{ $slot }}
</td>
