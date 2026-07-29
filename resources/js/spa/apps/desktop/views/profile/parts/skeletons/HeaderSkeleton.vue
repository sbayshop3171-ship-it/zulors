<template>
	<div class="block relative">
		<div class="h-44 skeleton-square mb-4"></div>
		<div class="flex mb-3">
			<div class="relative z-10 border border-bord-sc skeleton-circle rounded-full bg-fill-pr backdrop-blur-2xl size-large-avatar -mt-[72px] ml-4"></div>
			<div class="flex justify-end gap-2 ml-auto px-4">
				<div class="size-6 skeleton-circle">
				</div>
				<div class="h-6 skeleton w-16"></div>
			</div>
		</div>
		<div class="px-4 flex justify-center mb-4">
			<div class="flex flex-col items-center">
				<div class="colibri-primary-animation"></div>
				<span class="text-par-s text-lab-pr3 mt-1">{{ $t('labels.loading') }}...</span>
			</div>
		</div>
	</div>
</template>