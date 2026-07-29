export default {
    mounted: function(el, binding) {
        el.clickOutsideHandler = function(event) {
            if (! (el === event.target || el.contains(event.target))) {
                binding.value(event);
            }
        };

        document.addEventListener('click', el.clickOutsideHandler);
    },
    unmounted: function(el) {
        document.removeEventListener('click', el.clickOutsideHandler);
    }
};