#!/usr/bin/env python

import os
import re
import sys
import base64
import mimetypes


def embed_files(template_file):
	"""Embed files specified in specified template."""
	print '- injecting {0}'.format(os.path.basename(template_file))

	file_data = {}
	working_directory = os.path.abspath(os.path.dirname(sys.argv[0]))
	match_files = re.compile(r'(%-embed:\s?(.+)%)', re.U | re.I)

	# read file to a buffer
	with open(template_file, 'r') as raw_file:
		template = raw_file.read()

	# find matches
	matches = match_files.findall(template)

	# load and encode files
	for match in matches:
		file_name = os.path.abspath(os.path.join(working_directory, match[1]))

		# skip unknown files
		if not os.path.exists(file_name):
			continue

		# detect mimetype
		mime_type = mimetypes.guess_type(file_name)[0]

		if (mime_type == 'text/css'):
			# parse css files separately
			raw_data = embed_files(file_name)

		else:
			# load file
			with open(file_name, 'rb') as raw_file:
				raw_data = raw_file.read()

		# encode file data
		encoded_file = base64.encodestring(raw_data).replace('\n', '')

		# replace data
		template = template.replace(
					match[0],
					'data:{0};base64,{1}'.format(mime_type, encoded_file)
				)

	return template


if __name__ == '__main__':
	# exit if arguments are missing or path doesn't exist
	if not len(sys.argv) > 1 or not os.path.exists(sys.argv[1]):
		sys.exit(1)

	# parse template
	template_file = sys.argv[1]
	template = embed_files(template_file)

	# replace file
	with open(template_file, 'w+') as raw_file:
		raw_file.write(template)
