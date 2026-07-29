// longpress.js - Vue 3 Long Press Directive

const longpress = {
	mounted(el, binding) {
		if (typeof binding.value !== 'function') {
			console.warn('v-longpress expects a function as the value');
			return false;
		}

		let timeout = null;
		let startTime = 0;
		let isLongPress = false;
		
		// Get duration from directive argument or default to 500ms
		const duration = parseInt(binding.arg) || 500
		
		// Mouse events
		const startPress = (e) => {
			startTime = Date.now();
			isLongPress = false;
			
			timeout = setTimeout(() => {
				isLongPress = true;
				binding.value(e);
			}, duration);
		}
		
		const endPress = (e) => {
			clearTimeout(timeout)
			
			// Prevent click event if it was a long press
			if (isLongPress) {
				e.preventDefault()
				e.stopPropagation()
			}
		}
		
		const cancelPress = () => {
			clearTimeout(timeout)
			isLongPress = false
		}

		// Touch events
		const touchStart = (e) => {
			startPress(e.touches[0])
		}
		
		const touchEnd = (e) => {
			endPress(e)
		}
		
		const touchMove = (e) => {
			// Cancel if finger moves too much (tolerance of 10px)
			if (startTime > 0) {
				const touch = e.touches[0]
				const rect = el.getBoundingClientRect()
				const x = touch.clientX - rect.left
				const y = touch.clientY - rect.top
				
				if (x < -10 || x > rect.width + 10 || y < -10 || y > rect.height + 10) {
				cancelPress()
				}
			}
		}

		// Store event listeners for cleanup
		el._longpress = {
			mousedown: startPress,
			mouseup: endPress,
			mouseleave: cancelPress,
			touchstart: touchStart,
			touchend: touchEnd,
			touchmove: touchMove,
			touchcancel: cancelPress
		}

		// Add event listeners
		el.addEventListener('mousedown', el._longpress.mousedown)
		el.addEventListener('mouseup', el._longpress.mouseup)
		el.addEventListener('mouseleave', el._longpress.mouseleave)
		el.addEventListener('touchstart', el._longpress.touchstart, { passive: false })
		el.addEventListener('touchend', el._longpress.touchend, { passive: false })
		el.addEventListener('touchmove', el._longpress.touchmove, { passive: false })
		el.addEventListener('touchcancel', el._longpress.touchcancel)
	},

	beforeUnmount(el) {
		// Cleanup event listeners
		if (el._longpress) {
			el.removeEventListener('mousedown', el._longpress.mousedown);
			el.removeEventListener('mouseup', el._longpress.mouseup);
			el.removeEventListener('mouseleave', el._longpress.mouseleave);
			el.removeEventListener('touchstart', el._longpress.touchstart);
			el.removeEventListener('touchend', el._longpress.touchend);
			el.removeEventListener('touchmove', el._longpress.touchmove);
			el.removeEventListener('touchcancel', el._longpress.touchcancel);
			delete el._longpress;
		}
	}
}

export default longpress;