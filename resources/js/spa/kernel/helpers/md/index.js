import MarkdownParser from 'markdown-it';

const mdInlineRenderer = (text = '', options = {}) => {
	return new MarkdownParser({
		html: true,
		breaks: true,
		linkify: true,
		...options
	}).renderInline(text);
}

export { mdInlineRenderer };