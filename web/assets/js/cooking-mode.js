// Cooking Mode JavaScript - DOM Transformation
document.addEventListener('DOMContentLoaded', function() {
    // Add the cooking mode toggle button to recipe pages
    const recipeContent = document.querySelector('.recipe-content');
    if (recipeContent) {
        // Create the cooking mode button
        const cookingModeBtn = document.createElement('button');
        cookingModeBtn.id = 'cooking-mode-toggle';
        cookingModeBtn.className = 'cooking-mode-btn flex items-center bg-deep-rose hover:bg-warm-brown text-white font-medium rounded-lg py-2 px-4 shadow-md transition duration-200 fixed bottom-6 left-6 z-50';
        cookingModeBtn.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1.323l3.954 1.582 1.599-.8a1 1 0 01.894 1.79l-1.233.616 1.738 5.42a1 1 0 01-.285 1.05A3.989 3.989 0 0115 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.715-5.349L11 6.477V16h2a1 1 0 110 2H7a1 1 0 110-2h2V6.477L6.237 7.582l1.715 5.349a1 1 0 01-.285 1.05A3.989 3.989 0 015 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.738-5.42-1.233-.616a1 1 0 01.894-1.79l1.599.8L9 4.323V3a1 1 0 011-1z" clip-rule="evenodd" />
            </svg>
            Cooking Mode
        `;

        // Add the button to the page
        document.body.appendChild(cookingModeBtn);

        // Add event listener to toggle cooking mode
        cookingModeBtn.addEventListener('click', toggleCookingMode);

        // Create timer element (hidden initially)
        const timerContainer = document.createElement('div');
        timerContainer.id = 'cooking-timer';
        timerContainer.className = 'cooking-timer bg-white rounded-lg p-4 shadow-md fixed top-6 right-6 z-50 hidden';
        timerContainer.innerHTML = `
            <div class="text-center">
                <span class="timer-display text-2xl font-bold">00:00</span>
                <div class="flex justify-center mt-2 gap-2">
                    <button class="timer-start bg-deep-rose text-white py-1 px-3 rounded">Start</button>
                    <button class="timer-reset bg-gray-300 text-gray-700 py-1 px-3 rounded">Reset</button>
                </div>
            </div>
        `;
        document.body.appendChild(timerContainer);

        // Add timer functionality
        setupTimer();
    }

    // Function to toggle cooking mode
    function toggleCookingMode() {
        const body = document.body;
        const cookingBtn = document.getElementById('cooking-mode-toggle');
        const header = document.querySelector('header');
        const footer = document.querySelector('footer');
        const recipeContainer = document.querySelector('article');
        const recipeContent = document.querySelector('.recipe-content');
        const timerContainer = document.getElementById('cooking-timer');
        const printLink = document.querySelector('.print-link');

        // Toggle cooking mode class
        body.classList.toggle('cooking-mode-active');

        if (body.classList.contains('cooking-mode-active')) {
            // Entering cooking mode
            cookingBtn.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
                Exit Cooking Mode
            `;

            // Hide header, footer, and print link
            header.classList.add('hidden');
            footer.classList.add('hidden');
            if (printLink) printLink.classList.add('hidden');

            // Show timer
            timerContainer.classList.remove('hidden');

            // Modify recipe container
            recipeContainer.classList.add('cooking-mode-container');
            recipeContainer.classList.add('max-w-full');

            // Transform the recipe content for cooking mode
            transformRecipeForCookingMode();

            // Prevent screen from sleeping
            keepScreenAwake(true);

        } else {
            // Exiting cooking mode
            cookingBtn.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1.323l3.954 1.582 1.599-.8a1 1 0 01.894 1.79l-1.233.616 1.738 5.42a1 1 0 01-.285 1.05A3.989 3.989 0 0115 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.715-5.349L11 6.477V16h2a1 1 0 110 2H7a1 1 0 110-2h2V6.477L6.237 7.582l1.715 5.349a1 1 0 01-.285 1.05A3.989 3.989 0 015 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.738-5.42-1.233-.616a1 1 0 01.894-1.79l1.599.8L9 4.323V3a1 1 0 011-1z" clip-rule="evenodd" />
                </svg>
                Cooking Mode
            `;

            // Show header, footer, and print link
            header.classList.remove('hidden');
            footer.classList.remove('hidden');
            if (printLink) printLink.classList.remove('hidden');

            // Hide timer
            timerContainer.classList.add('hidden');

            // Remove cooking mode classes
            recipeContainer.classList.remove('cooking-mode-container', 'max-w-full');

            // Restore the original recipe content
            restoreOriginalRecipe();

            // Let the screen sleep normally
            keepScreenAwake(false);
        }
    }

    // Function to transform recipe for cooking mode
    function transformRecipeForCookingMode() {
        const recipeContent = document.querySelector('.recipe-content');
        const recipeHeader = document.querySelector('article header');
        const recipeTitle = document.querySelector('h1').textContent;

        // Save current state for restoration later
        window.originalRecipeContent = recipeContent.innerHTML;
        window.originalRecipeHeader = recipeHeader.innerHTML;

        // Modify header to be simpler
        recipeHeader.innerHTML = `
            <h1 class="text-3xl md:text-4xl font-bold text-warm-brown mb-4 text-center font-serif">${recipeTitle}</h1>
        `;

        // Find ingredients and instructions sections
        const ingredientsSections = [];
        const instructionsSections = [];

        recipeContent.querySelectorAll('section').forEach(section => {
            const heading = section.querySelector('h3');
            if (!heading) return;

            if (heading.textContent.trim().toLowerCase().includes('ingredient')) {
                ingredientsSections.push(section);
            } else if (heading.textContent.trim().toLowerCase().includes('instruction')) {
                instructionsSections.push(section);
            }
        });

        // Add checkboxes to ingredients
        ingredientsSections.forEach(section => {
            const items = section.querySelectorAll('li');
            items.forEach(item => {
                const itemText = item.innerHTML;
                item.innerHTML = `
                    <label class="flex items-start cursor-pointer">
                        <input type="checkbox" class="mt-1 mr-3 h-5 w-5">
                        <span>${itemText}</span>
                    </label>
                `;
            });
        });

        // Transform instructions for step-by-step navigation
        instructionsSections.forEach(section => {
            const items = Array.from(section.querySelectorAll('li'));
            if (items.length <= 1) return; // No need for navigation with only one step

            const heading = section.querySelector('h3');
            const ol = section.querySelector('ol');

            // Create step navigation
            const stepNav = document.createElement('div');
            stepNav.className = 'step-nav flex justify-between items-center mb-4';
            stepNav.innerHTML = `
                <button class="step-prev bg-deep-rose text-white py-2 px-4 rounded-lg opacity-50 cursor-not-allowed" disabled>&larr; Previous</button>
                <span class="step-indicator font-medium">Step 1 of ${items.length}</span>
                <button class="step-next bg-deep-rose text-white py-2 px-4 rounded-lg">Next &rarr;</button>
            `;

            // Insert step navigation after heading
            heading.after(stepNav);

            // Add data attributes and classes to list items
            items.forEach((item, index) => {
                item.dataset.step = index;
                item.classList.add('cooking-step');

                // Only display the first step initially
                if (index > 0) {
                    item.classList.add('hidden');
                }

                // Add step number
                const stepNumber = document.createElement('div');
                stepNumber.className = 'text-deep-rose font-bold text-xl mb-2';
                stepNumber.textContent = `Step ${index + 1}`;
                item.prepend(stepNumber);
            });

            // Add event listeners for step navigation
            const prevBtn = stepNav.querySelector('.step-prev');
            const nextBtn = stepNav.querySelector('.step-next');
            const indicator = stepNav.querySelector('.step-indicator');

            prevBtn.addEventListener('click', function() {
                const currentStep = ol.querySelector('li.cooking-step:not(.hidden)');
                const currentIndex = parseInt(currentStep.dataset.step);

                if (currentIndex > 0) {
                    // Hide current step, show previous step
                    currentStep.classList.add('hidden');
                    ol.querySelector(`li[data-step="${currentIndex - 1}"]`).classList.remove('hidden');

                    // Update step indicator
                    indicator.textContent = `Step ${currentIndex} of ${items.length}`;

                    // Update button states
                    nextBtn.disabled = false;
                    nextBtn.classList.remove('opacity-50', 'cursor-not-allowed');

                    if (currentIndex - 1 === 0) {
                        this.disabled = true;
                        this.classList.add('opacity-50', 'cursor-not-allowed');
                    }
                }
            });

            nextBtn.addEventListener('click', function() {
                const currentStep = ol.querySelector('li.cooking-step:not(.hidden)');
                const currentIndex = parseInt(currentStep.dataset.step);

                if (currentIndex < items.length - 1) {
                    // Hide current step, show next step
                    currentStep.classList.add('hidden');
                    ol.querySelector(`li[data-step="${currentIndex + 1}"]`).classList.remove('hidden');

                    // Update step indicator
                    indicator.textContent = `Step ${currentIndex + 2} of ${items.length}`;

                    // Update button states
                    prevBtn.disabled = false;
                    prevBtn.classList.remove('opacity-50', 'cursor-not-allowed');

                    if (currentIndex + 1 === items.length - 1) {
                        this.disabled = true;
                        this.classList.add('opacity-50', 'cursor-not-allowed');
                    }
                }
            });
        });

        // Add tab navigation if both ingredients and instructions exist
        if (ingredientsSections.length > 0 && instructionsSections.length > 0) {
            // Create tabs container
            const tabsContainer = document.createElement('div');
            tabsContainer.className = 'cooking-tabs flex border-b border-deep-rose mb-6 sticky top-0 bg-warm-beige z-10';

            // Create tabs
            tabsContainer.innerHTML = `
                <button class="tab-btn py-3 px-6 font-medium text-lg tab-active" data-tab="ingredients">Ingredients</button>
                <button class="tab-btn py-3 px-6 font-medium text-lg" data-tab="instructions">Steps</button>
            `;

            // Add tabs before recipe content
            recipeContent.prepend(tabsContainer);

            // Tag sections for tab control
            ingredientsSections.forEach(section => {
                section.dataset.content = 'ingredients';
                section.classList.add('tab-content', 'tab-active');
            });

            instructionsSections.forEach(section => {
                section.dataset.content = 'instructions';
                section.classList.add('tab-content', 'hidden');
            });

            // Add event listeners for tabs
            tabsContainer.querySelectorAll('.tab-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    // Remove active class from all tabs
                    tabsContainer.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('tab-active'));
                    recipeContent.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));

                    // Add active class to clicked tab
                    btn.classList.add('tab-active');
                    recipeContent.querySelectorAll(`.tab-content[data-content="${btn.dataset.tab}"]`).forEach(section => {
                        section.classList.remove('hidden');
                    });
                });
            });
        }
    }

    // Function to restore original recipe layout
    function restoreOriginalRecipe() {
        if (window.originalRecipeContent && window.originalRecipeHeader) {
            const recipeContent = document.querySelector('.recipe-content');
            const recipeHeader = document.querySelector('article header');

            recipeContent.innerHTML = window.originalRecipeContent;
            recipeHeader.innerHTML = window.originalRecipeHeader;
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