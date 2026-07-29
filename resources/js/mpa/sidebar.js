document.addEventListener('DOMContentLoaded', function() {
	const activeItem = document.querySelector('.sidenav-active');
	if (activeItem) {
		activeItem.scrollIntoView({
			behavior: 'smooth',
			block: 'center'
		});
	}
});