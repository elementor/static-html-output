#!/bin/bash

### Outdated script, but may be worth reviving diagnostics theme

# remove previous version, while preserving settings.
wp plugin deactivate --uninstall wordpress-static-html-plugin

# install latest development version
wp plugin install https://github.com/leonstafford/statichtmloutput/archive/master.zip

# rename folder for correct plugin slug
mv wp-content/plugins/statichtmloutput wp-content/plugins/wordpress-static-html-plugin

#activate the renamed plugin
wp plugin activate wordpress-static-html-plugin

#activate the renamed plugin
wp statichtmloutput diagnostics

# install theme for running diagnostics
wp theme install https://github.com/leonstafford/diagnostic-theme-for-statichtmloutput/archive/master.zip --activate

# generate an archive
wp statichtmloutput generate

# pipe generate time into a TXT file and have this loaded by the theme via JS...

# this allows for some general benchmarking/comparison across hosts

# test deploy
wp statichtmloutput deploy --test

# deploy (to folder "/mystaticsite/" if no existing options set)
wp statichtmloutput deploy
