import {createBehavior} from '@area17/a17-behaviors';

const walmartIntegration = createBehavior('walmartIntegration',
    {
        addCartIcon(ingredient) {
            const ingredientName = ingredient.querySelector('[data-ingredient-name]').textContent;
            const searchUrl = `https://www.walmart.com/search?q=${encodeURIComponent(ingredientName)}`;
            const cartIcon = this.$node.querySelector('[data-cart-icon]')
            cartIcon.addEventListener('click', function () {
                window.open(searchUrl, '_blank');
            });
        }
    },
    {
        init() {
            this.addCartIcon(this.$node);
        }
    }
);

export default walmartIntegration;