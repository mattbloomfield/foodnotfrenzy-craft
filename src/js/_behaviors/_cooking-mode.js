import {createBehavior} from '@area17/a17-behaviors';

const cookingMode = createBehavior('cookingMode',
    {
        enterCookingMode() {
            // Save scroll position
            const scrollPosition = window.scrollY;

            // Add class to body
            document.body.classList.add('cooking-mode');

            // Create a wrapper for recipe content
            const recipeContentWrapper = document.createElement('div');
            recipeContentWrapper.className = 'recipe-content-wrapper';

            // Get the recipe title and content
            const recipeTitle = document.querySelector('h1').cloneNode(true);
            const recipeContent = document.querySelector('.recipe-content').cloneNode(true);

            // Add to wrapper
            recipeContentWrapper.appendChild(recipeTitle);
            recipeContentWrapper.appendChild(recipeContent);

            // Add wrapper to body
            document.body.appendChild(recipeContentWrapper);

            // Prevent screen from sleeping
            this.preventSleep();

            // Setup scroll progress tracking
            this.setupScrollProgress(recipeContentWrapper);
        },

        /**
         * Exit cooking mode
         */
        exitCookingMode() {
            // Remove cooking mode class
            document.body.classList.remove('cooking-mode');

            // Remove recipe content wrapper
            const wrapper = document.querySelector('.recipe-content-wrapper');
            if (wrapper) {
                wrapper.remove();
            }

            // Allow screen to sleep again
            this.allowSleep();
        },

        /**
         * Prevent screen from sleeping
         */
        preventSleep() {
            if ('wakeLock' in navigator) {
                // Use the Wake Lock API if available
                navigator.wakeLock.request('screen')
                    .then(lock => {
                        this.wakeLock = lock;
                        console.log('Wake Lock is active');

                        // Release wake lock if page visibility changes
                        document.addEventListener('visibilitychange', this.handleVisibilityChange);
                    })
                    .catch(err => {
                        console.error(`Wake Lock error: ${err.name}, ${err.message}`);

                        // Fallback method - play a silent video loop
                        this.useFallbackSleepPrevention();
                    });
            } else {
                // Fallback for browsers that don't support Wake Lock API
                this.useFallbackSleepPrevention();
            }
        },

        /**
         * Fallback method to prevent screen sleep
         */
        useFallbackSleepPrevention() {
            // Create a hidden video element playing a silent video
            const video = document.createElement('video');
            video.setAttribute('id', 'sleep-prevention-video');
            video.setAttribute('src', 'data:video/mp4;base64,AAAAIGZ0eXBpc29tAAACAGlzb21pc28yYXZjMW1wNDEAAAAIZnJlZQAAAFhtZGF0AAAAMGRhdGEAAAAPAAAAAQAAAQAAAAEAAAJYbGliZmFhYyAxLjI4AAAAAAAAAAAAAAAAAAAAAAIAre7u7g==');
            video.setAttribute('loop', '');
            video.style.display = 'none';
            document.body.appendChild(video);

            // Play the video
            video.play()
                .catch(err => console.error('Fallback sleep prevention failed:', err));
        },

        /**
         * Allow screen to sleep again
         */
        allowSleep() {
            // Release wake lock if it exists
            if (typeof this.wakeLock !== 'undefined' && this.wakeLock !== null) {
                this.wakeLock.release()
                    .then(() => {
                        console.log('Wake Lock released');
                        this.wakeLock = null;
                    });

                document.removeEventListener('visibilitychange', this.handleVisibilityChange);
            }

            // Remove fallback video if it exists
            const video = document.getElementById('sleep-prevention-video');
            if (video) {
                video.pause();
                video.remove();
            }
        },

        /**
         * Handle visibility change for wake lock
         */
        handleVisibilityChange() {
            if (document.visibilityState === 'visible' && wakeLock === null) {
                this.preventSleep();
            }
        },

        /**
         * Setup scroll progress tracking
         */
        setupScrollProgress(container) {
            container.addEventListener('scroll', () => {
                const scrollHeight = container.scrollHeight - container.clientHeight;
                const scrolled = (container.scrollTop / scrollHeight) * 100;
                this.progressBar.style.width = scrolled + '%';
            });
        },
    },
    {
        init() {
            this.wakeLock = null;
            const cookingModeBtn = document.createElement('button');
            cookingModeBtn.className = 'cooking-mode-btn';
            cookingModeBtn.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M8 3v3a2 2 0 0 1-2 2H3"></path>
                    <path d="M21 8h-3a2 2 0 0 1-2-2V3"></path>
                    <path d="M3 16h3a2 2 0 0 1 2 2v3"></path>
                    <path d="M16 21v-3a2 2 0 0 1 2-2h3"></path>
                </svg>
                Cooking Mode
            `;
            document.body.appendChild(cookingModeBtn);

            // Create exit button (initially hidden)
            const exitButton = document.createElement('button');
            exitButton.className = 'exit-cooking-mode';
            exitButton.textContent = 'Exit Cooking Mode';
            document.body.appendChild(exitButton);

            // Create progress bar (initially hidden)
            const progressBar = document.createElement('div');
            progressBar.className = 'progress-bar';
            document.body.appendChild(progressBar);

            // Enter cooking mode
            cookingModeBtn.addEventListener('click', () => {
                this.enterCookingMode();
            });

            // Exit cooking mode
            exitButton.addEventListener('click', () => {
                this.exitCookingMode();
            });

            // Also exit on ESC key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && document.body.classList.contains('cooking-mode')) {
                    this.exitCookingMode();
                }
            });
        }
    }
);

export default cookingMode;