// Simplified Timer with Drag Functionality
document.addEventListener('DOMContentLoaded', function() {
    // Add the timer button to recipe pages
    const recipeContent = document.querySelector('.recipe-content');
    if (recipeContent) {
        // Create the timer button
        const timerBtn = document.createElement('button');
        timerBtn.id = 'cooking-mode-toggle';
        timerBtn.className = 'cooking-mode-btn flex items-center bg-deep-rose hover:bg-warm-brown text-white font-medium rounded-lg py-2 px-4 shadow-md transition duration-200 fixed bottom-6 left-6 z-50';
        timerBtn.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
            </svg>
            Timer
        `;

        // Add the button to the page
        document.body.appendChild(timerBtn);

        // Add event listener to toggle timer
        timerBtn.addEventListener('click', toggleTimer);

        // Create timer element (hidden initially)
        const timerContainer = document.createElement('div');
        timerContainer.id = 'cooking-timer';
        timerContainer.className = 'cooking-timer bg-white rounded-lg shadow-md fixed top-6 right-6 z-50 hidden';
        timerContainer.innerHTML = `
            <div id="timer-handle" class="cursor-move bg-gray-200 rounded-t-lg px-2 py-1 text-xs text-gray-500 flex justify-between">
                <span>Drag</span>
                <button id="timer-close" class="text-gray-500 hover:text-gray-700">×</button>
            </div>
            <div class="p-2 text-center">
                <span class="timer-display block text-xl font-bold">00:00</span>
                <div class="flex justify-center mt-2 gap-1">
                    <button class="timer-start bg-deep-rose text-white text-sm py-1 px-2 rounded">Start</button>
                    <button class="timer-reset bg-gray-300 text-gray-700 text-sm py-1 px-2 rounded">Reset</button>
                </div>
            </div>
        `;
        document.body.appendChild(timerContainer);

        // Make timer draggable
        makeDraggable(timerContainer);

        // Add close button functionality
        document.getElementById('timer-close').addEventListener('click', function() {
            timerContainer.classList.add('hidden');
            timerBtn.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                </svg>
                Timer
            `;
            keepScreenAwake(false);
        });

        // Add timer functionality
        setupTimer();
    }

    // Function to toggle timer
    function toggleTimer() {
        const timerContainer = document.getElementById('cooking-timer');
        const timerBtn = document.getElementById('cooking-mode-toggle');

        // Toggle timer visibility
        if (timerContainer.classList.contains('hidden')) {
            // Show timer
            timerContainer.classList.remove('hidden');
            timerBtn.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                </svg>
                Hide Timer
            `;

            // Prevent screen from sleeping
            keepScreenAwake(true);
        } else {
            // Hide timer
            timerContainer.classList.add('hidden');
            timerBtn.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                </svg>
                Timer
            `;

            // Let the screen sleep normally
            keepScreenAwake(false);
        }
    }

    // Function to make an element draggable
    function makeDraggable(element) {
        const handle = document.getElementById('timer-handle');
        let pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;

        if (handle) {
            // If handle exists, use handle for dragging
            handle.onmousedown = dragMouseDown;
            handle.ontouchstart = dragTouchStart;
        } else {
            // Otherwise, move from anywhere inside the element
            element.onmousedown = dragMouseDown;
            element.ontouchstart = dragTouchStart;
        }

        function dragMouseDown(e) {
            e.preventDefault();
            // Get the mouse cursor position at startup
            pos3 = e.clientX;
            pos4 = e.clientY;
            document.onmouseup = closeDragElement;
            // Call function when cursor moves
            document.onmousemove = elementDrag;
        }

        function dragTouchStart(e) {
            e.preventDefault();
            // Get the touch position at startup
            const touch = e.touches[0];
            pos3 = touch.clientX;
            pos4 = touch.clientY;
            document.ontouchend = closeDragElement;
            // Call function when finger moves
            document.ontouchmove = elementTouchDrag;
        }

        function elementDrag(e) {
            e.preventDefault();
            // Calculate the new cursor position
            pos1 = pos3 - e.clientX;
            pos2 = pos4 - e.clientY;
            pos3 = e.clientX;
            pos4 = e.clientY;
            // Set the element's new position
            element.style.top = (element.offsetTop - pos2) + "px";
            element.style.left = (element.offsetLeft - pos1) + "px";
            // Reset right position to avoid conflicts
            element.style.right = "auto";
        }

        function elementTouchDrag(e) {
            e.preventDefault();
            // Calculate the new touch position
            const touch = e.touches[0];
            pos1 = pos3 - touch.clientX;
            pos2 = pos4 - touch.clientY;
            pos3 = touch.clientX;
            pos4 = touch.clientY;
            // Set the element's new position
            element.style.top = (element.offsetTop - pos2) + "px";
            element.style.left = (element.offsetLeft - pos1) + "px";
            // Reset right position to avoid conflicts
            element.style.right = "auto";
        }

        function closeDragElement() {
            // Stop moving when mouse button/touch is released
            document.onmouseup = null;
            document.onmousemove = null;
            document.ontouchend = null;
            document.ontouchmove = null;
        }
    }

    // Function to setup the timer
    function setupTimer() {
        const timerDisplay = document.querySelector('.timer-display');
        const startBtn = document.querySelector('.timer-start');
        const resetBtn = document.querySelector('.timer-reset');
        let timerInterval;
        let seconds = 0;

        startBtn.addEventListener('click', function() {
            if (this.textContent === 'Start') {
                // Start timer
                timerInterval = setInterval(() => {
                    seconds++;
                    const mins = Math.floor(seconds / 60);
                    const secs = seconds % 60;
                    timerDisplay.textContent = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
                }, 1000);

                this.textContent = 'Pause';
                this.classList.remove('bg-deep-rose');
                this.classList.add('bg-warm-brown');
            } else {
                // Pause timer
                clearInterval(timerInterval);
                this.textContent = 'Start';
                this.classList.remove('bg-warm-brown');
                this.classList.add('bg-deep-rose');
            }
        });

        resetBtn.addEventListener('click', function() {
            clearInterval(timerInterval);
            seconds = 0;
            timerDisplay.textContent = '00:00';
            startBtn.textContent = 'Start';
            startBtn.classList.remove('bg-warm-brown');
            startBtn.classList.add('bg-deep-rose');
        });
    }

    // Function to keep screen awake
    function keepScreenAwake(enable) {
        if (!('wakeLock' in navigator)) {
            console.log('Wake Lock API not supported in this browser');
            return;
        }

        if (enable) {
            // Request a wake lock
            navigator.wakeLock.request('screen')
                .then(lock => {
                    console.log('Wake Lock is active');
                    // Store the wake lock
                    window.wakeLock = lock;
                })
                .catch(err => {
                    console.error(`Failed to get wake lock: ${err.name}, ${err.message}`);
                });
        } else if (window.wakeLock) {
            // Release the wake lock
            window.wakeLock.release()
                .then(() => {
                    console.log('Wake Lock released');
                    window.wakeLock = null;
                });
        }
    }
});