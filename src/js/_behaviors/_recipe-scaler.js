import {createBehavior} from '@area17/a17-behaviors';

const recipeScaler = createBehavior('recipeScaler',
    {
        async setScale(scale) {
            const url = new URL(window.location.href);
            url.searchParams.set('scale', scale);

            const article = document.getElementById('Recipe');
            article.style.opacity = '0.5';

            try {
                const response = await fetch(url);
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
                const fresh = doc.getElementById('Recipe');
                if (!fresh) throw new Error('No #Recipe in response');
                article.replaceWith(fresh);

                if (scale === '1') url.searchParams.delete('scale');
                window.history.replaceState(null, '', url);
            } catch (err) {
                console.error('Recipe scaling failed:', err);
                window.location.assign(url);
            }
        }
    },
    {
        init() {
            this.$node.addEventListener('change', (e) => {
                if (e.target.name === 'scale') {
                    this.setScale(e.target.value);
                }
            });
        }
    }
);

export default recipeScaler;
