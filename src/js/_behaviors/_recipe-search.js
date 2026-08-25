import {createBehavior} from '@area17/a17-behaviors';

// Progressive enhancement for /recipes/search: fetch results and swap the
// #SearchResults region in place instead of a full page reload. Falls back to
// a normal GET form submit if anything goes wrong.
const recipeSearch = createBehavior('recipeSearch',
    {
        buildUrl() {
            const form = this.$node;
            const url = new URL(form.getAttribute('action') || window.location.pathname, window.location.origin);
            const data = new FormData(form);

            for (const [key, value] of data.entries()) {
                // Skip empties so the URL stays clean and shareable.
                if (value !== null && String(value).trim() !== '') {
                    url.searchParams.append(key, value);
                }
            }
            // Any filter change resets to the first page.
            url.searchParams.delete('offset');
            return url;
        },

        async run() {
            const url = this.buildUrl();
            const target = document.getElementById('SearchResults');
            if (!target) return;

            target.style.opacity = '0.5';
            target.setAttribute('aria-busy', 'true');

            try {
                const response = await fetch(url, {headers: {'X-Requested-With': 'fetch'}});
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
                const fresh = doc.getElementById('SearchResults');
                if (!fresh) throw new Error('No #SearchResults in response');
                target.replaceWith(fresh);
                window.history.replaceState(null, '', url);
            } catch (err) {
                console.error('Recipe search failed:', err);
                window.location.assign(url);
            }
        },

        debouncedRun() {
            clearTimeout(this._timer);
            this._timer = setTimeout(() => this.run(), 350);
        },
    },
    {
        init() {
            const typed = (el) => ['search', 'number', 'text'].includes(el.type);
            // Instant on discrete choices (radios, select); debounced while typing.
            this.$node.addEventListener('change', (e) => {
                if (!typed(e.target)) this.run();
            });
            this.$node.addEventListener('input', (e) => {
                if (typed(e.target)) this.debouncedRun();
            });
            this.$node.addEventListener('submit', (e) => {
                e.preventDefault();
                clearTimeout(this._timer);
                this.run();
            });
        },
        destroy() {
            clearTimeout(this._timer);
        },
    }
);

export default recipeSearch;
