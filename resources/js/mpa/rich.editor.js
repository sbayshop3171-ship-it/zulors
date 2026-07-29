import Quill from 'quill';

window.addEventListener('load', () => {
	const richEditor = document.getElementById('rich-editor-content');
	
	if (richEditor) {
		const quill = new Quill('#rich-editor', {
			theme: 'snow',
			height: 500
		});

		quill.on('text-change', (delta, oldDelta, source) => {
			richEditor.value = quill.root.innerHTML;
		});
	}
});
