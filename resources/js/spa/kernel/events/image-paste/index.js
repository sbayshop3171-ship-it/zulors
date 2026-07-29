const imagePasteHandler = function (event, callback) {

    const clipboardData = event.clipboardData || window.clipboardData;
    const pastedItems = clipboardData.items;
    
    if(typeof pastedItems === 'string') {
        return 1;
    }
    
    for (let i = 0; i < pastedItems.length; i++) {
        const item = pastedItems[i];
        
        if (item.type.startsWith('image')) {
            const imageFile = item.getAsFile();

            if (imageFile && typeof callback === 'function') {
                callback(imageFile);
            }
        }
    }
}

export { imagePasteHandler };