# WordPress.org assets

These files are the plugin-directory artwork for
[wordpress.org/plugins/buddypress-registration-groups-1](https://wordpress.org/plugins/buddypress-registration-groups-1/).

They do **not** ship inside the plugin zip. When releasing, copy them into the
`/assets` directory at the **root** of the plugin's SVN repository (a sibling
of `/trunk` and `/tags`, not inside them):

```
svn co https://plugins.svn.wordpress.org/buddypress-registration-groups-1 svn
cp .wordpress-org/icon.svg .wordpress-org/icon-*.png svn/assets/
cd svn && svn add assets/icon* && svn ci -m "Add plugin icon"
```

- `icon.svg` — source artwork; wordpress.org serves SVG icons directly.
- `icon-256x256.png` / `icon-128x128.png` — raster fallbacks.

The existing `screenshot-*.png` files in the plugin root can also be moved to
`/assets` on a future release to slim down the download zip.
