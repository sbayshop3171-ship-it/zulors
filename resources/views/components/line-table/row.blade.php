<div class="flex justify-between py-2 border-b border-bord-pr border-dashed">
    <span class="text-par-n text-lab-sc">
        {{ (isset($labelText) == true) ? $labelText : '' }}
    </span>
    <span class="text-par-n text-lab-pr2 ml-12 text-right font-medium">
        {{ (isset($labelValue) == true) ? $labelValue : '' }}
    </span>
</div>