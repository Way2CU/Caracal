# Set session variable - `cms:session`

This tag is used to set session variable which can later be used in different places such as contact forms and conditional parsing of templates. System provides option for setting variable value only once to ensure value is not overwritten on subsequent parsings.

```xml
<cms:session
	name="variable_name"
	once="yes"
	value="something"
	/>
```

Supported attributes are:
- `name` - Name of the variable to store value in to;
- `once` - Optional attribute telling system if it's allowed to change value once set;
- `value` - Raw value or [`cms:eval`-ed](eval.markdown) value.
