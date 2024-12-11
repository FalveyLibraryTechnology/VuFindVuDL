# VuDL module for VuFind®

This is the code used by [Villanova's Digital Library](https://digital.library.villanova.edu/) to integrate [VuFind®](https://vufind.org) and [VuDL](https://github.com/FalveyLibraryTechnology/VuDL).

## About this Project

Since Villanova is currently the only user of this code, it contains several Villanova-specific details. If others wish to begin collaborating, the code can be made more generic.

Font files are not included in this repository due to licensing issues, so there may be some minor cosmetic flaws if you install it as-is on a local system.

## Installation

1. Copy the files from this repository into your $VUFIND_HOME directory.

2. Copy $VUFIND_HOME/config/vufind/VuDL.ini to $VUFIND_LOCAL_DIR/config/vufind/VuDL.ini, and edit the copy to include appropriate Fedora credentials, IIIF server URL, etc.

3. Obtain a build of the [Universal Viewer](https://github.com/UniversalViewer/universalviewer) and copy it into the $VUFIND_HOME/themes/vudiglib/assets/uv-x.y.z directory, where x.y.z is the version of UV that you built. (As of this writing, 4.0.25 is the most recent tested version).

4. Edit your $VUFIND_LOCAL_DIR/httpd-vufind.conf file, add "DigLib" to your VUFIND_LOCAL_MODULES setting, and add these lines inside your `<Location>` section, below `RewriteEngine On`:

```
  # UV default embed URL rewrite:
  RewriteRule ^/uv.html /themes/vudiglib/assets/uv-x.y.z/uv.html [R=301,L]

  # Rewrite to allow different versions of Universal Viewer to be handled cleanly;
  # redirects old versions to the latest version. Note that both RewriteCond and
  # RewriteRule lines must be updated to point to new version whenever UV is updated.
  RewriteCond $1 !^x.y.z
  RewriteRule /themes/vudiglib[3]?/assets/uv-([^/]*)/(.*)$ /themes/vudiglib/assets/uv-x.y.z/$2 [R=301,L]
```

(Be sure to replace x.y.z with the actual UV version in the code).

## Git Branches

The dev branch contains the latest code. Other branches are named for compatible VuFind® versions -- e.g. vufind-10.0 contains code that is compatible with VuFind® 10.0.x.