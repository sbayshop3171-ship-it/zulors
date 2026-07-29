<div class="flex justify-between py-2 px-4 odd:bg-fill-qt">
    <span class="text-par-n text-lab-sc">
        {{ (isset($labelText) == true) ? $labelText : '' }}
    </span>
    <span class="text-par-n text-lab-pr2 font-medium">
        {{ (isset($labelValue) == true) ? $labelValue : '' }}
    </span>
</div>