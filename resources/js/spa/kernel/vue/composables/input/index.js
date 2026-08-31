import { nextTick } from 'vue';

const buildViewportMetaContent = function({ userScalable = false, interactiveWidget = 'resizes-content' } = {}) {
    return `width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=${interactiveWidget}, user-scalable=${userScalable ? 'yes' : 'no'}`;
};

const getSafeViewportHeight = function({ fallback = 0 } = {}) {
    if(typeof window === 'undefined') {
        return fallback;
    }

    const visualViewportHeight = Number(window.visualViewport?.height || 0);
    const innerHeight = Number(window.innerHeight || 0);
    const documentHeight = Number(document.documentElement?.clientHeight || 0);
    const candidates = [
        visualViewportHeight,
        innerHeight,
        documentHeight,
        fallback,
    ].filter((value) => Number.isFinite(value) && value > 0);

    if(candidates.length === 0) {
        return fallback;
    }

    return Math.min(...candidates);
};

const syncViewportHeight = function({ element = document.documentElement, variableName = '--app-viewport-height' } = {}) {
    if(! element || typeof window === 'undefined') {
        return null;
    }

    const nextHeight = getSafeViewportHeight({ fallback: window.innerHeight || 0 });
    const nextValue = `${nextHeight}px`;

    element.style.setProperty(variableName, nextValue);

    return nextValue;
};

const installViewportKeyboardGuard = function({ element = document.documentElement, variableName = '--app-viewport-height' } = {}) {
    if(typeof window === 'undefined' || ! element) {
        return () => {};
    }

    const update = () => syncViewportHeight({ element, variableName });

    update();

    window.addEventListener('resize', update, { passive: true });
    window.addEventListener('orientationchange', update, { passive: true });

    if(window.visualViewport) {
        window.visualViewport.addEventListener('resize', update, { passive: true });
        window.visualViewport.addEventListener('scroll', update, { passive: true });
    }

    return () => {
        window.removeEventListener('resize', update);
        window.removeEventListener('orientationchange', update);

        if(window.visualViewport) {
            window.visualViewport.removeEventListener('resize', update);
            window.visualViewport.removeEventListener('scroll', update);
        }
    };
};

function useInputHandlers() {
    const autoResize = function(textInputFiled) {
        nextTick(() => {
            if (textInputFiled) {
                const minHeight = 40;
                const maxHeight = 112;
                const viewportPadding = 0;
                const measuredHeight = Math.max(0, Number(textInputFiled.scrollHeight || 0) + viewportPadding);
                const clampedHeight = Math.min(maxHeight, Math.max(minHeight, measuredHeight));

                textInputFiled.style.height = 'auto';
                textInputFiled.style.height = `${clampedHeight}px`;
                textInputFiled.style.overflowY = measuredHeight > maxHeight ? 'auto' : 'hidden';
                textInputFiled.style.maxHeight = `${maxHeight}px`;
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

export {
    useInputHandlers,
    buildViewportMetaContent,
    getSafeViewportHeight,
    syncViewportHeight,
    installViewportKeyboardGuard,
};