<template>
	<div class="bg-bg-pr overflow-hidden rounded-md p-2 group">
		<div class="border border-dashed border-edge-pr h-44 rounded-md flex flex-col justify-center cursor-pointer">
			<div class="text-center mt-auto">
				<div class="flex justify-center group-hover:-translate-y-2 smoothing">
					<img class="size-24" v-bind:src="$asset('assets/icons/upload.png')" alt="Image">
				</div>
			</div>
			<p class="text-center text-cap-l text-lab-sc mt-auto mb-3">
				{{ $t('editor.post_drop_zone') }}
			</p>
		</div>
	</div>
</template>