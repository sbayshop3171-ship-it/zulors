export default function (md) {
    // This rule goes through inline tokens
    md.inline.ruler.push('mention', (state, silent) => {
        const mentionPattern = /@([a-zA-Z0-9_.]+)/; // Regex to match @username
        const pos = state.pos;

        // Check if the match pattern is found
        if (state.src.charAt(pos) !== '@' || !mentionPattern.test(state.src.slice(pos))) {
        return false;
        }

        const match = state.src.slice(pos).match(mentionPattern);

        // If there’s no match or silent mode is on, return false
        if (!match || silent) return false;

        const username = match[1];
        const mentionLink = `/@${username}`;

        // Push the link token
        const token = state.push('link_open', 'a', 1);
        token.attrs = [['href', mentionLink]];
        token.content = `@${username}`;
        token.markup = '@';

        // Add the text content
        const textToken = state.push('text', '', 0);
        textToken.content = `@${username}`;

        // Close the link
        state.push('link_close', 'a', -1);

        // Move the parser position forward
        state.pos += match[0].length;

        return true;
    });
}