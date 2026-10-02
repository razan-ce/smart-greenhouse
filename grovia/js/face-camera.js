
function ghInitFaceCamera(opts) {
  
  const video = document.getElementById(opts.videoId);
  const canvas = document.getElementById(opts.canvasId);
  const placeholder = document.getElementById(opts.placeholderId);
  const startBtn = document.getElementById(opts.startBtnId);
  const captureBtn = document.getElementById(opts.captureBtnId);
  const retakeBtn = document.getElementById(opts.retakeBtnId);

  let stream = null; // the live camera feed, once it's turned on
  let capturedBlob = null; // the actual photo, once you've taken one

  function stopStream() {
    // turns the camera light off, so it's not left running in the background
    if (stream) { stream.getTracks().forEach((t) => t.stop()); stream = null; }
  }

  startBtn.addEventListener('click', async () => {
    try {
      // ask the browser for permission to use the camera, facing the user (not the back camera)
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
      video.srcObject = stream; // pipe the live camera feed into the <video> tag
      placeholder.hidden = true; // hide the "camera not started" message
      video.hidden = false; // show the live video
      canvas.hidden = true;
      startBtn.hidden = true; // swap the buttons: hide start...
      captureBtn.hidden = false; // ...show take photo
      if (opts.onStateChange) opts.onStateChange('camera-on');
    } catch (err) {
      // user said no to camera permission, or there's no camera at all
      placeholder.querySelector('p').textContent = 'Could not access the camera — check permissions.';
    }
  });

  captureBtn.addEventListener('click', () => {
    // freeze the current video frame onto the canvas, that's how we "take" the photo
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    canvas.toBlob((blob) => {
      capturedBlob = blob; // save the actual photo file, ready to upload later
      if (opts.onCapture) opts.onCapture(blob);
    }, 'image/jpeg', 0.92);

    video.hidden = true;
    canvas.hidden = false; // now show the frozen photo instead of the live video
    captureBtn.hidden = true;
    retakeBtn.hidden = false; // swap to the retake button
    stopStream(); // don't need the live camera anymore once we have the photo
    if (opts.onStateChange) opts.onStateChange('captured');
  });

  retakeBtn.addEventListener('click', () => {
    // throw away the photo and go back to the very start
    capturedBlob = null;
    canvas.hidden = true;
    retakeBtn.hidden = true;
    startBtn.hidden = false;
    placeholder.hidden = false;
    video.hidden = true;
    if (opts.onStateChange) opts.onStateChange('reset');
  });

  // hand back a few things the page itself might need to use
  return {
    getBlob: () => capturedBlob, // the page reads this when it's time to actually upload the photo
    stop: stopStream,
    reset: () => retakeBtn.hidden ? null : retakeBtn.click(), // lets the page reset the camera from outside
  };
}
