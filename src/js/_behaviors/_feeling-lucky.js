import {createBehavior} from '@area17/a17-behaviors';

const feelingLucky = createBehavior('feelingLucky',
    {
        async loadRecipe() {
            const category = this.$node.querySelector('input[name="category"]:checked')?.value;
            const url = new URL('/feeling-lucky/result', window.location.origin);
            if (category && category !== 'null') {
                url.searchParams.set('category', category);
            }

            const target = document.getElementById('FeelingLuckyResult');
            target.style.opacity = '0.5';

            try {
                const response = await fetch(url);
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
                const fresh = doc.getElementById('FeelingLuckyResult');
                if (!fresh) throw new Error('No #FeelingLuckyResult in response');
                target.replaceWith(fresh);
            } catch (err) {
                console.error('Feeling lucky failed:', err);
                target.style.opacity = '';
            }
        }
    },
    {
        init() {
            this.$node.addEventListener('submit', (e) => {
                e.preventDefault();
                this.loadRecipe();
            });
        }
    }
);

export default feelingLucky;
