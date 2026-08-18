import {createBehavior} from '@area17/a17-behaviors';

const STORAGE_KEY = `fnf-checked-ingredients:${window.location.pathname}`;

function readChecked() {
    try {
        return new Set(JSON.parse(sessionStorage.getItem(STORAGE_KEY)) || []);
    } catch {
        return new Set();
    }
}

function writeChecked(set) {
    try {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify([...set]));
    } catch {
        // sessionStorage unavailable — checkboxes still work, just don't persist
    }
}

const ingredientChecklist = createBehavior('ingredientChecklist',
    {
        restore() {
            const checked = readChecked();
            this.$node.querySelectorAll('[data-ingredient]').forEach(item => {
                const name = item.querySelector('[data-ingredient-name]')?.getAttribute('data-ingredient-name');
                const checkbox = item.querySelector('[data-ingredient-checkbox]');
                if (name && checkbox) {
                    checkbox.checked = checked.has(name);
                }
            });
        }
    },
    {
        init() {
            this.restore();

            this.$node.addEventListener('change', (e) => {
                if (!e.target.matches('[data-ingredient-checkbox]')) return;
                const name = e.target.closest('[data-ingredient]')
                    ?.querySelector('[data-ingredient-name]')
                    ?.getAttribute('data-ingredient-name');
                if (!name) return;
                const checked = readChecked();
                e.target.checked ? checked.add(name) : checked.delete(name);
                writeChecked(checked);
            });

            // Datastar morphs the list in place when the recipe is re-scaled,
            // which resets checkbox state — re-apply after any DOM change
            this.observer = new MutationObserver(() => this.restore());
            this.observer.observe(this.$node, {childList: true, subtree: true});
        },
        destroy() {
            this.observer?.disconnect();
        }
    }
);

export default ingredientChecklist;
