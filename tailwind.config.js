export default {
    content: [
        './templates/**/*.twig',
        './templates/**/**/*.twig',
        './templates/**/**/**/*.twig',
    ],
    theme: {
        extend: {
            fontFamily: {
                'serif': ['"Playfair Display"', 'serif'],
                'sans': ['"Source Sans Pro"', 'sans-serif'],
            },
            colors: {
                'warm-beige': '#f5f0e1',
                'soft-pink': '#ffd5c2',
                'warm-brown': '#8e6e53',
                'deep-rose': '#c86b6b',
                'sage': '#a9c0a6',
            }
        }
    }
}