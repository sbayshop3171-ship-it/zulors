import 'alpinejs';

document.addEventListener('alpine:init', () => {
	Alpine.store('confirmModal', {
		isOpen: false,
		content: {
			title: '',
			desc: '',
			cancelButtonText: null,
			confirmButtonText: null
		},
		open: function(options) {
			this.isOpen = true;
			this.content.title = options.title;
			this.content.desc = options.desc;
			this.formAction = options.formAction;

			if(options.cancelButtonText) {
				this.content.cancelButtonText = options.cancelButtonText;
			}

			if (options.confirmButtonText) {
				this.content.confirmButtonText = options.confirmButtonText;
			}
		},
		close: function() {
			this.isOpen = false;
		}
	});
});