#!/bin/sh
# Build the SQL editor as one Moodle AMD module. CodeMirror 6 is only published as ES modules, so esbuild bundles it.
# "-lazy" keeps the module out of the JavaScript that Moodle loads on every page: only the SQL form loads it.
#
# Usage: cd cm6 && npm ci --ignore-scripts && ./build.sh
set -e
cd "$(dirname "$0")"

MODULE=sqleditor-lazy
OPTIONS="--bundle --format=iife --global-name=sqleditor --target=es2017 --legal-comments=none --log-level=warning"
# No module name: Totara adds it when it serves the file, and a second name breaks the module.
BANNER="define([], function() {"
FOOTER="return sqleditor; });"

npx esbuild src/sqleditor.js $OPTIONS --banner:js="/*eslint-disable*/
$BANNER" --footer:js="$FOOTER" --outfile=../amd/src/$MODULE.js
npx esbuild src/sqleditor.js $OPTIONS --minify --banner:js="$BANNER" --footer:js="$FOOTER" \
    --outfile=../amd/build/$MODULE.min.js
