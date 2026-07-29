import Autolinker from 'autolinker';

export default class Str {
    str = '';

    constructor(str) {
        this.str = str;
    }

    static make(str) {
        return new Str(str);
    }

    format() {
        for (var argItem in arguments) {
            this.str = this.str.replace(new RegExp("\\{" + argItem + "\\}", 'g'), arguments[argItem]);
        }
    
        return this;
    }

    numberOf(position = 1) {
        const numberMatches = this.str.match(/\d+/g);
    
        if (!numberMatches || position > numberMatches.length || position < 1) {
            return null;
        }
    
        this.str = parseInt(numberMatches[position - 1], 10);

        return this;
    }

    linkifyText(options) {
        const defaultOptions = {
            mention: false,
            newWindow: true,
            className: 'text-brand-900',
            ...options
        }

        this.str = Autolinker.link(this.str, defaultOptions);

        return this;
    }

    fileSize() {
        if (this.str === 0) {
            return '0 Bytes';
        };

        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(this.str) / Math.log(1024));
        const size = this.str / Math.pow(1024, i);

        this.str = `${size.toFixed(2)} ${sizes[i]}`;

        return this;
    }

    timeFormat() {
        const hours = Math.floor(this.str / 3600);
        const minutes = Math.floor((this.str % 3600) / 60);
        const seconds = Math.floor(this.str % 60);
    
        const formattedMins = String(minutes).padStart(2, '0');
        const formattedSecs = String(seconds).padStart(2, '0');
    
        if (hours > 0) {
            this.str = `${String(hours).padStart(2, '0')}:${formattedMins}:${formattedSecs}`;
        }

        else{
            this.str = `${formattedMins}:${formattedSecs}`;
        }

        return this;
    }

    value() {
        return this.str;
    }
}