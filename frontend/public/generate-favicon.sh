#!/bin/bash
# Generate PNG favicon from SVG using ImageMagick or Inkscape if available

if command -v convert &> /dev/null; then
    echo "Using ImageMagick to generate favicon.ico..."
    convert -background none -density 256 favicon.svg -define icon:auto-resize=16,32,48 favicon.ico
    echo "✓ Generated favicon.ico"
elif command -v inkscape &> /dev/null; then
    echo "Using Inkscape to generate PNG..."
    inkscape favicon.svg --export-type=png --export-filename=favicon-32.png -w 32 -h 32
    inkscape favicon.svg --export-type=png --export-filename=favicon-16.png -w 16 -h 16
    if command -v icotool &> /dev/null; then
        icotool -c -o favicon.ico favicon-16.png favicon-32.png
        rm favicon-16.png favicon-32.png
        echo "✓ Generated favicon.ico"
    else
        echo "⚠ icotool not found, keeping PNG files"
    fi
else
    echo "⚠ Neither ImageMagick nor Inkscape found."
    echo "  SVG favicon will work in modern browsers."
    echo "  For .ico support, install: apt-get install imagemagick"
fi
