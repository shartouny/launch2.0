
const fitInBox = (canvas, width, height) => {
    // create a temp canvas to hold the source image
    const tempCanvas = document.createElement('canvas');
    tempCanvas.width = canvas.width;
    tempCanvas.height = canvas.height;
    const tempCtx = tempCanvas.getContext('2d');
    tempCtx.drawImage(canvas, 0, 0);

    let newHeight = height;
    let newWidth = width;

    // determine new size
    if(width / tempCanvas.width < height / tempCanvas.height){
        // scale by width
        const scale = width / tempCanvas.width;
        newHeight = Math.floor(tempCanvas.height * scale);
    } else {
        // scale by heaight
        const scale = height / tempCanvas.height;
        newWidth = Math.floor(tempCanvas.width * scale);
    }

    // resize and clear canvas
    canvas.height = newHeight;
    canvas.width = newWidth;
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(tempCanvas, 0, 0, canvas.width, canvas.height);

    // cleanup
    tempCanvas.remove();

    return canvas;
}

export default fitInBox;