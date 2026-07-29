export default class Num {
    num = 0;

    static make(num) {
        return new Num(num);
    }

    format() {
        if (this.num >= 1000 && this.num < 1000000) {
            (this.num / 1000).toFixed(1) + 'k';
        } 
        
        else if (this.num >= 1000000 && this.num < 1000000000) {
            (this.num / 1000000).toFixed(1) + 'M';
        } 
        
        else if (this.num >= 1000000000) {
            (this.num / 1000000000).toFixed(1) + 'B';
        } 
        
        else {
            this.num.toString();
        }

        return this;
    }
    
    value() {
        return this.num;
    }
}