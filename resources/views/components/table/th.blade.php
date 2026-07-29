@props(['classes' => ''])

<th class="text-left text-par-s text-lab-pr2 px-2 first:pl-5 last:pr-5 font-medium pb-4 {{ $classes }} border-b-6 border-b-bord-tr" {{ $attributes }}>
    {{ $slot }}
</th>