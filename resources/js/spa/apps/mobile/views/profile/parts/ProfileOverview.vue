<template>
	<div class="flex flex-col gap-3" v-if="profileData.country || profileData.join_date">
		<div v-if="profileData.country" class="inline-flex gap-1 text-lab-pr2 leading-zero items-center">
			<span class="text-lab-pr3">
				<SvgIcon name="marker-pin-01" type="line" classes="size-icon-small"></SvgIcon>
			</span>
			<span class="text-par-n">
				<span v-if="profileData.country_name">{{ profileData.country_name }}</span>
				<span v-if="profileData.country_name && profileData.city">, </span>
				<span v-if="profileData.city">{{ profileData.city }}</span>
			</span>
		</div>
		<div v-if="profileData.join_date" class="inline-flex gap-1 text-lab-pr2 leading-zero items-center">
			<span class="text-lab-pr3">
				<SvgIcon name="calendar-check-01" type="line" classes="size-icon-small"></SvgIcon>
			</span>
			<span class="text-par-n">
				{{ $t('labels.joined_at_date', { date: profileData.join_date.formatted }) }}
			</span>
		</div>
	</div>
</template>

<script>
	import { defineComponent, inject } from 'vue';

	export default defineComponent({
		setup: function() {
			const profileData = inject('profileData');

			return {
				profileData: profileData
			}
		}
	});
</script>
