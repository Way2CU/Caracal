
# directories
project_directory := ..
working_directory := $(project_directory)/install
build_directory := $(working_directory)/build

# files
template_file := $(working_directory)/install.php
output_file := $(build_directory)/install.php

# scripts
embed_script := $(working_directory)/embed.py

all: build

.prepare:
	mkdir -p $(build_directory)
	cp $(template_file) $(output_file)

.embed:
	$(embed_script) $(output_file)

build: .prepare .embed
	
clean:
	rm -Rf $(build_directory)

