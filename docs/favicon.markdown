# Site Favorite Icon

System will automatically detect and use a favicon from the site's images directory
(`$images_path`, `site/images/` by default). Icon should not be smaller than 16 by 16 pixels.

Detection happens in the following order:

- `site/images/favicon.png` - a single icon, used as-is (16x16);
- `site/images/favicon/` - a directory holding multiple sizes; system looks for
  `16.png`, `32.png` and `64.png` and registers each one that exists;
- otherwise a built-in default icon (16, 32 and 64 pixel variants) is used.
