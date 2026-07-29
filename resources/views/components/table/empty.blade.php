@props([
	'message' => '',
	'colspan' => 1
])

<x-table.tr>
	<x-table.td colspan="{{ $colspan }}">
		<p class="py-32 text-center text-cap-l text-lab-sc">{{ empty($message) ? __('business/table.empty') : $message }}</p>
	</x-table.td>
</x-table.tr>