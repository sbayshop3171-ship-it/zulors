export default function (md) {
    // Add a rule to parse the custom syntax
    md.inline.ruler.after('emphasis', 'underline', (state, silent) => {
        const marker = state.src[state.pos];

        // Check for "++" at the current position
        if (marker !== '+') return false;

        // Ensure there are at least two "+" symbols
        const start = state.pos;
        if (state.src[start + 1] !== '+') return false;

        // Find the closing "++"
        const match = state.src.slice(start + 2).match(/(.+?)\+\+/);
        if (!match) return false;

        if (!silent) {
        // Push the underline token
        state.push({
            type: 'underline_open',
            level: state.level,
        });
        state.push({
            type: 'text',
            content: match[1],
            level: state.level + 1,
        });
        state.push({
            type: 'underline_close',
            level: state.level,
        });
        }

        // Update position to after the closing "++"
        state.pos += match[0].length + 2;

            // Define rendering rules for the underline tokens
        md.renderer.rules.underline_open = () => '<u>';
        md.renderer.rules.underline_close = () => '</u>';

        return true;
    });
}