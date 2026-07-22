# Articles

This module provides storage and handling of articles in multiple languages. It can be used for anything from
blog posts to containers of small text blocks. Module, when used with default templates, uses [Markdown][] markup
language.

Articles can optionally be organized into *groups* (categories) and support a simple up/down voting mechanism
with generated rating images. Both articles and groups have a numeric `id` and an optional human-friendly
`text_id`, either of which can be used to address them from templates.

## Features

- Multi-language `title` and `content` for every article and group;
- Optional grouping of articles into named categories;
- Per-article up/down voting, limited to one vote per client address, with a computed rating and rating image
  (stars or circles);
- Frontend rendering through framework tags as well as an AJAX/JSON interface;
- Backend management of both articles and groups.

## Dependencies

The module has no required dependencies. When the `gallery` module is available it can optionally generate a
sprite of images associated with a list of articles (see the `generate_sprite` parameter of `show_list`). The
module also connects to the `backend` and `search` events when those systems are present.

## Documentation

- [Database structure](database.markdown)
- [Functions](functions.markdown)

[Markdown]: https://daringfireball.net/projects/markdown/basics
