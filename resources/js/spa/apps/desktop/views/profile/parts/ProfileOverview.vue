<template>
	<div class="flex gap-3 flex-wrap" v-if="profileData.caption || profileData.country || profileData.join_date">
		<div v-if="profileData.caption" class="inline-flex gap-1 text-lab-sc leading-zero items-center">
			<span class="text-lab-tr">
				<SvgIcon name="briefcase-01" type="line" classes="size-icon-small"></SvgIcon>
			</span>
			<span class="text-par-s">
				{{ profileData.caption }}
			</span>
		</div>
		<div v-if="profileData.country" class="inline-flex gap-1 text-lab-sc leading-zero items-center">
			<span class="text-lab-tr">
				<SvgIcon name="marker-pin-01" type="line" classes="size-icon-small"></SvgIcon>
			</span>
			<span class="text-par-s">
				{{ profileData.country_name }}, <span v-if="profileData.city">{{ profileData.city }}</span>
			</span>
		</div>
		<div v-if="profileData.join_date" class="inline-flex gap-1 text-lab-sc leading-zero items-center">
			<span class="text-lab-tr">
				<SvgIcon name="calendar-check-01" type="line" classes="size-icon-small"></SvgIcon>
			</span>
			<span class="text-par-s">
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
