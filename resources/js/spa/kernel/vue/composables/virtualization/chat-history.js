/**
 * Chat History Virtualization Composable
 * Efficiently renders thousands of messages with DOM virtualization
 * Maintains smooth scrolling and sub-100ms message rendering
 */

import { ref, computed, onMounted, onUnmounted } from 'vue';

const ITEM_HEIGHT = 60; // Average message height in pixels
const BUFFER = 5; // Number of items to render outside visible area

export const useChatHistoryVirtualization = (messages, containerSelector = '.messages-container') => {
	const scrollContainer = ref(null);
	const visibleRange = ref({ start: 0, end: 50 });
	const scrollTop = ref(0);
	const containerHeight = ref(0);
	const isScrolling = ref(false);
	let scrollTimeout = null;

	// Compute visible messages based on scroll position
	const visibleMessages = computed(() => {
		const start = Math.max(0, visibleRange.value.start - BUFFER);
		const end = Math.min(messages.length, visibleRange.value.end + BUFFER);
		return messages.slice(start, end);
	});

	// Compute offset for virtual scrolling
	const offsetY = computed(() => {
		return visibleRange.value.start * ITEM_HEIGHT;
	});

	// Compute total height for scroll area
	const totalHeight = computed(() => {
		return messages.length * ITEM_HEIGHT;
	});

	/**
	 * Handle scroll events efficiently with debouncing
	 */
	const handleScroll = (event) => {
		isScrolling.value = true;
		scrollTop.value = event.target.scrollTop;

		// Calculate visible range
		const start = Math.floor(scrollTop.value / ITEM_HEIGHT);
		const visibleItems = Math.ceil(containerHeight.value / ITEM_HEIGHT);

		visibleRange.value = {
			start,
			end: start + visibleItems
		};

		// Clear previous timeout
		if (scrollTimeout) {
			clearTimeout(scrollTimeout);
		}

		// Set scrolling state after user stops scrolling
		scrollTimeout = setTimeout(() => {
			isScrolling.value = false;
		}, 150);
	};

	/**
	 * Scroll to bottom (new messages)
	 */
	const scrollToBottom = (behavior = 'smooth') => {
		if (scrollContainer.value) {
			scrollContainer.value.scrollTop = scrollContainer.value.scrollHeight;
		}
	};

	/**
	 * Scroll to specific message ID
	 */
	const scrollToMessage = (messageId) => {
		const messageIndex = messages.findIndex(m => m.id === messageId);
		if (messageIndex !== -1) {
			const targetScrollTop = messageIndex * ITEM_HEIGHT - containerHeight.value / 2;
			if (scrollContainer.value) {
				scrollContainer.value.scrollTop = Math.max(0, targetScrollTop);
			}
		}
	};

	/**
	 * Initialize virtualization
	 */
	const initialize = () => {
		scrollContainer.value = document.querySelector(containerSelector);

		if (!scrollContainer.value) {
			console.warn(`Container not found: ${containerSelector}`);
			return;
		}

		containerHeight.value = scrollContainer.value.clientHeight;
		scrollContainer.value.addEventListener('scroll', handleScroll, { passive: true });

		// Initial range
		const visibleItems = Math.ceil(containerHeight.value / ITEM_HEIGHT);
		visibleRange.value = { start: 0, end: visibleItems };
	};

	/**
	 * Cleanup
	 */
	const cleanup = () => {
		if (scrollContainer.value) {
			scrollContainer.value.removeEventListener('scroll', handleScroll);
		}

		if (scrollTimeout) {
			clearTimeout(scrollTimeout);
		}
	};

	/**
	 * Handle window resize
	 */
	const handleResize = () => {
		if (scrollContainer.value) {
			containerHeight.value = scrollContainer.value.clientHeight;
		}
	};

	// Lifecycle
	onMounted(() => {
		initialize();
		window.addEventListener('resize', handleResize);
	});

	onUnmounted(() => {
		cleanup();
		window.removeEventListener('resize', handleResize);
	});

	return {
		visibleMessages,
		offsetY,
		totalHeight,
		scrollToBottom,
		scrollToMessage,
		isScrolling,
		scrollTop,
		containerHeight
	};
};

/**
 * Performance metrics for virtualization
 */
export const useVirtualizationMetrics = () => {
	const metrics = ref({
		renderCount: 0,
		lastRenderTime: 0,
		averageRenderTime: 0,
		maxRenderTime: 0,
		messagesRendered: 0,
		messagesInDOM: 0
	});

	const recordRender = (messageCount, renderTime) => {
		metrics.value.renderCount++;
		metrics.value.lastRenderTime = renderTime;
		metrics.value.messagesRendered += messageCount;
		metrics.value.messagesInDOM = messageCount;

		if (renderTime > metrics.value.maxRenderTime) {
			metrics.value.maxRenderTime = renderTime;
		}

		// Calculate running average
		metrics.value.averageRenderTime = (
			(metrics.value.averageRenderTime * (metrics.value.renderCount - 1) + renderTime) /
			metrics.value.renderCount
		);
	};

	const getMetricsReport = () => {
		return {
			...metrics.value,
			isOptimized: metrics.value.averageRenderTime < 16.67 // 60fps target
		};
	};

	return {
		metrics: computed(() => metrics.value),
		recordRender,
		getMetricsReport
	};
};
