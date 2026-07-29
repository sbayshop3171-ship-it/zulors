import { Num, Str } from '@/kernel/helpers/javascript/index.js';

import { mdInlineRenderer } from '@/kernel/helpers/md/index.js';

export default {
    install(app) {
        app.config.globalProperties.$getRoute = function (name, params = {}) {
            let route = embedder(`routes.${name}`);
            return route;
        };

        app.config.globalProperties.$money = function (amount) {
            let currency = embedder('config.app.currency');
            
            return `${amount} ${currency.symbol}`;
        };

        app.config.globalProperties.$asset = (path = '') => {
            return embedder('links.assets_url') + path;
        };

        app.config.globalProperties.$link = (path = '') => {
            return embedder(`links.${path}`);
        };

        app.config.globalProperties.$baseUrl = (path = '') => {
            return embedder('links.base_url') + path;
        };

        app.config.globalProperties.$embedder = function(path, defaultValue = undefined) {
            return embedder(path, defaultValue);
        }

        app.config.globalProperties.$config = function(path, defaultValue = undefined) {
            return config(path, defaultValue);
        }

        app.config.globalProperties.$comingSoon = function() {
            alert('This feature is still under development.\nPlease check the new Zulors Telegram channel to stay updated.');
        }

        app.config.globalProperties.$isStandalone = function() {
            return isStandalone();
        }

        app.config.globalProperties.$filters = {
            formatNumber: function(value) {
                return Num.make(value).format().value();
            },
            linkifyText: function(value) {
                return Str.make(value).linkifyText().value();
            },
            fileSize: function(value) {
                return Str.make(value).fileSize().value();
            },
            mediaDuration: function(durationTime) {
                if(! durationTime) {
                    return '';
                }

                let duration = `${durationTime.minutes || '00'}:${durationTime.seconds || '00'}`;

                if(durationTime.hours && durationTime.hours != '00') {
                    duration = `${durationTime.hours}:${duration}`;
                }

                return duration;
            },
            formatTime: function(seconds) {
                return Str.make(seconds).timeFormat().value();
            },
            secondsToDuration: function(seconds) {
                const hours = Math.floor(seconds / 3600);
                const minutes = Math.floor((seconds % 3600) / 60);
                const remainingSeconds = Math.floor(seconds % 60);

                return {
                    hours: hours.toString().padStart(2, '0'),
                    minutes: minutes.toString().padStart(2, '0'), 
                    seconds: remainingSeconds.toString().padStart(2, '0')
                };
            }
        };

        app.config.globalProperties.$mdInline = function(text, options = {}) {
            return mdInlineRenderer(text, options);
        };
    }
};
