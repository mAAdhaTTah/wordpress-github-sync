#!/bin/bash

################################################################################
# Convert readme files between WordPress Plugin readme and Github markdown format
# This script is used by the deploy-plugin.sh script
#
# Author: Sudar <http://sudarmuthu.com>
#
# License: Beerware ;)
#
# Usage:
# ./path/to/readme-converter.sh [from-file] [to-file] [format to-wp|from-wp]
#
# Refer to the README.md file for information about the different options
#
# Credit: Uses most of the code from the following places
#       https://github.com/ocean90/svn2git-tools/
################################################################################

# wrapper for sed
_sed() {
	# -E is used so that it is compatible in both Mac and Ubuntu.
	sed -E "$1" $2 > $2.tmp && mv $2.tmp $2
}

# Check if file exists
file_exists () {
	if [ ! -f $1 ]; then
		echo "$1 doesn't exist"
		exit 1;
	fi
}

# Handle screenshots section for WP to Markdown format
ss_wptomd () {
	awk '
		BEGIN {                             # Set the field separator to a . for picking up line num
			FS = "."
		}
		/^==/ {                             # If we hit a new section stop
			flag = 0
		}
		NF && flag && $1 ~ /^[0-9]+$/ {     # If the line is not empty and the flag is set add text
			print "![](screenshot-" $1 ".png)"
			sub(/^[0-9]+. */, "")           # Remove the leading line number (no limit on fields)
		}
		/^== Screenshots ==/ {              # If we hit the screenshot section start
			flag = 1
		}
		{                                   # Print all the lines in the file
			print
		}
	' $1 > $2
}

# Handle screenshots section for Markdown to WP format
ss_mdtowp () {
	awk '
		/^##/ {                             # If we hit a new section stop
			flag = 0
		}
		NF && flag && $0 ~ /^!\[\]/ {       # If the line contains a markdown image
			total = split($0, arr, /[-.]/)
			num = arr[total - 1]
			next
		}
		flag && num && NF > 1 {             # If we have a image number
			print num ". " $0
			num = 0
			next
		}
		/^## Screenshots ##/ {              # If we hit the screenshot section start
			flag = 1
		}
		{                                   # Print all the lines in the file
			print
		}
	' $1 > $2
}

# WP to Markdown format
wptomarkdown () {
	file_exists $1

	ss_wptomd $1 $2

	PLUGINMETA=("Contributors" "Donate link" "Donate Link" "Tags" "Requires at least" "Tested up to" "Stable tag" "License" "License URI" "Requires base plugin" "Requires base plugin version")
	for m in "${PLUGINMETA[@]}"
	do
		_sed 's/^'"$m"':/**'"$m"':**/g' $2
	done

	_sed "s/===([^=]+)===/#\1#/g" $2
	_sed "s/==([^=]+)==/##\1##/g" $2
	_sed "s/=([^=]+)=/###\1###/g" $2
}

# Markdown to WP format
markdowntowp () {
	file_exists $1

	ss_mdtowp $1 $2

	PLUGINMETA=("Contributors" "Donate link" "Donate Link" "Tags" "Requires at least" "Tested up to" "Stable tag" "License" "License URI" "Requires base plugin" "Requires base plugin version")
	for m in "${PLUGINMETA[@]}"
	do
		_sed 's/^(\*\*|__)'"$m"':(\*\*|__)/'"$m"':/g' $2
	done

	echo "" >> $2
	cat CHANGELOG.md >> $2

	_sed "s/#### ([^#]+) ####/**\1**\\`echo -e '\n\r'`/g" $2
	_sed "s/###([^#]+)###/=\1=/g" $2
	_sed "s/##([^#]+)##/==\1==/g" $2
	_sed "s/#([^#]+)#/===\1===/g" $2
	_sed "s/\[([^#]+)\]\[([^#]+)\]/\1/g" $2
}

if [ $# -eq 3 ]; then

	if [ "$3" == "to-wp" ]; then
		markdowntowp $1 $2
	else
		wptomarkdown $1 $2
	fi

else
		echo >&2 \
		"usage: $0 [from-file] [to-file] [format to-wp|from-wp]"

		exit 1;
fi
