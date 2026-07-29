<template>
	<div class="flex items-center border-b border-b-fill-pr pb-4 overflow-hidden">
		<div class="size-normal-avatar shrink-0 rounded-full overflow-hidden flex justify-center items-center bg-fill-tr">
			<template v-if="sessionData.is_desktop">
				<SvgIcon type="line" name="monitor-04" classes="size-icon-small text-brand-900"></SvgIcon>
			</template>
			<template v-else>
				<SvgIcon type="line" name="phone-01" classes="size-icon-small text-brand-900"></SvgIcon>
			</template>
		</div>
		<div class="flex-1 mx-4 overflow-hidden">
			<h4 class="text-par-m font-semibold text-lab-pr2 leading-tight truncate">
				{{ sessionData.os.name }} {{ sessionData.os.version }}, {{ sessionData.ip_address }}, {{ sessionData.browser.name }} {{ sessionData.browser.version }}
			</h4>
			<span v-if="sessionData.location" class="block text-par-s text-lab-sc truncate">
				{{ sessionData.location.city }}, {{ sessionData.location.region }}, {{ sessionData.location.country }}
			</span>
			<span v-else class="block text-par-s text-orange-400 truncate">
				{{ $t('settings.forms.active_sessions.location_unknown') }}
			</span>
		</div>
		<div class="self-start shrink-0">
			<span v-if="sessionData.is_online" class="block text-par-s text-green-500 truncate">
				{{ $t('labels.online') }}
			</span>
			<span v-else class="block text-par-s text-lab-sc truncate">
				{{ sessionData.last_online }}
			</span>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			sessionData: {
				type: Object,
				required: true
			}
		}
	});
</script>