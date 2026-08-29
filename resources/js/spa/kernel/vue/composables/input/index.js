import { nextTick } from 'vue';

const buildViewportMetaContent = function({ userScalable = false, interactiveWidget = 'resizes-content' } = {}) {
    return `width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=${interactiveWidget}, user-scalable=${userScalable ? 'yes' : 'no'}`;
};

function useInputHandlers() {
    const autoResize = function(textInputFiled) {
        nextTick(() => {
            if (textInputFiled) {
                const nextHeight = Math.max(48, textInputFiled.scrollHeight + 2);
                textInputFiled.style.height = 'auto';
                textInputFiled.style.height = `${nextHeight}px`;
            }
        });
    }

    const preserveInputFocus = function(inputField, nextValue = '', { preventScroll = true } = {}) {
        if(! inputField) {
            return false;
        }

        const selectionStart = typeof inputField.selectionStart === 'number' ? inputField.selectionStart : null;

        inputField.value = nextValue;
        inputField.style.height = 'auto';

        if(inputField.scrollHeight) {
            const nextHeight = Math.max(48, inputField.scrollHeight + 2);
            inputField.style.height = `${nextHeight}px`;
        }

        requestAnimationFrame(() => {
            inputField.focus({ preventScroll });

            if(selectionStart !== null && typeof inputField.setSelectionRange === 'function') {
                const position = Math.min(selectionStart, String(nextValue).length);
                inputField.setSelectionRange(position, position);
            }

            if(typeof document !== 'undefined' && document.activeElement !== inputField) {
                inputField.focus({ preventScroll });
            }
        });

        return true;
    }

    const insertSymbolAtCaret = function(inputField, symbol) {
        if(inputField) {
            const value = inputField.value;
            const start = inputField.selectionStart;
            const end = inputField.selectionEnd;
            const newValue = value.slice(0, start) + symbol + value.slice(end);
    
            return newValue;
        }

        return symbol;
    }

    const completeText = function(inputField, textParams) {
        if(inputField) {
            const value = inputField.value;
            
            return `${value.slice(0, textParams.start)}${textParams.completable}${value.slice(textParams.end)}`;
        }

        return '';
    }

    const matchMention = function(inputField) {
        if(inputField) {
            const value = inputField.value;
            const start = inputField.selectionStart;

            const textBeforeCursor = value.substring(0, start);

            const mentionMatch = textBeforeCursor.match(/\B@[a-zA-Z0-9_.]+$/);

            if(mentionMatch) {
                return {
                    username: mentionMatch[0].slice(1),
                    start: mentionMatch.index,
                    end: start
                };
            }
        }

        return null;
    }

    const matchLink = function(inputField) {
        if(inputField) {
            const urlRegex = /(?:(?:https?|ftp|file):\/\/|www\.|ftp\.)(?:\([-A-Z0-9+&@#\/%=~_|$?!:,.]*\)|[-A-Z0-9+&@#\/%=~_|$?!:,.])*(?:\([-A-Z0-9+&@#\/%=~_|$?!:,.]*\)|[A-Z0-9+&@#\/%=~_|$])/ig;
            const linkMatch = inputField.value.match(urlRegex);
            
            if(linkMatch) {
                return linkMatch[0];
            }
        }

        return null;
    }

    return {
        autoResize: autoResize,
        preserveInputFocus: preserveInputFocus,
        insertSymbolAtCaret: insertSymbolAtCaret,
        matchMention: matchMention,
        completeText: completeText,
        matchLink: matchLink
    };
}

export { useInputHandlers, buildViewportMetaContent };