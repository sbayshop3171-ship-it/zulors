/**
 * A simple wrapper around an array providing some handy methods.
 *
 * @class Arr
 * @constructor
 * @param {Array} [items=[]] The items to be used in the array.
 */

export default class Arr {
    items = [];

    constructor(items = []) {
        this.items = items;
    }

    static make(items = []) {
        return new Arr(items);
    }

    get(index, defaultValue = null) {
        if (index >= 0 && index < this.items.length) {
            return this.items[index];
        }
    
        return defaultValue;
    }

    chunk(size) {
        let res = [];
    
        if (this.items.length === 0 || size <= 0) {
            return res;
        }
    
        for (let i = 0; i < this.items.length; i += size) {
            res.push(this.items.slice(i, i + size));
        }
    
        this.items = res;
    
        return this;
    }

    removeItem(itemIndex) {
        if (itemIndex >= 0 && itemIndex < this.items.length) {

            this.items.splice(itemIndex, 1);

            return this;
        }
    
        console.warn(`Invalid index: ${itemIndex}`);
    
        return this;
    }

    value() {
        return this.items;
    }
}

export { Arr }