const checkTextOverflow = (htmlElement, maxTextLines) => {
    const style = window.getComputedStyle(htmlElement);
    
    let lineHeight = parseFloat(style.lineHeight);

    if (isNaN(lineHeight)) {
        lineHeight = parseFloat(style.fontSize) * 1.2;
    }

    const maxHeight = lineHeight * maxTextLines;

    return htmlElement.scrollHeight > maxHeight;
}

export { checkTextOverflow };