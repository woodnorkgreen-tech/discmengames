/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.vue',
        './resources/**/*.js',
    ],
    theme: {
        extend: {
            colors: {
                // Discmen artwork system: accessible dark teal + exact client cyan.
                discmen: { DEFAULT: '#147783', accent: '#61C8D2', ink: '#100F0D' },
            },
            fontFamily: {
                display: ['Outfit', 'Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            },
            // Slim weight scale: preserve hierarchy without dense, heavy type.
            fontWeight: {
                light:     '250',
                normal:    '300',
                medium:    '350',
                semibold:  '400',
                bold:      '450',
                extrabold: '500',
                black:     '550',
            },
        },
    },
    plugins: [],
}
