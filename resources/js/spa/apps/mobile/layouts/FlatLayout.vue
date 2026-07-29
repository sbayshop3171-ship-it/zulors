<template>
	<RouterView v-slot="{ Component, route }">
		<component v-bind:is="Component" v-bind:key="route.fullPath"></component>
	</RouterView>
</template>
