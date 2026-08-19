import {createBehavior} from '@area17/a17-behaviors';

const YEAR = 60 * 60 * 24 * 365;

function setCookie(name, value) {
    document.cookie = `${name}=${value}; max-age=${YEAR}; path=/; samesite=lax`;
}

const themePicker = createBehavior('themePicker',
    {
        applyPalette(palette) {
            document.documentElement.dataset.palette = palette;
            setCookie('fnf_palette', palette);
            this.reflect();
        },
        applyScheme(scheme) {
            if (scheme === 'auto') {
                delete document.documentElement.dataset.theme;
            } else {
                document.documentElement.dataset.theme = scheme;
            }
            setCookie('fnf_scheme', scheme);
            document.querySelector('meta[name="color-scheme"]')
                ?.setAttribute('content', scheme === 'auto' ? 'light dark' : scheme);
            this.reflect();
        },
        reflect() {
            const html = document.documentElement;
            const palette = html.dataset.palette || 'hearth';
            const scheme = html.dataset.theme || 'auto';

            this.$node.querySelectorAll('[data-set-palette]').forEach(btn => {
                const active = btn.dataset.setPalette === palette;
                btn.setAttribute('aria-pressed', active);
                btn.classList.toggle('border-clay', active);
                btn.classList.toggle('border-transparent', !active);
            });
            this.$node.querySelectorAll('[data-set-scheme]').forEach(btn => {
                const active = btn.dataset.setScheme === scheme;
                btn.setAttribute('aria-pressed', active);
                btn.classList.toggle('bg-clay', active);
                btn.classList.toggle('text-white', active);
            });
        }
    },
    {
        init() {
            this.reflect();
            this.$node.addEventListener('click', (e) => {
                const paletteBtn = e.target.closest('[data-set-palette]');
                if (paletteBtn) return this.applyPalette(paletteBtn.dataset.setPalette);
                const schemeBtn = e.target.closest('[data-set-scheme]');
                if (schemeBtn) return this.applyScheme(schemeBtn.dataset.setScheme);
            });
        }
    }
);

export default themePicker;
