import {createBehavior} from '@area17/a17-behaviors';

const walmartIntegration = createBehavior('walmartIntegration',
    {
        addCartIcon(ingredient) {
            const ingredientName = ingredient.querySelector('[data-ingredient-name]').textContent;
            const searchUrl = `https://www.walmart.com/search?q=${encodeURIComponent(ingredientName)}`;
            // add a cart icon to the ingredient that is clickable
            const cartIcon = document.createElement('button');
            cartIcon.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
        `;
            cartIcon.classList.add('inline-block', 'text-grey-500', 'ml-2');
            cartIcon.addEventListener('click', function () {
                window.open(searchUrl, '_blank');
            });

            ingredient.appendChild(cartIcon);
        }
    },
    {
        init() {
            document.addEventListener('DOMContentLoaded', function () {
                // search for any ingredients using `data-ingredient` attribute
                // the ingredient name will have a `data-ingredient-name` attribute
                const ingredients = document.querySelectorAll('[data-ingredient]');
                ingredients.forEach(ingredient => {
                    this.addCartIcon(ingredient);
                });
            });
        }
    }
);

export default walmartIntegration;