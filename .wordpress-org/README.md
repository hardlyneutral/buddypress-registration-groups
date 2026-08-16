# WordPress.org assets

These files are the plugin-directory artwork for
[wordpress.org/plugins/buddypress-registration-groups-1](https://wordpress.org/plugins/buddypress-registration-groups-1/).

They do **not** ship inside the plugin zip. When releasing, copy them into the
`/assets` directory at the **root** of the plugin's SVN repository (a sibling
of `/trunk` and `/tags`, not inside them):

```
svn co https://plugins.svn.wordpress.org/buddypress-registration-groups-1 svn
cp .wordpress-org/icon.svg .wordpress-org/icon-*.png .wordpress-org/screenshot-*.png svn/assets/
cd svn && svn add --force assets && svn ci -m "Update plugin icon and screenshots"
```

- `icon.svg` — source artwork; wordpress.org serves SVG icons directly.
- `icon-256x256.png` / `icon-128x128.png` — raster fallbacks.
- `screenshot-1.png` … `screenshot-5.png` — directory screenshots; their
  captions come from the numbered list in the `== Screenshots ==` section of
  `readme.txt`. Screenshots were moved here from the plugin root as of 1.3.0,
  so they no longer bloat the download zip. Remove any old
  `screenshot-*.png` files from `/trunk` in the same release.
