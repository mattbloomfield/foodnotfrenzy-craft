// Recipe checklist functionality - adds ingredient and step tracking
document.addEventListener('DOMContentLoaded', function() {
    // Find all section headings
    const sectionHeadings = document.querySelectorAll('.recipe-content section h3');

    // Process ingredients
    sectionHeadings.forEach(heading => {
        if (heading.textContent.toLowerCase().includes('ingredient')) {
            const section = heading.closest('section');
            const ingredientItems = section.querySelectorAll('ul li');

            ingredientItems.forEach(item => {
                const itemText = item.innerHTML;
                item.innerHTML = `
                    <label class="flex items-start cursor-pointer">
                        <input type="checkbox" class="mt-1 mr-3 h-5 w-5 accent-deep-rose">
                        <span>${itemText}</span>
                    </label>
                `;
            });
        }
    });

    // Process instructions/steps
    sectionHeadings.forEach(heading => {
        if (heading.textContent.toLowerCase().includes('instruction')) {
            const section = heading.closest('section');
            const instructionSteps = section.querySelectorAll('ol li');

            instructionSteps.forEach(step => {
                // Create a wrapper div for the checkbox and content
                const wrapper = document.createElement('div');
                wrapper.className = 'flex items-start';

                // Create checkbox
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'mt-1 mr-3 h-5 w-5 accent-deep-rose flex-shrink-0';

                // Create text container
                const textSpan = document.createElement('span');
                textSpan.innerHTML = step.innerHTML;

                // Assemble the elements
                wrapper.appendChild(checkbox);
                wrapper.appendChild(textSpan);

                // Replace the original content with our new structure
                step.innerHTML = '';
                step.appendChild(wrapper);
            });
        }
    });

    // Add event listeners to all checkboxes to save state to localStorage
    document.querySelectorAll('.recipe-content input[type="checkbox"]').forEach(checkbox => {
        const recipeId = window.location.pathname.split('/').pop();
        const checkboxId = generateCheckboxId(checkbox);

        // Load saved state
        if (localStorage.getItem(`recipe_${recipeId}_${checkboxId}`) === 'true') {
            checkbox.checked = true;
            checkbox.closest('label, div')?.classList.add('text-gray-500');
        }

        // Save state on change
        checkbox.addEventListener('change', function() {
            localStorage.setItem(`recipe_${recipeId}_${checkboxId}`, this.checked);

            // Add strikethrough effect
            if (this.checked) {
                this.closest('label, div')?.classList.add('text-gray-500');
            } else {
                this.closest('label, div')?.classList.remove('text-gray-500');
            }
        });
    });

    // Helper to generate a unique ID for each checkbox based on its content
    function generateCheckboxId(checkbox) {
        const parent = checkbox.closest('li');
        const index = Array.from(parent.parentNode.children).indexOf(parent);
        const isIngredient = parent.closest('section').querySelector('h3').textContent.toLowerCase().includes('ingredient');

        return isIngredient ? `ingredient_${index}` : `step_${index}`;
    }
});

// Remove the unneeded polyfill since we're not using :has() selector anymore