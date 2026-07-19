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
            // Slimmed weight scale: existing font-bold/black classes render
            // lighter app-wide via the variable font (100–900)
            fontWeight: {
                medium:    '400',
                semibold:  '450',
                bold:      '500',
                extrabold: '550',
                black:     '600',
            },
        },
    },
    plugins: [],
}
