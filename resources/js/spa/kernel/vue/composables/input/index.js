import { nextTick } from 'vue';

function useInputHandlers() {
    const autoResize = function(textInputFiled) {
        nextTick(() => {
            if (textInputFiled) {
                textInputFiled.style.height = '1rem';
                textInputFiled.style.height = textInputFiled.scrollHeight + 'px';
            }
        });
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
        insertSymbolAtCaret: insertSymbolAtCaret,
        matchMention: matchMention,
        completeText: completeText,
        matchLink: matchLink
    };
}

export { useInputHandlers };