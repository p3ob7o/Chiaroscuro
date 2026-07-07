# Chiaroscuro Standards Notes

- `style.css` keeps a small number of scoped `!important` declarations where WordPress block layout classes otherwise override committed template spacing.
- `functions.php` prints the theme bootstrap script and Newsreader font-face rules early in the document head so color mode and local-first font loading resolve before first paint.
- A few patterns and template parts use `wp:html` for static semantic markup where nested block serialization would be harder to maintain without changing the design.
