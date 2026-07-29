<template>
    <div class="border smoothing text-lab-pr3 border-edge-pr rounded-full flex-center cursor-pointer size-full hover:text-brand-900 hover:border-brand-900">
        <div class="size-8 group-hover:text-brand-900">
			<SvgIcon v-bind:name="iconName" v-bind:type="iconType"></SvgIcon>
		</div>
    </div>
</template>
<script>
    import { defineComponent } from 'vue';

    export default defineComponent({
        props: {
            iconName: {
                type: String,
                required: true
            },
            iconType: {
                type: String,
                default: 'solid' 
            }
        }
    });
</script>