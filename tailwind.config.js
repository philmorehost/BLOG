/** Build utility styles ahead of time; never depend on a browser-side compiler. */
module.exports = {
  content: ['./includes/**/*.php', './pages/**/*.php', './admin/**/*.php'],
  theme: { extend: {} },
  plugins: [],
};
