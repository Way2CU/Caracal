# Show parameter language tags for backend use - `cms:language_data`

This tag is used only when creating custom module with backend user interface. It's used to provide all the language data for specified parameter to the `LanguageSelector` JavaScript object which allows users to enter data for all languages without having to switch interface language.

System will generate data tag for each language configured in system. After `LanguageSelector` object is created it will immediately remove these tags from the DOM tree. This tag must be called immediately after `<input>` tag in interface template.

Tag recognizes the following attributes:

- `param` - Parameter name whose values should be rendered.

Example:

```xml
<cms:language_data param="title"/>
```

Outputs:

```xml
<input name="..." class="multi-language"/>
<data field="title" language="en">Value in English</data>
<data field="title" language="he">ערך בעברית</data>
```
