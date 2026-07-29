@props(['description' => ''])

<div class="flex gap-2">
    <span class="shrink-0 size-icon-x-small text-lab-sc mt-0.5">
        <x-ui-icon name="alert-circle" type="line"></x-ui-icon>
    </span>
    <p class="text-par-s text-lab-sc w-8/12">
        {{ $description }}
    </p>
</div>
