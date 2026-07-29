import { Wave } from '@foobar404/wave';

const audioVisualizer = () => {
    return {
        audioStream: null,
        canvas: null,
        audioContext: null,
        animationInstance: null,
        audioElement: {},
        config: {
            count: 50,
            lineWidth: 2,
            frequencyBand: 'mids',
            fillColor: 'rgba(0, 122, 255, 1)',
            lineColor: 'rgba(0, 122, 255, 1)'
        },
        setAudioElement: function(audioElement = null) {
            if (audioElement) {
                this.audioElement = audioElement;
            }
    
            return this;
        },
        setCanvas: function(canvas = null) {
            if (canvas) {
                this.canvas = canvas;
            }
    
            return this;
        },
        linesWaveStart: function (config = {}) {
            this.config = {
                ...this.config,
                ...config
            };
            
            this.animationInstance = new Wave(this.audioElement, this.canvas, true);
    
            this.animationInstance.addAnimation(new this.animationInstance.animations.Wave(this.config));
        }
    };
} 

export { audioVisualizer };
