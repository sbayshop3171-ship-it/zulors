/**
 * @description This component is used to copy the code to the clipboard.
 * @author Zulors Developer Mansur Terla
 * Naming convention: colibriUI<ComponentName>
 * Example: colibriUICode
 */

window.addEventListener('alpine:init', () => {
	Alpine.data('colibriUICode', () => {
		return {
			copying: false,
			copy: async function() {
				this.copying = true;

				await navigator.clipboard.writeText(this.$refs.code.textContent);
				
				setTimeout(() => {
					this.copying = false;
				}, 1000);
			}
		}
	});
});